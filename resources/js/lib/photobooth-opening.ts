import type { CmsIntroExperience } from '@/types/cms-v2';
import type { EditorialPhoto, EnchantedEditorialPresentation } from '@/types/enchanted-editorial';

/** Decode the actual strip elements, including the browser's selected responsive sources. */
export function observePhotoboothImages(strip: HTMLElement, callbacks: { pending: () => void; ready: () => void; failed: () => void }): () => void {
    let generation = 0,
        disposed = false,
        frame = 0,
        readyImages: { image: HTMLImageElement; source: string }[] | undefined;
    const images = () => [...strip.querySelectorAll<HTMLImageElement>('.ee-booth-print img')];
    const source = (image: HTMLImageElement) => image.currentSrc || image.src;
    const valid = (image: HTMLImageElement) => image.complete && image.naturalWidth > 0 && image.naturalHeight > 0;
    const matches = (snapshot: { image: HTMLImageElement; source: string }[]) => {
        const current = images();
        return (
            current.length === snapshot.length &&
            snapshot.every((entry, index) => current[index] === entry.image && source(entry.image) === entry.source && valid(entry.image))
        );
    };
    async function check() {
        if (disposed) return;
        const run = ++generation;
        const current = images();
        if (readyImages && matches(readyImages)) return;
        readyImages = undefined;
        callbacks.pending();
        if (disposed) return;
        if (strip.querySelector('.ee-photo-fallback') || current.some((image) => image.complete && !valid(image))) {
            callbacks.failed();
            return;
        }
        if (current.some((image) => !valid(image))) return;
        const snapshot = current.map((image) => ({ image, source: source(image) }));
        try {
            await Promise.all(current.map((image) => (typeof image.decode === 'function' ? image.decode() : Promise.resolve())));
        } catch {
            if (!disposed && run === generation) {
                if (matches(snapshot)) callbacks.failed();
                else schedule();
            }
            return;
        }
        if (disposed || run !== generation) return;
        // A source may change while decoding, or during responsive selection at the next layout.
        frame = requestAnimationFrame(() => {
            frame = 0;
            if (disposed || run !== generation) return;
            if (!matches(snapshot)) {
                schedule();
                return;
            }
            readyImages = snapshot;
            callbacks.ready();
        });
    }
    function schedule() {
        if (disposed) return;
        ++generation;
        readyImages = undefined;
        callbacks.pending();
        if (disposed) return;
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            frame = 0;
            void check();
        });
    }
    function resized() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            frame = 0;
            void check();
        });
    }
    const observer = new MutationObserver(schedule);
    observer.observe(strip, { subtree: true, childList: true, attributes: true, attributeFilter: ['src', 'srcset', 'sizes', 'media', 'type'] });
    strip.addEventListener('load', check, true);
    strip.addEventListener('error', check, true);
    window.addEventListener('resize', resized);
    void check();
    return () => {
        disposed = true;
        ++generation;
        cancelAnimationFrame(frame);
        observer.disconnect();
        strip.removeEventListener('load', check, true);
        strip.removeEventListener('error', check, true);
        window.removeEventListener('resize', resized);
    };
}

export function photoboothExperience(): CmsIntroExperience {
    return {
        enabled: true,
        intro_style: 'photobooth',
        intro_version: 1,
        intro_settings: {
            opening_text: 'We are getting married',
            open_label: 'Open Invitation',
            font_preset: 'editorial-serif',
            palette_preset: 'ivory-ink',
            variant: { print_preset: 'three-frame' },
        },
    };
}

/** Only already authorized display photos. Never inspect retained/hidden CMS source or the media library. */
export function photoboothPhotos(presentation: EnchantedEditorialPresentation): EditorialPhoto[] {
    const candidates: (EditorialPhoto | null)[] = [presentation.hero.photo];
    const visible = presentation.sections.filter((section) => section.enabled);
    for (const section of [
        ...visible.filter((section) => section.type === 'gallery'),
        ...visible.filter((section) => section.type === 'story'),
        ...visible.filter((section) => section.type === 'event_details'),
    ]) {
        if (section.type === 'gallery') candidates.push(...section.items.map((item) => item.photo));
        if (section.type === 'story') candidates.push(section.photo, ...(section.milestones ?? []).map((milestone) => milestone.photo));
        if (section.type === 'event_details') candidates.push(presentation.details.photo);
    }
    const photos = candidates
        .filter((photo): photo is EditorialPhoto => Boolean(photo))
        .filter((photo, index, list) => list.findIndex((other) => other.url === photo.url) === index)
        .slice(0, 3);
    return photos;
}

export function openingSessionKey(weddingKey: string): string {
    return `ee:photobooth:1:${weddingKey}`;
}

export function shouldShowOpening(presentation: EnchantedEditorialPresentation, publicWebsite: boolean): boolean {
    if (!publicWebsite || !presentation.opening || typeof window === 'undefined') return false;
    try {
        const key = openingSessionKey(presentation.opening.weddingKey);
        if (window.location.hash || window.document.documentElement.dataset.eeOpeningBypass === 'true') {
            sessionStorage.setItem(key, 'opened');
            return false;
        }
        return sessionStorage.getItem(key) !== 'opened';
    } catch {
        return false;
    } // Storage unavailable: keep the invitation directly accessible.
}

import type { CmsIntroExperience } from '@/types/cms-v2';
import type { EditorialPhoto, EnchantedEditorialPresentation } from '@/types/enchanted-editorial';

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

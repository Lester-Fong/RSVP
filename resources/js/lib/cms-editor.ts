import type { WeddingPresentation } from '@/types';
import type { EditorImage, EditorSlots } from '@/types/cms-editor';
import type { CmsGuestGuideSection, CmsImageReference, CmsSectionType, CmsV2Document } from '@/types/cms-v2';
import type { EditorialPhoto, EditorialSection, EnchantedEditorialPresentation } from '@/types/enchanted-editorial';

export function editorPresentation(
    document: CmsV2Document,
    wedding: WeddingPresentation,
    slots: EditorSlots,
    images: readonly EditorImage[],
    supported: readonly CmsSectionType[],
    timeZone = 'Asia/Manila',
): EnchantedEditorialPresentation {
    const photo = (reference?: CmsImageReference | null): EditorialPhoto | null => {
        const asset = images.find((item) => item.id === reference?.asset_id);
        if (!asset || !reference) return null;
        return {
            url: asset.preview_url,
            width: asset.width,
            height: asset.height,
            alt: reference.alt,
            focalPoint: reference.focal === 'top' ? 'top' : reference.focal === 'bottom' ? 'lower' : 'center',
            focal: reference.focal,
            crop: reference.crop,
            frame: reference.frame,
        };
    };
    const visible = document.content.sections.filter((s) => s.enabled && supported.includes(s.type));
    const sections: EditorialSection[] = [];
    for (const section of visible) {
        if (section.type === 'story')
            sections.push({
                id: section.id,
                enabled: true,
                type: 'story',
                eyebrow: 'A love like ours',
                heading: section.data.heading,
                paragraphs: section.data.paragraphs,
                milestones: (section.data.milestones ?? [])
                    .filter((m) => m.enabled && m.title.trim())
                    .map((m) => ({
                        id: m.id,
                        title: m.title,
                        description: m.description ?? '',
                        when: m.when ?? null,
                        photo: photo(m.photo),
                    })),
                photo: photo(slots.story) ?? photo(section.data.milestones?.find((m) => m.enabled && m.photo)?.photo),
                insetPhoto: null,
            });
        if (section.type === 'gallery')
            sections.push({
                id: section.id,
                enabled: true,
                type: 'gallery',
                eyebrow: 'Moments to treasure',
                heading: section.data.heading,
                message: '',
                items: section.data.items.filter((i) => i.enabled).map((i) => ({ id: i.id, photo: photo(i.image), caption: i.caption ?? '' })),
                slideshow: section.data.slideshow,
            });
        if (section.type === 'entourage')
            sections.push({
                id: section.id,
                enabled: true,
                type: 'entourage',
                eyebrow: 'With grateful hearts',
                heading: section.data.heading,
                message: '',
                groups: section.data.groups.filter((g) => g.enabled).map((g) => ({ id: g.id, title: g.title, people: g.people })),
            });
        if (section.type === 'guest_guide') {
            const guide = section.data;
            if (guide.attire.enabled || guide.gifts.enabled || guide.faqs.enabled) {
                sections.push({
                    id: section.id,
                    type: 'guest_guide',
                    enabled: true,
                    ...(guide.attire.enabled ? { attire: guide.attire } : {}),
                    ...(guide.gifts.enabled ? { gifts: guide.gifts } : {}),
                    ...(guide.faqs.enabled ? { faqs: guide.faqs } : {}),
                });
            }
        }
        if (section.type === 'event_details') sections.push({ id: section.id, type: 'event_details', enabled: true });
        if (section.type === 'countdown')
            sections.push({
                id: section.id,
                type: 'countdown',
                enabled: true,
                eyebrow: 'Our wedding day',
                heading: section.data.heading,
                message: section.data.subtitle,
            });
        if (section.type === 'rsvp_information')
            sections.push({
                id: section.id,
                type: 'rsvp_information',
                enabled: true,
                eyebrow: 'Editor preview',
                heading: 'We would love to celebrate with you',
                message: section.data.message,
                closing: '',
            });
        if (section.type === 'closing') sections.push({ id: section.id, type: 'closing', enabled: true, message: section.data.message });
    }
    const message = visible.find((s) => s.type === 'welcome');
    const events = visible.find((s) => s.type === 'event_details');
    return {
        templateKey: 'enchanted-editorial',
        templateVersion: 1,
        wedding,
        timeZone,
        sections,
        hero: {
            eyebrow: 'You are warmly invited',
            message: message?.type === 'welcome' ? message.data.message : '',
            photo: photo(slots.hero),
            insetPhoto: null,
        },
        details: {
            eyebrow: 'Our wedding day',
            heading: 'A celebration of love',
            message: '',
            photo: photo(slots.details),
            insetPhoto: null,
            events: events?.type === 'event_details' ? events.data.events : [],
        },
    };
}

export function newGuestGuideSection(): CmsGuestGuideSection {
    return {
        id: crypto.randomUUID(),
        type: 'guest_guide',
        enabled: true,
        data: {
            attire: { enabled: false, dress_code: 'Dress code to be announced', description: '', swatches: [] },
            gifts: { enabled: false, heading: 'Gifts', message: '', url: null },
            faqs: {
                enabled: true,
                items: [
                    {
                        id: crypto.randomUUID(),
                        question: 'Are my kids welcome?',
                        answer: 'Please check your invitation or contact the couple if you are unsure whether children are included.',
                    },
                    {
                        id: crypto.randomUUID(),
                        question: 'Can I bring a plus one?',
                        answer: 'Please refer to your invitation for the names invited, or contact the couple if you have a question.',
                    },
                    {
                        id: crypto.randomUUID(),
                        question: 'How do I RSVP?',
                        answer: 'If you received a personal invitation, please use its RSVP section. Contact the couple if you need help.',
                    },
                ],
            },
        },
    };
}

export function cmsCsrfHeaders(fallback: string): Record<string, string> {
    const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
    return cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) } : { 'X-CSRF-TOKEN': fallback };
}

export async function editorRequest(
    url: string,
    csrf: string,
    payload?: unknown,
    signal?: AbortSignal,
    method?: 'PUT',
): Promise<Record<string, unknown>> {
    const response = await fetch(url, {
        method: method ?? (payload === undefined ? 'GET' : 'POST'),
        credentials: 'same-origin',
        signal,
        headers: { Accept: 'application/json', ...(payload === undefined ? {} : { 'Content-Type': 'application/json', ...cmsCsrfHeaders(csrf) }) },
        body: payload === undefined ? undefined : JSON.stringify(payload),
    });
    let body;
    try {
        body = await response.json();
    } catch {
        throw new Error('The server response could not be read. Your local edits remain in this tab.');
    }
    if (!response.ok)
        throw Object.assign(new Error(body.message || 'This operation could not finish.'), { errors: body.errors ?? {}, status: response.status });
    return body;
}

export const editorSectionLabels = {
    welcome: 'Welcome',
    event_details: 'Wedding details',
    countdown: 'Countdown',
    story: 'Our Story',
    gallery: 'Gallery',
    entourage: 'Entourage',
    guest_guide: 'Guest Guide',
    rsvp_information: 'RSVP information',
    closing: 'Closing',
} as const;

export function editorErrorMessages(errors: Record<string, string[]>, content: CmsV2Document): string[] {
    const words: Record<string, string> = {
        paragraphs: 'paragraph',
        milestones: 'milestone',
        when: 'date',
        items: 'photograph',
        image: 'photo',
        alt: 'description',
        groups: 'group',
        people: 'person',
    };
    return Object.entries(errors).flatMap(([path, messages]) =>
        messages.map((message) => {
            const group = /content\.sections\.(\d+)\.data\.groups\.(\d+)\.title/.exec(path);
            if (group && /required/i.test(message)) return `Entourage group ${Number(group[2]) + 1} needs a title.`;
            return message
                .replace(/(?:document\.)?content\.sections\.(\d+)((?:\.[\w]+)*)/g, (_whole, index: string, tail: string) => {
                    const section = content.content.sections[Number(index)];
                    const label = section ? editorSectionLabels[section.type] : 'Website section';
                    const field = tail
                        .split('.')
                        .filter((word) => word && !['data', 'value'].includes(word))
                        .map((word) => (/^\d+$/.test(word) ? String(Number(word) + 1) : (words[word] ?? word)))
                        .join(' ');
                    return label + (field ? ' ' + field : '');
                })
                .replace(
                    /(?:document\.)?content\.photo_slots\.(hero|details|story)(?:\.([\w]+))?/g,
                    (_whole, slot: string, field: string) =>
                        ({ hero: 'Main wedding photo', details: 'Wedding details photo', story: 'Our Story photo' })[
                            slot as 'hero' | 'details' | 'story'
                        ] + (field ? ' ' + (words[field] ?? field) : ''),
                )
                .replace(/slots\.(hero|details|story)(?:\.\w+)?/g, 'Photo assignment')
                .replace(/\b(?:document\.)?content\.[\w.]+/g, 'Website settings')
                .replaceAll('CMS content', 'Website content');
        }),
    );
}

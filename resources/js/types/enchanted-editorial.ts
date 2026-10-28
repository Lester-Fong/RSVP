import type { WeddingPresentation } from '@/types';
import type { CmsEventEntry, CmsGuestGuideSection, CmsMilestoneWhen } from '@/types/cms-v2';

/** Resolved display inputs only. Not a persisted CMS document or an RSVP authorization model. */
export interface EditorialPhoto {
    readonly url: string;
    readonly alt: string;
    readonly width: number;
    readonly height: number;
    readonly focalPoint: 'center' | 'top' | 'upper' | 'lower';
    /** Optional curated editor projection; existing trial photos preserve their original framing. */
    readonly focal?: 'center' | 'top' | 'bottom' | 'left' | 'right';
    readonly crop?: 'natural' | 'portrait' | 'landscape' | 'square';
    readonly frame?: 'fit' | 'fill';
}

export interface EnchantedEditorialPresentation {
    readonly templateKey: 'enchanted-editorial';
    readonly templateVersion: 1;
    /** Opt-in from the immutable published experience. Never includes personal-link credentials. */
    readonly opening?: {
        readonly style: 'photobooth';
        readonly version: 1;
        readonly weddingKey: string;
        readonly heading: string;
        readonly openLabel: string;
    };
    readonly wedding: WeddingPresentation;
    /** Required named zone; Asia/Manila is supplied by the synthetic fixture, not a renderer default. */
    readonly timeZone: string;
    readonly sections: readonly EditorialSection[];
    readonly hero: {
        readonly eyebrow: string;
        readonly message: string;
        readonly photo: EditorialPhoto | null;
        readonly insetPhoto: EditorialPhoto | null;
    };
    readonly details: {
        readonly eyebrow: string;
        readonly heading: string;
        readonly message: string;
        readonly photo: EditorialPhoto | null;
        readonly insetPhoto: EditorialPhoto | null;
        readonly events: readonly CmsEventEntry[];
    };
}

/** Proposed visual-only section contracts. Require PHP validation/owned media resolution before persistence. */
interface EditorialSectionBase {
    readonly id: string;
    readonly enabled: boolean;
}

export interface EditorialStorySection extends EditorialSectionBase {
    readonly type: 'story';
    readonly eyebrow: string;
    readonly heading: string;
    readonly paragraphs: readonly string[];
    readonly photo: EditorialPhoto | null;
    readonly insetPhoto: EditorialPhoto | null;
    /** Resolved from the existing Story milestones; never a second content store. */
    readonly milestones?: readonly {
        readonly id: string;
        readonly title: string;
        readonly description: string;
        readonly when: CmsMilestoneWhen | null;
        readonly photo: EditorialPhoto | null;
    }[];
}

export interface EditorialGallerySection extends EditorialSectionBase {
    readonly type: 'gallery';
    readonly eyebrow: string;
    readonly heading: string;
    readonly message: string;
    readonly slideshow?: { readonly autoplay: boolean; readonly interval_seconds: 5 | 8 | 12 };
    readonly items: readonly { readonly id: string; readonly photo: EditorialPhoto | null; readonly caption: string }[];
}

export interface EditorialEntourageSection extends EditorialSectionBase {
    readonly type: 'entourage';
    readonly eyebrow: string;
    readonly heading: string;
    readonly message: string;
    readonly groups: readonly {
        readonly id: string;
        readonly title: string;
        readonly people: readonly { readonly id: string; readonly name: string }[];
    }[];
}

export interface EditorialRsvpSection extends EditorialSectionBase {
    readonly type: 'rsvp_information';
    readonly eyebrow: string;
    readonly heading: string;
    readonly message: string;
    readonly closing: string;
}

export interface EditorialGuestGuideSection extends EditorialSectionBase {
    readonly type: 'guest_guide';
    readonly attire?: CmsGuestGuideSection['data']['attire'];
    readonly gifts?: CmsGuestGuideSection['data']['gifts'];
    readonly faqs?: CmsGuestGuideSection['data']['faqs'];
}

export type EditorialSection =
    | EditorialStorySection
    | EditorialGallerySection
    | EditorialEntourageSection
    | EditorialGuestGuideSection
    | EditorialRsvpSection
    | (EditorialSectionBase & { readonly type: 'closing'; readonly message: string })
    | (EditorialSectionBase & { readonly type: 'event_details' })
    | (EditorialSectionBase & { readonly type: 'countdown'; readonly eyebrow: string; readonly heading: string; readonly message: string });

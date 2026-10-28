/** Internal contracts only. Reserved designs are unavailable to customer workflows. */
/** Mirrors CmsV2Contract::MAX_GALLERY_IMAGES; verified by the shared boundary fixtures. */
export const CMS_GALLERY_IMAGE_LIMIT = 10;
export type WebsiteTemplateKey = 'modern-classic' | 'floral-trial' | 'enchanted-editorial';
export type IntroStyle =
    | 'photobooth'
    | 'royal-wax-seal'
    | 'classic-ivory-letter'
    | 'botanical-bloom'
    | 'modern-folded-card'
    | 'romantic-photo-reveal';
export type CmsPalette = 'ivory-ink' | 'blush-terracotta';
export type CmsFontPreset = 'editorial-serif' | 'script-serif';
export type CmsSectionType =
    | 'welcome'
    | 'event_details'
    | 'countdown'
    | 'closing'
    | 'rsvp_information'
    | 'story'
    | 'gallery'
    | 'entourage'
    | 'guest_guide';

export const CMS_GUEST_GUIDE_SWATCH_LIMIT = 5;
export const CMS_GUEST_GUIDE_FAQ_LIMIT = 7;

export interface WebsiteTemplateManifest {
    readonly key: WebsiteTemplateKey;
    readonly version: 1;
    readonly schemaVersions: readonly (1 | 2)[];
    readonly sectionTypes: readonly (CmsSectionType | 'ceremony' | 'reception')[];
    readonly renderer: string;
    readonly fallback: string;
    readonly themeCapabilities: {
        readonly palettes: readonly (CmsPalette | 'modern-classic')[];
        readonly font_pairs: readonly (CmsFontPreset | 'modern-classic')[];
        readonly custom_css: false;
    };
    readonly available: boolean;
}

interface IntroVariantMap {
    photobooth: { readonly print_preset: 'three-frame' };
    'royal-wax-seal': { readonly seal_preset: 'round-monogram'; readonly liner_preset: 'plain' };
    'classic-ivory-letter': { readonly paper_preset: 'ivory'; readonly edge_preset: 'straight' };
    'botanical-bloom': { readonly artwork_preset: 'botanical-default' };
    'modern-folded-card': { readonly fold_direction: 'horizontal' | 'vertical'; readonly edge_preset: 'straight' };
    'romantic-photo-reveal': { readonly focal_point: 'center' | 'top' | 'bottom'; readonly scrim_preset: 'readable' };
}

export interface IntroductionStyleManifest {
    readonly key: IntroStyle;
    readonly version: 1;
    readonly schemaVersions: readonly 2[];
    readonly sharedSettings: readonly ('opening_text' | 'open_label' | 'font_preset' | 'palette_preset' | 'variant')[];
    readonly variantOptions: Readonly<Record<string, readonly string[]>>;
    readonly renderer: string;
    readonly fallback: string;
    readonly available: false;
}

export type CmsEnabledIntroExperience = {
    [Style in IntroStyle]: {
        readonly enabled: true;
        readonly intro_style: Style;
        readonly intro_version: 1;
        readonly intro_settings: {
            readonly opening_text: string;
            readonly open_label: string;
            readonly font_preset: Style extends 'photobooth' ? 'editorial-serif' : CmsFontPreset;
            readonly palette_preset: Style extends 'photobooth' ? 'ivory-ink' : CmsPalette;
            readonly variant: IntroVariantMap[Style];
        };
    };
}[IntroStyle];

/** Disabled is a state, not another intro design. No renderer/settings are selected. */
export type CmsIntroExperience = { readonly enabled: false } | CmsEnabledIntroExperience;

interface CmsSectionBase {
    /** Lowercase UUID, unique within its containing list; verified by PHP. */
    readonly id: string;
    readonly enabled: boolean;
}

export type CmsMessageSection = CmsSectionBase & {
    readonly type: 'welcome' | 'closing' | 'rsvp_information';
    readonly data: { readonly message: string };
};

export interface CmsEventEntry {
    readonly id: string;
    readonly kind: 'ceremony' | 'reception';
    readonly address: string;
    readonly directions: string;
    /** Null or an HTTPS link without credentials. PHP validates URLs and all bounds. */
    readonly map_url: string | null;
}

export type CmsEventSection = CmsSectionBase & {
    readonly type: 'event_details';
    readonly data: { readonly events: readonly CmsEventEntry[] };
};

export type CmsCountdownSection = CmsSectionBase & {
    readonly type: 'countdown';
    readonly data: { readonly heading: string; readonly subtitle: string };
};

/** Structural reference only; PHP media integration must verify ownership and visible publication. */
export interface CmsImageReference {
    readonly asset_id: string;
    readonly alt: string;
    readonly focal: 'center' | 'top' | 'bottom' | 'left' | 'right';
    readonly crop: 'natural' | 'portrait' | 'landscape' | 'square';
    /** Absent preserves the previous template framing. Never changes stored image bytes. */
    readonly frame?: 'fit' | 'fill';
}

export type CmsMilestoneWhen = { readonly precision: 'year'; readonly value: number } | { readonly precision: 'date'; readonly value: string };

export interface CmsStoryMilestone extends CmsSectionBase {
    readonly title: string;
    readonly description?: string;
    /** PHP verifies integer years 1–9999 or exact calendar dates YYYY-MM-DD. No implicit timezone. */
    readonly when?: CmsMilestoneWhen | null;
    readonly photo?: CmsImageReference | null;
}

export type CmsStorySection = CmsSectionBase & {
    readonly type: 'story';
    readonly data: {
        readonly heading: string;
        readonly paragraphs: readonly string[];
        readonly milestones?: readonly CmsStoryMilestone[];
    };
};

export interface CmsGalleryItem extends CmsSectionBase {
    readonly image: CmsImageReference;
    readonly caption?: string;
}

export type CmsGallerySection = CmsSectionBase & {
    readonly type: 'gallery';
    readonly data: {
        readonly heading: string;
        readonly items: readonly CmsGalleryItem[];
        readonly slideshow?: { readonly autoplay: boolean; readonly interval_seconds: 5 | 8 | 12 };
    };
};

export interface CmsEntourageGroup extends CmsSectionBase {
    readonly title: string;
    readonly people: readonly { readonly id: string; readonly name: string }[];
}

export type CmsEntourageSection = CmsSectionBase & {
    readonly type: 'entourage';
    readonly data: { readonly heading: string; readonly groups: readonly CmsEntourageGroup[] };
};

export interface CmsGuestGuideSwatch {
    readonly id: string;
    readonly label: string;
    /** Six-digit RGB hex value, validated by the PHP contract. */
    readonly color: `#${string}`;
}

export type CmsGuestGuideSection = CmsSectionBase & {
    readonly type: 'guest_guide';
    readonly data: {
        readonly attire: {
            readonly enabled: boolean;
            readonly dress_code: string;
            readonly description: string;
            readonly swatches: readonly CmsGuestGuideSwatch[];
        };
        readonly gifts: { readonly enabled: boolean; readonly heading: string; readonly message: string; readonly url: string | null };
        readonly faqs: {
            readonly enabled: boolean;
            readonly items: readonly { readonly id: string; readonly question: string; readonly answer: string }[];
        };
    };
};

export type CmsSection =
    | CmsMessageSection
    | CmsEventSection
    | CmsCountdownSection
    | CmsStorySection
    | CmsGallerySection
    | CmsEntourageSection
    | CmsGuestGuideSection;

/** Validated private content or capability projection; neither grants publication/media/RSVP authority. */
export interface CmsV2Content {
    readonly photo_slots?: {
        readonly hero: CmsImageReference | null;
        readonly details: CmsImageReference | null;
        readonly story: CmsImageReference | null;
    };
    readonly theme: { readonly palette: CmsPalette; readonly font_pair: CmsFontPreset };
    readonly experience: CmsIntroExperience;
    readonly sections: readonly CmsSection[];
}

/** Guarded owner draft saving/private preview only; public premium publishing remains unavailable. */
export interface CmsV2Document {
    readonly schema_version: 2;
    readonly template_key: 'floral-trial' | 'enchanted-editorial';
    readonly template_version: 1;
    readonly content: CmsV2Content;
}

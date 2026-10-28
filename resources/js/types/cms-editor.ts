import type { WeddingPresentation, WeddingPublicationSummary } from '@/types';
import type { CmsImageReference, CmsSection, CmsSectionType, CmsV2Document } from './cms-v2';
export type Editable<T> = T extends readonly (infer U)[] ? Editable<U>[] : T extends object ? { -readonly [K in keyof T]: Editable<T[K]> } : T;
export type EditorSection = Editable<CmsSection>;
export interface EditorImage {
    id: string;
    kind: 'image';
    mime_type: 'image/webp';
    byte_size: number;
    width: number;
    height: number;
    preview_url: string;
}
export interface EditorLimits {
    paragraphs: number;
    paragraphLength: number;
    milestones: number;
    titleLength: number;
    descriptionLength: number;
    galleryImages: number;
    entourageGroups: number;
    peoplePerGroup: number;
    people: number;
    nameLength: number;
    guestGuideSwatches: number;
    guestGuideFaqs: number;
}
export interface EditorEndpoints {
    publish?: string;
    draft: string;
    preview: string;
    images: string;
    upload: string;
    validate: string;
}
export interface EditorProps {
    publication?: WeddingPublicationSummary;
    publishingEnabled?: boolean;
    draftVersion: number;
    timeZone: string;
    initialImages: EditorImage[];
    wedding: WeddingPresentation;
    initialDocument: CmsV2Document;
    limits: EditorLimits;
    capabilities: Record<string, readonly CmsSectionType[]>;
    endpoints: EditorEndpoints;
    csrfToken: string;
    overviewUrl: string;
}
export interface PhotoChoice {
    label: string;
    choose: (image: Editable<CmsImageReference>) => void;
    excludedIds?: string[];
    multiple?: { maximum: number; choose: (images: Editable<CmsImageReference>[]) => void };
}
export interface EditorSlots {
    hero: Editable<CmsImageReference> | null;
    /** Local preview assignment only; not a persisted CMS field. */
    details: Editable<CmsImageReference> | null;
    story: Editable<CmsImageReference> | null;
}

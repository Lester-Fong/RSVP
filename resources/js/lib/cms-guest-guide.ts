import { CMS_GUEST_GUIDE_FAQ_LIMIT, CMS_GUEST_GUIDE_SWATCH_LIMIT, type CmsGuestGuideSection } from '@/types/cms-v2';

export interface GuestGuideValidationIssue {
    readonly path: string;
    readonly message: string;
}

const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const personalCredential = /(?:\/rsvp\/|(?:token|credential)=)[a-f0-9]{64}(?:[/?#&]|$)/i;

function textIssue(path: string, value: string, maximum: number, required: boolean): GuestGuideValidationIssue | null {
    if ((required && !value.trim()) || value.length > maximum || /[<>]/u.test(value)) {
        return { path, message: `Use plain text up to ${maximum} characters${required ? ', with at least one character' : ''}.` };
    }
    if (personalCredential.test(value)) {
        return { path, message: 'Personal RSVP links cannot be included in Guest Guide content.' };
    }
    return null;
}

/** Validate the new section before sending a local preview/save request; PHP remains authoritative. */
export function guestGuideValidationIssues(section: CmsGuestGuideSection, sectionIndex: number): GuestGuideValidationIssue[] {
    const base = `content.sections.${sectionIndex}.data`;
    const issues: GuestGuideValidationIssue[] = [];
    const addText = (path: string, value: string, maximum: number, required = false) => {
        const issue = textIssue(`${base}.${path}`, value, maximum, required);
        if (issue) issues.push(issue);
    };

    addText('attire.dress_code', section.data.attire.dress_code, 100, true);
    addText('attire.description', section.data.attire.description, 500);
    if (section.data.attire.swatches.length > CMS_GUEST_GUIDE_SWATCH_LIMIT) {
        issues.push({ path: `${base}.attire.swatches`, message: `Use no more than ${CMS_GUEST_GUIDE_SWATCH_LIMIT} color swatches.` });
    }
    const swatchIds = new Set<string>();
    section.data.attire.swatches.forEach((swatch, index) => {
        if (!uuid.test(swatch.id) || swatchIds.has(swatch.id)) {
            issues.push({ path: `${base}.attire.swatches.${index}.id`, message: 'Each color needs a unique valid identifier.' });
        }
        swatchIds.add(swatch.id);
        addText(`attire.swatches.${index}.label`, swatch.label, 50, true);
        if (!/^#[0-9a-f]{6}$/i.test(swatch.color)) {
            issues.push({ path: `${base}.attire.swatches.${index}.color`, message: 'Choose a six-digit color.' });
        }
    });

    addText('gifts.heading', section.data.gifts.heading, 140, true);
    addText('gifts.message', section.data.gifts.message, 1000);
    if (section.data.gifts.url) {
        try {
            const link = new URL(section.data.gifts.url);
            if (link.protocol !== 'https:' || link.username || link.password || personalCredential.test(section.data.gifts.url)) {
                issues.push({ path: `${base}.gifts.url`, message: 'Use an HTTPS gift link without personal RSVP links or credentials.' });
            }
        } catch {
            issues.push({ path: `${base}.gifts.url`, message: 'Enter a valid HTTPS gift link.' });
        }
    }

    if (section.data.faqs.items.length > CMS_GUEST_GUIDE_FAQ_LIMIT) {
        issues.push({ path: `${base}.faqs.items`, message: `Use no more than ${CMS_GUEST_GUIDE_FAQ_LIMIT} questions.` });
    }
    const faqIds = new Set<string>();
    section.data.faqs.items.forEach((item, index) => {
        if (!uuid.test(item.id) || faqIds.has(item.id)) {
            issues.push({ path: `${base}.faqs.items.${index}.id`, message: 'Each question needs a unique valid identifier.' });
        }
        faqIds.add(item.id);
        addText(`faqs.items.${index}.question`, item.question, 180, true);
        addText(`faqs.items.${index}.answer`, item.answer, 1000);
    });

    return issues;
}

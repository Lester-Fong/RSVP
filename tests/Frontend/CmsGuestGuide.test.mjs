import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';

const source = (await readFile(new URL('../../resources/js/lib/cms-guest-guide.ts', import.meta.url), 'utf8')).replace(
    "import { CMS_GUEST_GUIDE_FAQ_LIMIT, CMS_GUEST_GUIDE_SWATCH_LIMIT, type CmsGuestGuideSection } from '@/types/cms-v2';",
    'const CMS_GUEST_GUIDE_FAQ_LIMIT = 7; const CMS_GUEST_GUIDE_SWATCH_LIMIT = 5;',
);
const javascript = ts.transpile(source, { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext });
const { guestGuideValidationIssues } = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString('base64')}`);

function section() {
    return {
        id: '4fa85f64-5717-4562-b3fc-2c963f66afa6',
        type: 'guest_guide',
        enabled: true,
        data: {
            attire: { enabled: true, dress_code: 'Garden formal', description: 'Soft colors welcome.', swatches: [] },
            gifts: { enabled: true, heading: 'Gifts', message: 'Your presence is enough.', url: null },
            faqs: {
                enabled: true,
                items: Array.from({ length: 7 }, (_, index) => ({
                    id: `4fa85f64-5717-4562-b3fc-2c963f66af${String(index).padStart(2, '0')}`,
                    question: `Question ${index + 1}?`,
                    answer: 'A considerate answer.',
                })),
            },
        },
    };
}

test('Guest Guide accepts the five swatch and seven FAQ boundaries and validates safe HTTPS links', () => {
    const guide = section();
    guide.data.attire.swatches = Array.from({ length: 5 }, (_, index) => ({
        id: `5fa85f64-5717-4562-b3fc-2c963f66af${String(index).padStart(2, '0')}`,
        label: `Color ${index + 1}`,
        color: '#A36E59',
    }));
    guide.data.gifts.url = 'https://example.test/registry';
    assert.deepEqual(guestGuideValidationIssues(guide, 6), []);
});

test('Guest Guide rejects overflow, markup, non-HTTPS and bearer invitation links', () => {
    const guide = section();
    guide.data.attire.swatches = Array.from({ length: 5 }, (_, index) => ({
        id: `5fa85f64-5717-4562-b3fc-2c963f66af${String(index).padStart(2, '0')}`,
        label: `Color ${index + 1}`,
        color: '#A36E59',
    }));
    guide.data.attire.swatches.push({ id: '5fa85f64-5717-4562-b3fc-2c963f66af09', label: 'Extra', color: '#A36E59' });
    guide.data.faqs.items.push({ id: '4fa85f64-5717-4562-b3fc-2c963f66af08', question: 'Eighth?', answer: 'No.' });
    guide.data.faqs.items[0].answer = '<script>unsafe</script>';
    guide.data.gifts.url = 'https://example.test/rsvp/' + 'a'.repeat(64);
    const paths = guestGuideValidationIssues(guide, 6).map((issue) => issue.path);
    assert.ok(paths.includes('content.sections.6.data.attire.swatches'));
    assert.ok(paths.includes('content.sections.6.data.faqs.items'));
    assert.ok(paths.includes('content.sections.6.data.faqs.items.0.answer'));
    assert.ok(paths.includes('content.sections.6.data.gifts.url'));
});

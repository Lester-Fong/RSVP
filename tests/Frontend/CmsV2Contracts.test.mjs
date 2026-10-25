import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

function changedDocument(fixtures, changes) {
    const document = structuredClone(fixtures.base);
    for (const [path, value] of Object.entries(changes)) {
        const keys = path.split('.');
        const last = keys.pop();
        let parent = document;
        for (const key of keys) parent = parent[key];
        parent[last] = structuredClone(value);
    }
    return document;
}

for (const fixtureFile of ['cms-v2-contracts.json', 'cms-reusable-content.json']) {
    const fixtures = JSON.parse(await readFile(new URL(`../Fixtures/${fixtureFile}`, import.meta.url), 'utf8'));
    test(`${fixtureFile}: shared PHP fixtures satisfy TypeScript and reject structurally invalid shapes`, () => {
        const source = ["import type { CmsV2Document, CmsV2Content } from './cms-v2';"];
        for (const [index, fixture] of fixtures.accepted.entries()) {
            source.push(`const accepted${index} = ${JSON.stringify(changedDocument(fixtures, fixture.changes))} as const satisfies CmsV2Document;`);
        }
        source.push(`const projected = ${JSON.stringify(fixtures.public_content)} as const satisfies CmsV2Content;`);
        if (fixtureFile === 'cms-v2-contracts.json') {
            const enchanted = changedDocument(fixtures, {});
            enchanted.template_key = 'enchanted-editorial';
            enchanted.content.sections.splice(2, 0, {
                id: '6a77710b-45ce-4df2-96fc-a544071697dd',
                type: 'countdown',
                enabled: true,
                data: { heading: 'Our wedding day', subtitle: 'We cannot wait to celebrate together.' },
            });
            source.push(`const enchantedCountdown = ${JSON.stringify(enchanted)} as const satisfies CmsV2Document;`);
            const guide = {
                id: '6a77710b-45ce-4df2-96fc-a544071697de',
                type: 'guest_guide',
                enabled: true,
                data: {
                    attire: {
                        enabled: true,
                        dress_code: 'Garden formal',
                        description: '',
                        swatches: [{ id: '6a77710b-45ce-4df2-96fc-a544071697df', label: 'Terracotta', color: '#A36E59' }],
                    },
                    gifts: { enabled: false, heading: 'Gifts', message: '', url: null },
                    faqs: {
                        enabled: true,
                        items: [{ id: '6a77710b-45ce-4df2-96fc-a544071697e0', question: 'How do I RSVP?', answer: 'Use your personal invitation.' }],
                    },
                },
            };
            enchanted.content.sections.splice(6, 0, guide);
            source.push(`const enchantedGuestGuide = ${JSON.stringify(enchanted)} as const satisfies CmsV2Document;`);
            source.push(`// @ts-expect-error countdown accepts no date or timezone fields`);
            source.push(
                `const invalidCountdown = ${JSON.stringify({ ...enchanted, content: { ...enchanted.content, sections: enchanted.content.sections.map((section) => (section.type === 'countdown' ? { ...section, data: { ...section.data, wedding_date: '2027-02-20' } } : section)) } })} as const satisfies CmsV2Document;`,
            );
        }
        for (const [index, fixture] of fixtures.rejected.filter((fixture) => fixture.type_error).entries()) {
            source.push(`// @ts-expect-error ${fixture.name}`);
            source.push(`const rejected${index} = ${JSON.stringify(changedDocument(fixtures, fixture.changes))} as const satisfies CmsV2Document;`);
        }

        const filename = resolve('resources/js/types/__cms_v2_contract_test__.ts');
        const text = source.join('\n');
        const options = {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.ESNext,
            moduleResolution: ts.ModuleResolutionKind.Bundler,
            strict: true,
            noEmit: true,
            skipLibCheck: true,
            types: [],
        };
        const host = ts.createCompilerHost(options);
        const originalGetSourceFile = host.getSourceFile.bind(host);
        host.getSourceFile = (path, languageVersion, onError, shouldCreateNewSourceFile) =>
            resolve(path) === filename
                ? ts.createSourceFile(filename, text, languageVersion, true)
                : originalGetSourceFile(path, languageVersion, onError, shouldCreateNewSourceFile);
        const program = ts.createProgram([filename], options, host);
        const diagnostics = ts.getPreEmitDiagnostics(program);

        assert.equal(
            diagnostics.length,
            0,
            ts.formatDiagnosticsWithColorAndContext(diagnostics, {
                getCanonicalFileName: (path) => path,
                getCurrentDirectory: () => process.cwd(),
                getNewLine: () => '\n',
            }),
        );
    });
}

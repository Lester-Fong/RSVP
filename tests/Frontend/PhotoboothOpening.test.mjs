import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';

const source = await readFile(new URL('../../resources/js/lib/photobooth-opening.ts', import.meta.url), 'utf8');
const javascript = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext } }).outputText;
const { photoboothPhotos, openingSessionKey } = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString('base64')}`);

test('Photo strip uses only visible projected photos, hero first, deduplicated in stable order', () => {
    const photo = (url) => ({ url });
    const presentation = {
        hero: { photo: photo('/visible-hero') },
        details: { photo: photo('/hidden-details') },
        sections: [
            { type: 'gallery', enabled: false, items: [{ photo: photo('/hidden-gallery') }] },
            {
                type: 'story',
                enabled: true,
                photo: photo('/visible-story'),
                milestones: [{ photo: photo('/visible-hero') }, { photo: photo('/visible-milestone') }],
            },
        ],
    };
    assert.deepEqual(
        photoboothPhotos(presentation).map((value) => value.url),
        ['/visible-hero', '/visible-story', '/visible-milestone'],
    );
});

test('Sparse strips use fewer distinct photographs and empty strips do not invent images', () => {
    assert.deepEqual(photoboothPhotos({ hero: { photo: null }, sections: [] }), []);
    assert.deepEqual(
        photoboothPhotos({ hero: { photo: { url: '/one' } }, sections: [] }).map((value) => value.url),
        ['/one'],
    );
    assert.notEqual(openingSessionKey('wedding-one'), openingSessionKey('wedding-two'));
});

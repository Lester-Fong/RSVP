import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import ts from 'typescript';
import { createSSRApp } from 'vue';

const filename = resolve('resources/js/components/wedding/ModernClassicInvitation.vue');
const { descriptor } = parse(await readFile(filename, 'utf8'));
const compiled = compileScript(descriptor, { id: 'invitation-test', inlineTemplate: true });
const javascript = ts.transpileModule(compiled.content, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
}).outputText;
const vueUrl = pathToFileURL(resolve('node_modules/vue/index.mjs')).href;
const { default: Invitation } = await import(
    `data:text/javascript;base64,${Buffer.from(javascript.replace(/from ["']vue["']/g, `from ${JSON.stringify(vueUrl)}`)).toString('base64')}`
);

const wedding = {
    partner_one_name: 'Alex <script>alert(1)</script>',
    partner_two_name: 'Jamie',
    wedding_date: '2027-05-12',
    wedding_time: '16:30',
    ceremony_venue: 'Ceremony <img onerror=alert(1)>',
    reception_venue: 'Intramuros Hall',
};
const content = () => ({
    introduction_message: 'Welcome\nOur family & friends',
    closing_message: 'See you soon',
    sections: { ceremony: true, reception: true, closing: true },
});
const render = (customization, facts = wedding) => renderToString(createSSRApp(Invitation, { wedding: facts, customization }));

test('optional hero photo shares the renderer without hiding names or existing sections', async () => {
    const html = await renderToString(createSSRApp(Invitation, { wedding, customization: content(), heroPhoto: { url: '/private-photo/123' } }));
    assert.match(html, /src="\/private-photo\/123"/);
    assert.match(html, /invitation-hero-photo/);
    assert.match(html, /The wedding of/);
    assert.match(html, /Intramuros Hall/);
    assert.ok(html.indexOf('invitation-hero-photo') < html.indexOf('invitation-details'));
    const noPhoto = await render(content());
    assert.doesNotMatch(noPhoto, /<img/);
});

test('hidden sections disappear while names, heading, and date remain; toggling restores stored venues', async () => {
    const customization = content();
    customization.sections = { ceremony: false, reception: false, closing: false };
    const hidden = await render(customization);
    assert.doesNotMatch(hidden, /invitation-venue[" ]|Intramuros Hall|invitation-footer|See you soon|Venue details will/);
    assert.match(hidden, /The wedding of/);
    assert.match(hidden, /Jamie/);
    assert.match(hidden, /May 12, 2027/);

    customization.sections = { ceremony: true, reception: true, closing: true };
    const restored = await render(customization);
    assert.match(restored, /Intramuros Hall/);
    assert.match(restored, /See you soon/);
    assert.match(restored, /Ceremony &lt;img/);
});

test('each venue toggle operates independently', async () => {
    const customization = content();
    customization.sections.reception = false;
    const html = await render(customization);
    assert.match(html, /Ceremony &lt;img/);
    assert.doesNotMatch(html, /Intramuros Hall|>Reception</);
});

test('messages and wedding facts are escaped and multiline messages survive rendering', async () => {
    const customization = content();
    customization.introduction_message = '<script>alert(1)</script>\nWelcome & enjoy';
    customization.closing_message = '<img onerror=alert(1)>';
    const html = await render(customization);
    assert.doesNotMatch(html, /<script>|<img/);
    assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;\nWelcome &amp; enjoy/);
    assert.match(html, /&lt;img onerror=alert\(1\)&gt;/);
});

test('missing wedding details retain the existing fallback copy', async () => {
    const html = await render(content(), { ...wedding, wedding_date: null, wedding_time: null, ceremony_venue: null, reception_venue: null });
    assert.match(html, /Date and time details will be added soon/);
    assert.match(html, /Venue details will be added soon/);
});

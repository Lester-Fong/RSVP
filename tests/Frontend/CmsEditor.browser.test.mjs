import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import ts from 'typescript';

const waypointScript = ts.transpile(
    (await readFile(new URL('../../resources/js/lib/wedding-waypoints.ts', import.meta.url), 'utf8')).replaceAll('export ', ''),
    { target: ts.ScriptTarget.ES2022 },
);
async function waypoints(frame = preview().locator('body')) {
    return frame.evaluate((body, script) => {
        const discover = new Function(script + '; return discoverWeddingWaypoints;')();
        return discover(body).map(({ id, kind, section }) => ({ id, kind, section }));
    }, waypointScript);
}

const enabled = Boolean(process.env.QA_CMS_EDITOR_INFO && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let info, browser, context, page, artifacts;
const errors = [];
before(async () => {
    if (!enabled) return;
    info = JSON.parse(await readFile(process.env.QA_CMS_EDITOR_INFO, 'utf8'));
    assert.match(info.origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    assert.match(info.root, /rsvp-cms-editor-/);
    artifacts = join(info.root, 'qa');
    await mkdir(artifacts, { recursive: true });
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
beforeEach(async () => {
    if (!enabled) return;
    errors.length = 0;
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(info.origin + '/login');
    await page.getByLabel('Email address').fill(info.owner.email);
    await page.getByLabel('Password', { exact: true }).fill(info.owner.password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
    await page.goto(info.previewUrl);
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await page.getByText('Preview content checked', { exact: true }).waitFor();
});
afterEach(async () => {
    if (!enabled) return;
    await context.close();
    assert.deepEqual(errors, []);
});
after(async () => {
    await browser?.close();
});
const run = (name, fn) => test(name, { skip: !enabled }, fn);
const editor = () => page.getByRole('region', { name: 'Website editing controls' });
const preview = () => page.frameLocator('iframe[title="Wedding website preview"]');
async function section(name) {
    await page.getByRole('navigation', { name: 'Editor sections' }).getByRole('button', { name, exact: true }).click();
}

run('DEL03a2 fresh gallery and picker start empty without demonstration assets', async () => {
    const source = await (await context.request.get(info.previewUrl + '/draft')).json();
    assert.deepEqual(source.document.content.sections.find((s) => s.type === 'gallery').data.items, []);
    assert.equal(source.draft_version, 0);
    assert.deepEqual((await (await context.request.get(info.previewUrl + '/images')).json()).images, []);
    await section('Gallery');
    await page.getByText('Make this space yours.', { exact: false }).waitFor();
    assert.equal(await preview().locator('.ee-gallery-slide').count(), 0);
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.screenshot({ path: join(artifacts, `del03a2-empty-editor-${width}.png`), fullPage: true });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.getByRole('dialog').getByText('No photographs yet.', { exact: false }).waitFor();
    assert.equal(
        await page
            .getByRole('dialog')
            .getByRole('button', { name: /^Use photograph/ })
            .count(),
        0,
    );
    await page.screenshot({ path: join(artifacts, 'del03a2-empty-picker.png') });
});

run('DEL04.1 Countdown starts after details, saves and reloads only editable copy, and preserves hidden preferences', async () => {
    const initial = await (await context.request.get(info.previewUrl + '/draft')).json();
    const types = initial.document.content.sections.map((entry) => entry.type);
    assert.deepEqual(types.slice(0, 4), ['welcome', 'event_details', 'countdown', 'story']);
    assert.equal(initial.document.content.sections[2].enabled, true);
    assert.deepEqual(Object.keys(initial.document.content.sections[2].data), ['heading', 'subtitle']);
    const sectionButtons = page.getByRole('navigation', { name: 'Editor sections' }).getByRole('button');
    assert.deepEqual(
        (await sectionButtons.allTextContents()).slice(0, 4).map((label) => label.trim()),
        ['Welcome', 'Wedding details', 'Countdown', 'Our Story'],
    );
    assert.equal(await page.getByRole('link', { name: 'Back to wedding', exact: true }).count(), 1);

    await section('Countdown');
    const editing = page.getByRole('region', { name: 'Countdown editing' });
    await editing.getByLabel('Countdown heading', { exact: true }).fill('A day until forever');
    await editing.getByLabel('Countdown subtitle', { exact: true }).fill('Our favorite day is getting closer.');
    await preview().getByRole('heading', { name: 'A day until forever', exact: true }).waitFor();
    await preview().locator('#countdown .ee-countdown-units').waitFor();
    assert.equal(await preview().locator('#countdown [data-landing-id="section:countdown"]').count(), 1);

    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText(/Draft saved privately\. Your published website has not changed\./).waitFor();
    const saved = await (await context.request.get(info.previewUrl + '/draft')).json();
    assert.deepEqual(saved.document.content.sections[2].data, { heading: 'A day until forever', subtitle: 'Our favorite day is getting closer.' });
    assert.equal(saved.document.content.sections[2].enabled, true);
    await page.reload();
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    await section('Countdown');
    await page.getByLabel('Countdown heading', { exact: true }).waitFor();
    assert.equal(await page.getByLabel('Countdown heading', { exact: true }).inputValue(), 'A day until forever');

    await page.getByRole('region', { name: 'Countdown editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText(/Draft saved privately\. Your published website has not changed\./).waitFor();
    const hidden = await (await context.request.get(info.previewUrl + '/draft')).json();
    assert.equal(hidden.document.content.sections[2].enabled, false);
    assert.equal(hidden.document.content.sections[2].data.heading, 'A day until forever');
    assert.equal(await preview().locator('#countdown').count(), 0);
});

run('DEL04.2 Guest Guide can be explicitly added to an older draft, edited, reordered, saved, and hidden without losing content', async () => {
    const initial = await (await context.request.get(info.previewUrl + '/draft')).json();
    const oldDocument = structuredClone(initial.document);
    oldDocument.content.sections = oldDocument.content.sections.filter((entry) => entry.type !== 'guest_guide');
    const legacySave = await page.evaluate(
        async ({ url, payload }) => {
            const xsrf = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
            const response = await fetch(url, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.slice('XSRF-TOKEN='.length)) } : {}),
                },
                body: JSON.stringify(payload),
            });
            return response.status;
        },
        { url: info.previewUrl + '/draft', payload: { document: oldDocument, draft_version: initial.draft_version } },
    );
    assert.equal(legacySave, 200);
    await page.reload();
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    assert.equal(
        await page.getByRole('navigation', { name: 'Editor sections' }).getByRole('button', { name: 'Guest Guide', exact: true }).count(),
        0,
    );
    await page.getByRole('button', { name: 'Add Guest Guide', exact: true }).click();

    const region = page.getByRole('region', { name: 'Guest Guide editing' });
    assert.equal(await region.getByLabel('Show Attire Guide').isChecked(), false);
    assert.equal(await region.getByLabel('Show Gift Guide').isChecked(), false);
    assert.equal(await region.getByLabel('Show frequently asked questions').isChecked(), true);
    assert.deepEqual(await region.locator('.cms-guide-faq-editor > summary').allTextContents(), [
        '1. Are my kids welcome?Edit',
        '2. Can I bring a plus one?Edit',
        '3. How do I RSVP?Edit',
    ]);

    await region.getByLabel('Show Attire Guide').check();
    await region.getByLabel('Dress code', { exact: true }).fill('Garden formal');
    await region.getByLabel('Short description', { exact: true }).fill('Soft colors are welcome.');
    for (let index = 0; index < 5; index++) await region.getByRole('button', { name: 'Add color', exact: true }).click();
    assert.equal(await region.locator('input[type="color"]').count(), 5);
    assert.equal(await region.getByRole('button', { name: 'Add color', exact: true }).isDisabled(), true);
    await region.getByLabel('Color label').nth(0).fill('Terracotta');
    await region.locator('input[type="color"]').nth(0).fill('#a36e59');

    await region.getByRole('button', { name: 'Add question', exact: true }).click();
    await region.getByRole('button', { name: 'Add question', exact: true }).click();
    await region.getByRole('button', { name: 'Add question', exact: true }).click();
    await region.getByRole('button', { name: 'Add question', exact: true }).click();
    assert.equal(await region.locator('.cms-guide-faq-editor').count(), 7);
    assert.equal(await region.getByRole('button', { name: 'Add question', exact: true }).isDisabled(), true);
    await region.locator('.cms-guide-faq-editor').first().locator('summary').click();
    await region.getByRole('button', { name: 'Move question 1 down', exact: true }).click();
    await region.getByRole('button', { name: 'Remove question 2', exact: true }).click();
    assert.equal(await region.locator('.cms-guide-faq-editor').count(), 6);
    await region.getByRole('button', { name: 'Add question', exact: true }).click();
    assert.equal(await region.locator('.cms-guide-faq-editor').count(), 7);

    await region.getByLabel('Show Gift Guide').check();
    await region.getByText('Edit gift details', { exact: true }).click();
    await region.getByLabel('Heading', { exact: true }).fill('A note on gifts');
    await region.getByLabel('Personalized message', { exact: true }).fill('Your company is a gift; a registry is optional.');
    await region.getByLabel('Optional HTTPS registry or gift link').fill('https://example.test/registry');
    await preview().locator('#guest-guide').waitFor();
    await preview().getByRole('heading', { name: 'Garden formal', exact: true }).waitFor();
    await preview().locator('#guest-guide .ee-guide-faqs details').first().locator('summary').press('Enter');
    assert.equal(await preview().locator('#guest-guide [data-landing-id="section:guest-guide"]').count(), 1);

    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        assert.ok(
            await preview()
                .locator('.ee-guide-swatches')
                .evaluate((list) => {
                    const center = list.getBoundingClientRect().left + list.getBoundingClientRect().width / 2;
                    const rows = new Map();
                    for (const item of list.children) {
                        const box = item.getBoundingClientRect();
                        const key = Math.round(box.top);
                        const row = rows.get(key) ?? { left: box.left, right: box.right };
                        row.left = Math.min(row.left, box.left);
                        row.right = Math.max(row.right, box.right);
                        rows.set(key, row);
                    }
                    return rows.size > 0 && [...rows.values()].every((row) => Math.abs((row.left + row.right) / 2 - center) < 3);
                }),
        );
        await page.screenshot({ path: join(artifacts, `del04-2-guide-editor-${width}.png`), fullPage: true });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText(/Draft saved privately/).waitFor();
    let saved = await (await context.request.get(info.previewUrl + '/draft')).json();
    const savedGuide = saved.document.content.sections.find((entry) => entry.type === 'guest_guide');
    assert.deepEqual(
        saved.document.content.sections.slice(5, 8).map((entry) => entry.type),
        ['entourage', 'guest_guide', 'rsvp_information'],
    );
    assert.equal(savedGuide.data.attire.swatches.length, 5);
    assert.equal(savedGuide.data.faqs.items.length, 7);
    assert.notEqual(savedGuide.data.faqs.items[0].question, 'Are my kids welcome?');
    assert.equal(savedGuide.data.gifts.url, 'https://example.test/registry');

    await page.reload();
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await section('Guest Guide');
    assert.equal(await region.getByLabel('Dress code', { exact: true }).inputValue(), 'Garden formal');
    await region.getByLabel('Show frequently asked questions').uncheck();
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText(/Draft saved privately/).waitFor();
    saved = await (await context.request.get(info.previewUrl + '/draft')).json();
    const hiddenFaqs = saved.document.content.sections.find((entry) => entry.type === 'guest_guide').data.faqs;
    assert.equal(hiddenFaqs.enabled, false);
    assert.equal(hiddenFaqs.items.length, 7);
    assert.equal(await preview().locator('#guest-guide .ee-guide-faqs').count(), 0);
    assert.equal(await preview().locator('#guest-guide').count(), 1);

    await region.getByLabel('Show section', { exact: true }).uncheck();
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText(/Draft saved privately/).waitFor();
    const hiddenGuide = (await (await context.request.get(info.previewUrl + '/draft')).json()).document.content.sections.find(
        (entry) => entry.type === 'guest_guide',
    );
    assert.equal(hiddenGuide.enabled, false);
    assert.equal(hiddenGuide.data.attire.swatches[0].label, 'Terracotta');
    assert.equal(await preview().locator('#guest-guide').count(), 0);
});

run('DEL02e checked empty sections show editable headings, independent visibility and navigation without Story ampersand', async () => {
    for (const [name, id, field] of [
        ['Our Story', 'our-story', 'Story heading'],
        ['Gallery', 'our-photos', 'Gallery heading'],
        ['Entourage', 'entourage', 'Entourage heading'],
    ]) {
        await section(name);
        await page.getByLabel(field, { exact: true }).fill('Our ' + name + ' heading');
        await preview()
            .locator('#' + id)
            .getByRole('heading', { name: 'Our ' + name + ' heading', exact: true })
            .waitFor();
        assert.ok(
            await preview()
                .locator('#' + id)
                .evaluate((el) => el.getBoundingClientRect().top >= 0 && el.getBoundingClientRect().top < innerHeight),
        );
        const region = editor().getByRole('region', { name: name + ' editing' });
        await region.getByLabel('Show section', { exact: true }).uncheck();
        assert.equal(
            await preview()
                .locator('#' + id)
                .count(),
            0,
        );
        await region.getByLabel('Show section', { exact: true }).check();
        await preview()
            .locator('#' + id)
            .waitFor();
        assert.equal(await page.getByLabel(field, { exact: true }).inputValue(), 'Our ' + name + ' heading');
    }
    assert.equal(await preview().locator('.ee-story-flourish').count(), 0);
    assert.equal((await preview().locator('.ee-ampersand').textContent()).trim(), '&');
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        if (width === 390) await page.getByRole('button', { name: 'View website preview', exact: true }).click();
        for (const id of ['our-story', 'our-photos', 'entourage']) {
            await preview()
                .locator('#' + id)
                .scrollIntoViewIfNeeded();
            assert.ok(
                await preview()
                    .locator('html')
                    .evaluate((el) => el.scrollWidth <= el.clientWidth + 1),
            );
            await page.screenshot({ path: join(artifacts, `del02e-empty-${id}-${width}.png`), fullPage: true });
        }
    }
});

run('DEL02e one photo is committed once despite repeated events and reopening excludes it', async () => {
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    const { image } = await uploadPhoto();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Add selected photographs (1)', exact: true })
        .evaluate((el) => {
            el.click();
            el.click();
        });
    assert.equal(await page.getByLabel('Caption 1', { exact: true }).count(), 1);
    assert.equal(await page.getByLabel('Caption 2', { exact: true }).count(), 0);
    assert.equal(await preview().locator('.ee-gallery-slide').count(), 1);
    await page.getByLabel('Caption 1', { exact: true }).fill('One photo, once.');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    const choice = page
        .getByRole('dialog')
        .locator('button')
        .filter({ has: page.locator(`img[src="${image.preview_url}"]`) });
    await choice.waitFor();
    assert.equal(await choice.isDisabled(), true);
    await page.keyboard.press('Escape');
    await section('Welcome');
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    await page
        .getByRole('dialog')
        .locator('button')
        .filter({ has: page.locator(`img[src="${image.preview_url}"]`) })
        .click();
    assert.equal(await preview().locator('.ee-hero-main img').getAttribute('src'), image.preview_url);
});

run('DEL02e multiple uploads retain successes, retry only failures and select existing photos with keyboard', async () => {
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.waitForFunction(() => !document.querySelector('[role=dialog] input[type=file]').disabled);
    let attempts = 0;
    await page.route('**/cms-preview/images', (route) => {
        if (route.request().method() === 'POST' && ++attempts === 2)
            return route.fulfill({
                status: 503,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Synthetic interrupted upload. Retry.' }),
            });
        return route.continue();
    });
    const buffer = await png(700, 900);
    await page
        .getByRole('dialog')
        .locator('input[type=file]')
        .setInputFiles([1, 2, 3].map((n) => ({ name: `synthetic-${n}.png`, mimeType: 'image/png', buffer })));
    await page.getByRole('dialog').getByText('3 of 3 uploads finished.', { exact: false }).waitFor();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (2)', exact: true }).waitFor();
    await page.getByRole('dialog').getByRole('button', { name: 'Retry failed uploads', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (3)', exact: true }).click();
    assert.equal(attempts, 4);
    assert.equal(await page.getByLabel(/^Caption /).count(), 3);
    await page.getByRole('button', { name: 'Remove gallery photograph', exact: true }).first().click();
    await page.getByRole('dialog').getByRole('button', { name: 'Remove gallery photograph', exact: true }).click();
    await page.getByRole('button', { name: 'Remove gallery photograph', exact: true }).first().click();
    await page.getByRole('dialog').getByRole('button', { name: 'Remove gallery photograph', exact: true }).click();
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.waitForFunction(() => !document.querySelector('[role=dialog] input[type=file]').disabled);
    const options = page
        .getByRole('dialog')
        .getByRole('button', { name: /^Use photograph / })
        .filter({ hasNot: page.getByText('Already in gallery', { exact: true }) });
    await options.first().focus();
    await page.keyboard.press('Space');
    await options.nth(1).focus();
    await page.keyboard.press('Space');
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (2)', exact: true }).click();
    assert.equal(await page.getByLabel(/^Caption /).count(), 3);
    await page.getByRole('button', { name: 'Move photo up', exact: true }).nth(2).click();
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.screenshot({ path: join(artifacts, `del02e-gallery-${width}.png`), fullPage: true });
    }
});

run('DEL02e ten-photo limit rejects eleven files before upload and prevents excess selections', async () => {
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.waitForFunction(() => !document.querySelector('[role=dialog] input[type=file]').disabled);
    let posts = 0;
    page.on('request', (r) => {
        if (r.url().endsWith('/cms-preview/images') && r.method() === 'POST') posts++;
    });
    const buffer = await png(32, 48);
    const files = Array.from({ length: 11 }, (_, n) => ({ name: `synthetic-limit-${n}.png`, mimeType: 'image/png', buffer }));
    await page.getByRole('dialog').locator('input[type=file]').setInputFiles(files);
    await page.getByRole('dialog').getByText('You can add 10 more photographs. Choose fewer files; nothing was uploaded.', { exact: true }).waitFor();
    assert.equal(posts, 0);
    await page.getByRole('dialog').locator('input[type=file]').setInputFiles(files.slice(0, 10));
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (10)', exact: true }).waitFor();
    await page.getByRole('dialog').locator('button[aria-pressed="false"]:enabled').first().click();
    await page.getByRole('dialog').getByText('Choose at most 10 more photographs. Remove a selection first.', { exact: true }).waitFor();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (10)', exact: true }).click();
    assert.equal(posts, 10);
    assert.equal(await page.getByLabel(/^Caption /).count(), 10);
    assert.equal(await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).isDisabled(), true);
    await page.getByText('Preview content checked', { exact: true }).waitFor();
});

run('DEL02e responsive multi-photo dialog retains selections with keyboard and light/dark contrast', async () => {
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.waitForFunction(() => !document.querySelector('[role=dialog] input[type=file]').disabled);
    const options = page.getByRole('dialog').getByRole('button', { name: /^Use photograph / });
    await options.first().focus();
    await page.keyboard.press('Space');
    await options.nth(1).focus();
    await page.keyboard.press('Space');
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const dark of [false, true]) {
            await page.evaluate((value) => document.documentElement.classList.toggle('dark', value), dark);
            const dialog = page.getByRole('dialog');
            await dialog.locator('img').evaluateAll(async (images) => {
                await Promise.all(
                    images.map(async (image) => {
                        image.loading = 'eager';
                        await image.decode();
                    }),
                );
            });
            assert.ok(
                await dialog.evaluate((el) => {
                    const box = el.getBoundingClientRect();
                    return box.left >= 0 && box.right <= innerWidth + 1 && el.scrollWidth <= el.clientWidth + 1;
                }),
            );
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            const confirm = dialog.getByRole('button', { name: 'Add selected photographs (2)', exact: true });
            await confirm.scrollIntoViewIfNeeded();
            await confirm.focus();
            assert.ok(
                await confirm.evaluate((el) => {
                    const box = el.getBoundingClientRect();
                    return box.top >= 0 && box.bottom <= innerHeight;
                }),
            );
            await page.screenshot({ path: join(artifacts, `del02e-picker-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
            await dialog.locator('input[type=file]').evaluate((el) => el.closest('label').scrollIntoView({ block: 'nearest' }));
            await page.screenshot({ path: join(artifacts, `del02e-picker-heading-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
        }
    }
    await page.keyboard.press('Escape');
    assert.equal(await page.getByLabel(/^Caption /).count(), 0);
    assert.equal(
        await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).evaluate((el) => el === document.activeElement),
        true,
    );
});
async function png(width = 600, height = 800, color = '#966e4b') {
    return Buffer.from(
        await page.evaluate(
            ({ width, height, color }) => {
                const c = document.createElement('canvas');
                c.width = width;
                c.height = height;
                const ctx = c.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, width, height);
                gradient.addColorStop(0, color);
                gradient.addColorStop(1, '#e5ceb0');
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, width, height);
                return c.toDataURL('image/png').split(',')[1];
            },
            { width, height, color },
        ),
        'base64',
    );
}
async function uploadPhoto(width = 600, height = 800) {
    await page.waitForFunction(() => {
        const input = document.querySelector('[role=dialog] input[type=file]');
        return input && !input.disabled;
    });
    const response = page.waitForResponse((r) => r.url().endsWith('/cms-preview/images') && r.request().method() === 'POST');
    await page
        .getByRole('dialog')
        .locator('input[type=file]')
        .setInputFiles({ name: 'synthetic-phone.png', mimeType: 'image/png', buffer: await png(width, height) });
    const res = await response;
    assert.equal(res.status(), 201);
    const { image } = await res.json();
    assert.equal(image.mime_type, 'image/webp');
    assert.ok(!('path' in image));
    const choice = page
        .getByRole('dialog')
        .locator('button')
        .filter({ has: page.locator('img[src="' + image.preview_url + '"]') });
    await choice.waitFor();
    return { image, choice };
}

run('actual authenticated multipart upload, private resolution and live hero assignment', async () => {
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    const { image, choice } = await uploadPhoto();
    await choice.click();
    const hero = preview().locator('.ee-hero-main img');
    await hero.waitFor();
    assert.equal(await hero.getAttribute('src'), image.preview_url);
    await hero.evaluate((img) => img.decode());
    assert.ok(await hero.evaluate((img) => img.naturalWidth > 0));
    assert.equal((await context.request.get(info.origin + image.preview_url)).headers()['cache-control'], 'no-store, private');
    const anon = await browser.newContext();
    const forbidden = await anon.request.get(info.origin + image.preview_url, { headers: { Accept: 'application/json' } });
    assert.equal(forbidden.status(), 401);
    await anon.close();
    const csrf = await context.request.post(info.previewUrl + '/validate', { data: {} });
    assert.equal(csrf.status(), 419);
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    await page.screenshot({ path: join(artifacts, 'desktop-hero.png'), fullPage: true });
});

run('new and replacement photos use Natural crop across editor slots and collections', async () => {
    const welcome = editor().getByRole('region', { name: 'Welcome editing' });
    await welcome.getByRole('button', { name: 'Choose photo' }).click();
    const first = await uploadPhoto();
    await first.choice.click();
    assert.equal(await welcome.getByLabel('Photo crop').inputValue(), 'natural');
    await welcome.getByLabel('Photo crop').selectOption('portrait');
    await welcome.getByLabel('Photo frame').selectOption('fit');
    await welcome.getByRole('button', { name: 'Change photo' }).click();
    const replacement = await uploadPhoto(900, 600);
    await replacement.choice.click();
    assert.equal(await welcome.getByLabel('Photo crop').inputValue(), 'natural');
    assert.equal(await welcome.getByLabel('Photo frame').inputValue(), 'fit');

    for (const [sectionName, regionName] of [
        ['Wedding details', 'Wedding details editing'],
        ['Our Story', 'Our Story editing'],
    ]) {
        await section(sectionName);
        const region = editor().getByRole('region', { name: regionName });
        await region.getByRole('button', { name: 'Choose photo' }).first().click();
        await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
        assert.equal(await region.getByLabel('Photo crop').first().inputValue(), 'natural');
    }
    await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
    const milestone = editor().getByRole('group', { name: 'Timeline milestone image' });
    await milestone.getByRole('button', { name: 'Choose photo' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    assert.equal(await milestone.getByLabel('Photo crop').inputValue(), 'natural');

    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    assert.equal(await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Photo crop').inputValue(), 'natural');
});

run('story edits, milestones and optional visibility update actual renderer without capability controls', async () => {
    await section('Our Story');
    await page.getByLabel('Story heading', { exact: true }).fill('The day our paths met');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1').fill('A short story that belongs only to us.');
    await preview().getByRole('heading', { name: 'The day our paths met', exact: true }).waitFor();
    await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
    await page.getByLabel('Milestone title', { exact: true }).fill('Our first adventure');
    await page.getByLabel('Milestone date precision').selectOption('year');
    await page.getByLabel('Milestone year').fill('2020');
    await preview().getByRole('heading', { name: 'Our first adventure', exact: true }).waitFor();
    await preview().locator('.ee-timeline-entry time').getByText('2020', { exact: true }).waitFor();
    await editor().getByRole('region', { name: 'Our Story editing' }).getByLabel('Show section').uncheck();
    assert.equal(await preview().locator('#our-story').count(), 0);
    assert.equal(await page.getByLabel('Story paragraph 1').inputValue(), 'A short story that belongs only to us.');
    await editor().getByRole('region', { name: 'Our Story editing' }).getByLabel('Show section').check();
    assert.equal(await page.getByLabel('Preview section support').count(), 0);
    await preview().getByText('A short story that belongs only to us.', { exact: true }).waitFor();
    await section('Welcome');
    await section('Our Story');
    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'The day our paths met');
    await page.getByText('Preview content checked', { exact: true }).waitFor();
});

run('gallery real uploads, ordering, captions and safe section-only removal', async () => {
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await uploadPhoto(900, 600);
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    await page.getByLabel('Caption 1', { exact: true }).fill('First photograph');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    const b = await uploadPhoto(600, 900);
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    await page.getByLabel('Caption 2', { exact: true }).fill('Second photograph');
    await page.getByRole('button', { name: 'Move photo up', exact: true }).nth(1).click();
    assert.equal(await page.getByLabel('Caption 1', { exact: true }).inputValue(), 'Second photograph');
    assert.equal(await preview().locator('.ee-gallery-slide').first().locator('img').getAttribute('src'), b.image.preview_url);
    await page.getByRole('button', { name: 'Remove gallery photograph', exact: true }).first().click();
    await page.getByRole('dialog').getByRole('button', { name: 'Remove gallery photograph', exact: true }).click();
    assert.equal((await context.request.get(info.origin + b.image.preview_url)).status(), 200);
    await page.getByText('Preview content checked', { exact: true }).waitFor();
});

run('repeatable entourage editing and explicit removal confirmation', async () => {
    await section('Entourage');
    await page.getByRole('button', { name: 'Add role group', exact: true }).click();
    await page.getByLabel('Role group title', { exact: true }).fill('Our favourite people');
    await page.getByLabel('Add a name', { exact: true }).fill('A very long name '.repeat(7));
    await page.getByLabel('Add a name', { exact: true }).press('Enter');
    await page.getByLabel('Add a name', { exact: true }).fill('Jordan');
    await page.getByRole('button', { name: 'Add person', exact: true }).click();
    await page.getByRole('button', { name: 'Reorder person 2: Jordan', exact: true }).press('Alt+ArrowUp');
    assert.equal(await page.getByLabel('Person 1', { exact: true }).inputValue(), 'Jordan');
    await preview().getByText('Jordan', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Remove role group', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Keep content', exact: true }).click();
    assert.equal(await page.getByLabel('Role group title', { exact: true }).inputValue(), 'Our favourite people');
    await page.getByRole('button', { name: 'Remove role group', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Remove role group', exact: true }).click();
    assert.equal(await page.getByLabel('Role group title', { exact: true }).count(), 0);
    await page.waitForFunction(() => document.activeElement?.closest('label')?.textContent?.includes('Entourage heading'));
});

run('invalid upload and real network failure expose errors then allow successful retry', async () => {
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    await page.waitForFunction(() => !document.querySelector('[role=dialog] input[type=file]').disabled);
    let response = page.waitForResponse((r) => r.url().endsWith('/cms-preview/images') && r.request().method() === 'POST');
    await page
        .getByRole('dialog')
        .locator('input[type=file]')
        .setInputFiles({ name: 'corrupt.png', mimeType: 'image/png', buffer: Buffer.from('not image') });
    assert.equal((await response).status(), 422);
    await page.getByRole('dialog').getByRole('alert').waitFor();
    await page.route('**/cms-preview/images', (route) => (route.request().method() === 'POST' ? route.abort('failed') : route.continue()), {
        times: 1,
    });
    await page
        .getByRole('dialog')
        .locator('input[type=file]')
        .setInputFiles({ name: 'retry.png', mimeType: 'image/png', buffer: await png() });
    await page.getByText(/Upload interrupted/).waitFor();
    await page.unroute('**/cms-preview/images');
    const success = await uploadPhoto();
    await success.choice.click();
    await preview().locator('.ee-hero-main img').waitFor();
});

run('keyboard photo dialog traps/restores focus, Escape closes, and presets remain bounded', async () => {
    const trigger = page.getByRole('button', { name: 'Choose photo', exact: true });
    await trigger.focus();
    await page.keyboard.press('Enter');
    await page.getByRole('dialog').waitFor();
    assert.equal(await page.locator('[data-state="open"].backdrop-blur-sm').evaluate((e) => getComputedStyle(e).animationName), 'none');
    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => Boolean(document.activeElement?.closest('[role=dialog]'))), true);
    await page.keyboard.press('Escape');
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
    assert.equal(await trigger.evaluate((e) => e === document.activeElement), true);
    const { outlineStyle, boxShadow } = await trigger.evaluate((e) => ({
        outlineStyle: getComputedStyle(e).outlineStyle,
        boxShadow: getComputedStyle(e).boxShadow,
    }));
    assert.ok(outlineStyle !== 'none' || boxShadow !== 'none');
});

run('desktop/tablet/mobile light and dark layouts contain content with visible preview and no overflow', async () => {
    await section('Our Story');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1').fill('An ordinary story, beautifully told.');
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const dark of [false, true]) {
            await page.evaluate((value) => document.documentElement.classList.toggle('dark', value), dark);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await page.screenshot({ path: join(artifacts, 'editor-' + width + '-' + (dark ? 'dark' : 'light') + '.png'), fullPage: true });
        }
        if (width === 390) {
            await page.getByRole('button', { name: 'View website preview', exact: true }).click();
            await preview().getByText('An ordinary story, beautifully told.', { exact: true }).waitFor();
            assert.ok(
                await preview()
                    .locator('#our-story')
                    .evaluate((e) => e.getBoundingClientRect().top >= 0 && e.getBoundingClientRect().top < innerHeight),
            );
            assert.ok(
                await preview()
                    .locator('html')
                    .evaluate((e) => e.scrollWidth <= e.clientWidth + 1),
            );
            await page.screenshot({ path: join(artifacts, 'mobile-website-preview.png'), fullPage: true });
            await page.getByRole('button', { name: 'Edit sections', exact: true }).click();
        }
    }
    await writeFile(
        join(artifacts, 'verification.json'),
        JSON.stringify(
            {
                widths: [1440, 1024, 390],
                themes: ['light', 'dark'],
                motion: 'reduce',
                http: 'real disposable PHP/SQLite',
                photos: 'synthetic canvas only',
            },
            null,
            2,
        ),
    );
});

run('validation feedback is readable, editable state survives errors, and reload never claims a saved draft', async () => {
    await section('Our Story');
    await page.getByLabel('Story heading', { exact: true }).fill('<unsafe heading>');
    await page.getByRole('region', { name: 'Content errors' }).waitFor();
    assert.doesNotMatch(await page.getByRole('region', { name: 'Content errors' }).innerText(), /content\.sections|schema_version|asset_id/);
    assert.equal(await preview().locator('script').count(), 0);
    await page.getByLabel('Story heading', { exact: true }).fill('Our own beginning');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1').fill('Still here after validation.');
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    await page.reload();
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await section('Our Story');
    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'Our love story');
    assert.equal(await page.getByLabel('Story paragraph 1').count(), 0);
});

run('all focal/crop presets stay bounded and duplicate photo reuse does not create extra assets', async () => {
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    const uploaded = await uploadPhoto();
    await uploaded.choice.click();
    const before = (await (await context.request.get(info.previewUrl + '/images', { headers: { Accept: 'application/json' } })).json()).total;
    const welcome = editor().getByRole('region', { name: 'Welcome editing' });
    const image = preview().locator('.ee-hero-main img');
    await image.waitFor();
    await image.evaluate((img) => img.decode());
    for (const [focal, position] of Object.entries({ center: '50% 50%', top: '50% 0%', bottom: '50% 100%', left: '0% 50%', right: '100% 50%' })) {
        await welcome.getByLabel('Photo focus').selectOption(focal);
        await image.evaluate((img) => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(() => done(img)))));
        assert.equal(await image.evaluate((img) => getComputedStyle(img).objectPosition), position);
    }
    for (const crop of ['natural', 'portrait', 'landscape', 'square']) {
        await welcome.getByLabel('Photo crop').selectOption(crop);
        const bounds = await image.evaluate(
            (img) =>
                new Promise((done) =>
                    requestAnimationFrame(() =>
                        requestAnimationFrame(() => {
                            const inner = img.parentElement.getBoundingClientRect(),
                                outer = img.closest('.ee-photo').getBoundingClientRect();
                            done({
                                width: inner.width,
                                height: inner.height,
                                maxWidth: outer.width,
                                maxHeight: outer.height,
                                natural: img.naturalWidth / img.naturalHeight,
                            });
                        }),
                    ),
                ),
        );
        const ratio = { natural: bounds.maxWidth / bounds.maxHeight, portrait: 3 / 4, landscape: 4 / 3, square: 1 }[crop];
        assert.ok(bounds.width <= bounds.maxWidth + 1 && bounds.height <= bounds.maxHeight + 1);
        assert.ok(Math.abs(bounds.width / bounds.height - ratio) < 0.02);
    }
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    assert.equal(await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).isDisabled(), true);
    await page.keyboard.press('Escape');
    assert.equal(await preview().locator('.ee-gallery-slide').count(), 1);
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    const after = (await (await context.request.get(info.previewUrl + '/images', { headers: { Accept: 'application/json' } })).json()).total;
    assert.equal(after, before);
});

run('DEL02c photo-only Story and independent hero details story assignments survive visibility changes', async () => {
    await section('Our Story');
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    const story = await uploadPhoto();
    await story.choice.click();
    await preview().locator('#our-story img').waitFor();
    assert.equal(await preview().locator('#our-story img').first().getAttribute('src'), story.image.preview_url);
    await page.getByLabel('Story heading', { exact: true }).fill('Our story with photographs');
    await preview().getByRole('heading', { name: 'Our story with photographs' }).waitFor();
    await section('Welcome');
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    const hero = await uploadPhoto(900, 600);
    await hero.choice.click();
    await section('Wedding details');
    assert.equal(await preview().locator('#wedding-details img').count(), 0);
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    const details = await uploadPhoto(800, 600);
    await details.choice.click();
    assert.equal(await preview().locator('#wedding-details img').first().getAttribute('src'), details.image.preview_url);
    assert.equal(await preview().locator('.ee-hero-main img').getAttribute('src'), hero.image.preview_url);
    await section('Our Story');
    await editor().getByRole('region', { name: 'Our Story editing' }).getByLabel('Show section').uncheck();
    assert.equal(await preview().locator('#our-story').count(), 0);
    await editor().getByRole('region', { name: 'Our Story editing' }).getByLabel('Show section').check();
    await preview().locator('#our-story img').waitFor();
    assert.equal(await preview().locator('#our-story img').first().getAttribute('src'), story.image.preview_url);
});

run('DEL02c RSVP and Closing have independent visibility and preview navigation', async () => {
    await section('Closing');
    await page.getByLabel('Closing message', { exact: true }).fill('Forever grateful, always together.');
    await preview().locator('#closing').getByText('Forever grateful, always together.').waitFor();
    await section('RSVP information');
    await editor().getByRole('region', { name: 'RSVP information editing' }).getByLabel('Show section').uncheck();
    assert.equal(await preview().locator('#rsvp').count(), 0);
    assert.equal(await preview().locator('#closing').count(), 1);
    await section('Closing');
    assert.ok(
        await preview()
            .locator('#closing')
            .evaluate((e) => e.getBoundingClientRect().top >= 0 && e.getBoundingClientRect().top < innerHeight),
    );
    await editor().getByRole('region', { name: 'Closing editing' }).getByLabel('Show section').uncheck();
    assert.equal(await preview().locator('#closing').count(), 0);
    await section('RSVP information');
    await editor().getByRole('region', { name: 'RSVP information editing' }).getByLabel('Show section').check();
    await preview().locator('#rsvp').waitFor();
    assert.equal(await preview().locator('#closing').count(), 0);
});

run('DEL02c one linked validation summary opens the correct collapsed role field across tabs', async () => {
    await section('Entourage');
    await page.getByRole('button', { name: 'Add role group', exact: true }).click();
    await editor().getByRole('region', { name: 'Content errors' }).waitFor();
    await section('Welcome');
    assert.equal(await editor().getByRole('alert').count(), 0);
    await page.getByLabel('Invitation message', { exact: true }).fill('We invite you with joy.');
    await preview().getByText('We invite you with joy.', { exact: true }).waitFor();
    await section('Entourage');
    const group = page.locator('[data-role-group]');
    await group.locator('summary').click();
    assert.equal(await group.getAttribute('open'), null);
    await section('Welcome');
    const review = editor().getByRole('region', { name: 'Content errors' });
    assert.equal(await review.count(), 1, await editor().innerText());
    await review
        .getByRole('button')
        .filter({ hasText: /Entourage group 1 needs a title/ })
        .click();
    await page.getByLabel('Role group title', { exact: true }).waitFor();
    assert.ok(await page.getByLabel('Role group title', { exact: true }).evaluate((el) => el === document.activeElement));
    await page.getByLabel('Role group title', { exact: true }).fill('Our family');
    await page.getByLabel('Add a name', { exact: true }).fill('Alex');
    await page.getByLabel('Add a name', { exact: true }).press('Enter');
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    await page.getByLabel('Add a name', { exact: true }).fill('Uncommitted name');
    await section('Gallery');
    await section('Entourage');
    assert.equal(await page.getByLabel('Add a name', { exact: true }).inputValue(), 'Uncommitted name');
    assert.equal(await page.getByLabel('Person 2', { exact: true }).count(), 0);
});

run('DEL02c name chip drag and keyboard ordering keep stable identities, duplicate names and collapsible summaries', async () => {
    await section('Entourage');
    await page.getByRole('button', { name: 'Add role group', exact: true }).click();
    await page.getByLabel('Role group title', { exact: true }).fill('Loved ones');
    for (const name of ['Alex', 'Jamie', 'Alex']) {
        await page.getByLabel('Add a name', { exact: true }).fill(name);
        await page.getByLabel('Add a name', { exact: true }).press('Enter');
    }
    const grips = page.locator('.cms-chip-grip');
    const ids = await grips.evaluateAll((elements) => elements.map((e) => e.id));
    await grips.nth(1).dragTo(grips.nth(0));
    assert.equal(await page.getByLabel('Person 1', { exact: true }).inputValue(), 'Jamie');
    assert.equal(await grips.first().getAttribute('id'), ids[1]);
    await grips.first().press('Alt+ArrowDown');
    assert.equal(await page.getByLabel('Person 2', { exact: true }).inputValue(), 'Jamie');
    assert.ok(
        await page
            .getByRole('status')
            .filter({ hasText: /Jamie moved to position 2/ })
            .count(),
    );
    await page.getByLabel('Person 2', { exact: true }).fill('Jamie renamed');
    await preview().getByText('Jamie renamed', { exact: true }).waitFor();
    await page.locator('[data-role-group] summary').click();
    await page.getByText('Loved ones · 3 names', { exact: true }).waitFor();
    assert.equal(await page.getByLabel('Person 1', { exact: true }).isVisible(), false);
    await page.locator('[data-role-group] summary').click();
    await page.getByRole('button', { name: 'Remove person 2', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Remove person 2', exact: true }).click();
    assert.equal(await grips.count(), 2);
    assert.equal(await page.getByLabel('Person 2', { exact: true }).inputValue(), 'Alex');
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const dark of [false, true]) {
            await page.evaluate((d) => document.documentElement.classList.toggle('dark', d), dark);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await page.screenshot({ path: join(artifacts, 'del02c-chips-' + width + '-' + (dark ? 'dark' : 'light') + '.png'), fullPage: true });
        }
    }
});

run('DEL02c section navigation locates content without jumping on keystrokes and closing transitions stay composed', async () => {
    await section('Our Story');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1').fill('Every ordinary day became a little more beautiful together.');
    await preview().locator('#our-story').waitFor();
    await preview()
        .locator('html')
        .evaluate(() => window.scrollTo(0, 0));
    await page.getByLabel('Story paragraph 1').fill('Every ordinary day became a little more beautiful together. Forever starts here.');
    assert.equal(
        await preview()
            .locator('html')
            .evaluate(() => window.scrollY),
        0,
    );
    await section('Entourage');
    await editor().getByRole('region', { name: 'Entourage editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    await section('Closing');
    assert.ok(
        await preview()
            .locator('#closing')
            .evaluate((e) => e.getBoundingClientRect().top >= 0 && e.getBoundingClientRect().top < innerHeight),
    );
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        if (width === 390) await page.getByRole('button', { name: 'View website preview', exact: true }).click();
        const layout = await preview()
            .locator('body')
            .evaluate(() => {
                const rect = (id) => document.getElementById(id).getBoundingClientRect();
                const gallery = rect('our-photos'),
                    rsvp = rect('rsvp'),
                    closing = rect('closing');
                return {
                    gap: rsvp.top - gallery.bottom,
                    closingGap: closing.top - rsvp.bottom,
                    overflow: document.documentElement.scrollWidth > innerWidth + 1,
                };
            });
        assert.ok(layout.gap >= 35 && layout.gap <= 73);
        assert.ok(layout.closingGap >= -1);
        assert.equal(layout.overflow, false);
        await page.locator('iframe').evaluate((frame) => {
            frame.style.height = frame.contentDocument.querySelector('.enchanted-editorial').getBoundingClientRect().height + 'px';
            frame.contentWindow.scrollTo(0, 0);
        });
        await preview()
            .locator('body')
            .evaluate(async () => {
                await document.fonts.ready;
                await Promise.all(
                    [...document.images].map(async (image) => {
                        image.loading = 'eager';
                        await image.decode();
                    }),
                );
            });
        await page.screenshot({ path: join(artifacts, 'del02c-transitions-' + width + '.png'), fullPage: true });
    }
});

run('DEL02d timeline preserves ordered actual dates, descriptions and uploaded private milestone photos', async () => {
    await section('Our Story');
    assert.equal(await preview().locator('.ee-story-timeline').count(), 0);
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1').fill('Our narrative stays separate from our milestones.');
    let photo;
    for (const [index, title] of ['Our first adventure', 'The promise', 'Every day together'].entries()) {
        await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
        const row = page.locator('[data-milestone-editor]').last();
        assert.equal(await row.locator('details').getAttribute('open'), '');
        await row.getByLabel('Milestone title', { exact: true }).fill(title);
        await row
            .getByLabel('Milestone description', { exact: true })
            .fill(index === 1 ? 'A long and beautiful memory. '.repeat(24) : 'A memory we treasure.');
        if (index === 0) {
            await row.getByLabel('Milestone date precision').selectOption('year');
            assert.equal(await row.getByLabel('Milestone year').inputValue(), '');
            await row.getByLabel('Milestone year').fill('2020');
            await row.getByRole('button', { name: 'Choose photo', exact: true }).click();
            const uploaded = await uploadPhoto(600, 900);
            photo = uploaded.image;
            await uploaded.choice.click();
        }
        if (index === 1) {
            await row.getByLabel('Milestone date precision').selectOption('date');
            await row.getByLabel('Milestone date', { exact: true }).fill('2025-03-09');
        }
        await row.getByRole('button', { name: 'Done editing milestone', exact: true }).click();
        assert.equal(await row.locator('details').getAttribute('open'), null);
    }
    const timeline = preview().locator('.ee-story-timeline');
    await timeline.getByRole('heading', { name: 'The Moments That Led Us Here' }).waitFor();
    assert.deepEqual(await timeline.locator('h4').allTextContents(), ['Our first adventure', 'The promise', 'Every day together']);
    assert.deepEqual(await timeline.locator('time').allTextContents(), ['2020', '9 March 2025']);
    assert.equal(await preview().locator('.ee-story-prose').getByText('Our first adventure', { exact: true }).count(), 0);
    const image = timeline.locator('img');
    await image.scrollIntoViewIfNeeded();
    await image.evaluate((img) => img.decode());
    assert.equal(await image.getAttribute('src'), photo.preview_url);
    const anonymous = await browser.newContext();
    assert.equal((await anonymous.request.get(info.origin + photo.preview_url, { headers: { Accept: 'application/json' } })).status(), 401);
    await anonymous.close();
    const desktop = await timeline.locator('.ee-timeline-content').evaluateAll((nodes) => nodes.map((n) => n.getBoundingClientRect().left));
    assert.ok(desktop[1] > desktop[0] + 50);
    await page.screenshot({ path: join(artifacts, 'del02d-timeline-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 900 });
    await page.getByRole('button', { name: 'View website preview', exact: true }).click();
    const mobile = await timeline.locator('.ee-timeline-content').evaluateAll((nodes) => nodes.map((n) => n.getBoundingClientRect().left));
    assert.ok(Math.abs(mobile[0] - mobile[1]) < 1);
    assert.ok(
        await preview()
            .locator('html')
            .evaluate((el) => el.scrollWidth <= innerWidth + 1),
    );
    await page.locator('iframe').evaluate((frame) => {
        frame.style.height = frame.contentDocument.querySelector('.enchanted-editorial').getBoundingClientRect().height + 'px';
        frame.contentWindow.scrollTo(0, 0);
    });
    await page.screenshot({ path: join(artifacts, 'del02d-timeline-mobile.png'), fullPage: true });
});

run('DEL02d collapsed milestones retain identity, validation, keyboard order and confirmed removal', async () => {
    await section('Our Story');
    await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
    const first = page.locator('[data-milestone-editor]').first();
    const firstId = await first.getAttribute('data-milestone-editor');
    await first.getByRole('button', { name: 'Done editing milestone', exact: true }).click();
    await first.getByText('Needs attention', { exact: true }).waitFor();
    await editor()
        .getByRole('region', { name: 'Content errors' })
        .getByRole('button')
        .filter({ hasText: /milestone 1 title/ })
        .click();
    assert.ok(await first.getByLabel('Milestone title', { exact: true }).evaluate((el) => el === document.activeElement));
    await first.getByLabel('Milestone title', { exact: true }).fill('First moment');
    await first.getByRole('button', { name: 'Done editing milestone', exact: true }).click();
    await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
    const second = page.locator('[data-milestone-editor]').last();
    await second.getByLabel('Milestone title', { exact: true }).fill('Second moment');
    await second.getByRole('button', { name: 'Done editing milestone', exact: true }).click();
    await second.locator('summary').press('Alt+ArrowUp');
    assert.equal(await page.locator('[data-milestone-editor]').last().getAttribute('data-milestone-editor'), firstId);
    assert.deepEqual(await preview().locator('.ee-timeline-entry h4').allTextContents(), ['Second moment', 'First moment']);
    const reordered = page.locator('[data-milestone-editor]').first();
    await reordered.getByRole('button', { name: 'Remove milestone', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Keep content', exact: true }).click();
    assert.equal(await page.locator('[data-milestone-editor]').count(), 2);
    await reordered.getByRole('button', { name: 'Remove milestone', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Remove milestone', exact: true }).click();
    assert.equal(await page.locator('[data-milestone-editor]').count(), 1);
    assert.equal(await page.locator('[data-milestone-editor]').getAttribute('data-milestone-editor'), firstId);
    await section('Welcome');
    await section('Our Story');
    assert.equal(await first.locator('details').getAttribute('open'), null);
    await first.locator('summary').click();
    assert.equal(await first.getByLabel('Milestone title', { exact: true }).inputValue(), 'First moment');
});

run('DEL02d desktop panes scroll independently with reachable navigation and compact many-group actions', async () => {
    await section('Entourage');
    for (let index = 0; index < 8; index++) {
        await page.getByRole('button', { name: 'Add role group', exact: true }).click();
        const group = page.locator('[data-role-group]').last();
        await group.getByLabel('Role group title', { exact: true }).fill('Family and friends ' + (index + 1));
        await group.getByLabel('Add a name', { exact: true }).fill('Alexandra Maria '.repeat(8));
        await group.getByLabel('Add a name', { exact: true }).press('Enter');
        await group.getByRole('button', { name: 'Done editing role group', exact: true }).click();
    }
    assert.equal(await page.locator('[data-role-group][open]').count(), 0);
    await page.setViewportSize({ width: 1440, height: 850 });
    const pane = editor().locator('.cms-editor-fields');
    await pane.focus();
    await pane.press('PageDown');
    await page.waitForFunction(() => document.querySelector('.cms-editor-fields').scrollTop > 0);
    await pane.evaluate((el) => (el.scrollTop = el.scrollHeight));
    const before = await pane.evaluate((el) => el.scrollTop);
    assert.ok(before > 0);
    const nav = await page.getByRole('navigation', { name: 'Editor sections' }).boundingBox();
    assert.ok(nav.y >= 0 && nav.y < 400);
    assert.ok(await page.evaluate(() => document.documentElement.scrollHeight <= innerHeight + 2));
    const frame = preview().locator('html');
    await frame.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    assert.equal(await pane.evaluate((el) => el.scrollTop), before);
    assert.ok(await frame.evaluate(() => window.scrollY > 0));
    await page.locator('.cms-collection-row').first().getByRole('button', { name: 'Move role group down', exact: true }).click();
    assert.equal(await page.locator('[data-role-group] summary').first().innerText(), 'Family and friends 2 · 1 name');
    for (const width of [1440, 1024]) {
        await page.setViewportSize({ width, height: 850 });
        const navigationBounds = await page.getByRole('navigation', { name: 'Editor sections' }).evaluate((nav) => {
            const pane = nav.closest('.cms-workspace').getBoundingClientRect();
            return {
                pane: { top: pane.top, bottom: pane.bottom },
                buttons: [...nav.querySelectorAll('button')].map((button) => ({
                    label: button.textContent,
                    top: button.getBoundingClientRect().top,
                    bottom: button.getBoundingClientRect().bottom,
                })),
                display: getComputedStyle(nav).display,
                position: getComputedStyle(nav).position,
            };
        });
        assert.ok(
            navigationBounds.buttons.every(
                (button) => button.top >= navigationBounds.pane.top - 1 && button.bottom <= navigationBounds.pane.bottom + 1,
            ),
            JSON.stringify(navigationBounds),
        );
        for (const dark of [false, true]) {
            await page.evaluate((d) => document.documentElement.classList.toggle('dark', d), dark);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await page.screenshot({ path: join(artifacts, 'del02d-panes-' + width + '-' + (dark ? 'dark' : 'light') + '.png'), fullPage: true });
        }
    }
    await page.setViewportSize({ width: 390, height: 850 });
    assert.equal(await pane.evaluate((el) => getComputedStyle(el).overflowY), 'visible');
    assert.ok(await page.evaluate(() => document.documentElement.scrollHeight > innerHeight));
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path: join(artifacts, 'del02d-compact-mobile.png'), fullPage: true });
});

run('DEL03a real private upload slots, section content and visibility persist through Save Draft and reload', async () => {
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    const { image, choice } = await uploadPhoto();
    await choice.click();
    await section('Our Story');
    await page.getByLabel('Story heading', { exact: true }).fill('Our saved beginning');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1').fill('Saved words that belong to us.');
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
    await page.screenshot({ path: join(artifacts, 'del03a-story-assignment.png'), fullPage: true });
    await editor().getByRole('region', { name: 'Our Story editing' }).getByLabel('Photo focus').selectOption('top');
    await section('Wedding details');
    await page.getByRole('button', { name: 'Choose photo', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    await editor().getByRole('region', { name: 'Wedding details editing' }).getByLabel('Photo crop').selectOption('landscape');
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    await page.getByLabel('Caption 1', { exact: true }).fill('Our saved gallery');
    await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await section('Entourage');
    await page.getByRole('button', { name: 'Add role group', exact: true }).click();
    await page.getByLabel('Role group title', { exact: true }).fill('Our loved ones');
    await page.getByLabel('Add a name', { exact: true }).fill('Sam');
    await page.getByLabel('Add a name', { exact: true }).press('Enter');
    const saved = page.waitForResponse((r) => r.url().endsWith('/draft') && r.request().method() === 'PUT');
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    assert.equal((await saved).status(), 200);
    await page.getByText('All changes saved', { exact: false }).waitFor();
    await page.reload();
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    assert.equal(await preview().locator('.ee-hero-main img').getAttribute('src'), image.preview_url);
    await section('Our Story');
    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'Our saved beginning');
    assert.equal(await page.getByLabel('Story paragraph 1').inputValue(), 'Saved words that belong to us.');
    assert.equal(await preview().locator('.ee-story-main img').getAttribute('src'), image.preview_url);
    await section('Gallery');
    assert.equal(await page.getByLabel('Caption 1', { exact: true }).inputValue(), 'Our saved gallery');
    assert.equal(await preview().locator('#our-photos').count(), 0);
    const popup = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'View saved draft', exact: true }).click();
    const savedPage = await popup;
    await savedPage.getByRole('heading', { name: 'Our saved beginning', exact: true }).waitFor();
    assert.equal(await savedPage.locator('#our-photos').count(), 0);
    assert.equal(await savedPage.getByText('Sam', { exact: true }).count(), 1);
    assert.equal((await context.request.get(info.previewUrl + '/saved-preview')).headers()['cache-control'], 'no-store, private');
    await savedPage.screenshot({ path: join(artifacts, 'del03a-saved-private-desktop.png'), fullPage: true });
    await savedPage.close();
    const source = await (await context.request.get(info.previewUrl + '/draft')).json();
    assert.equal(source.document.content.photo_slots.details.crop, 'landscape');
    assert.equal(source.document.content.photo_slots.story.focal, 'top');
    const anonymous = await browser.newContext();
    assert.equal((await anonymous.request.get(info.previewUrl + '/saved-preview', { headers: { Accept: 'application/json' } })).status(), 401);
    assert.equal(
        (
            await context.request.put(info.previewUrl + '/draft', { data: { document: source.document, draft_version: source.draft_version } })
        ).status(),
        419,
    );
    await anonymous.close();
});

run('DEL03a two windows reject stale saves without overwriting or discarding local edits', async () => {
    const peer = await context.newPage();
    await peer.goto(info.previewUrl);
    await peer.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await peer.getByRole('navigation', { name: 'Editor sections' }).getByRole('button', { name: 'Our Story', exact: true }).click();
    await peer.getByLabel('Story heading', { exact: true }).fill('Still local in the other window');
    await section('Our Story');
    await page.getByLabel('Story heading', { exact: true }).fill('Winning saved story');
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText('Draft saved privately. Your published website has not changed.', { exact: false }).waitFor();
    const response = peer.waitForResponse((r) => r.url().endsWith('/draft') && r.request().method() === 'PUT');
    await peer.getByRole('button', { name: 'Save Draft', exact: true }).click();
    assert.equal((await response).status(), 422);
    await peer
        .getByText(/This draft changed in another window/, { exact: false })
        .first()
        .waitFor();
    assert.equal(await peer.getByLabel('Story heading', { exact: true }).inputValue(), 'Still local in the other window');
    const source = await (await context.request.get(info.previewUrl + '/draft')).json();
    assert.equal(source.document.content.sections.find((s) => s.type === 'story').data.heading, 'Winning saved story');
    await peer.close();
});

run('DEL03a edits made during a pending save remain dirty and duplicate clicks send one write', async () => {
    await section('Our Story');
    await page.getByLabel('Story heading', { exact: true }).fill('Captured save');
    let release, committed;
    const hold = new Promise((resolve) => (release = resolve)),
        serverDone = new Promise((resolve) => (committed = resolve));
    let writes = 0;
    await page.route('**/cms-preview/draft', async (route) => {
        if (route.request().method() !== 'PUT') return route.continue();
        writes++;
        const response = await route.fetch();
        committed();
        await hold;
        return route.fulfill({ response });
    });
    await page.getByRole('button', { name: 'Save Draft', exact: true }).evaluate((el) => {
        el.click();
        el.click();
    });
    await serverDone;
    await page.getByLabel('Story heading', { exact: true }).fill('Newer unsaved edit');
    release();
    await page.getByText('Draft saved. Newer edits are still unsaved.', { exact: false }).waitFor();
    assert.equal(writes, 1);
    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'Newer unsaved edit');
    const source = await (await context.request.get(info.previewUrl + '/draft')).json();
    assert.equal(source.document.content.sections.find((s) => s.type === 'story').data.heading, 'Captured save');
    await page.getByLabel('Story heading', { exact: true }).fill('Captured save');
    await page.getByText('All changes saved', { exact: false }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Save Draft', exact: true }).isDisabled(), true);
});

run('DEL03a uncertain committed save is never reported successful and retry fails stale', async () => {
    await section('Our Story');
    await page.getByLabel('Story heading', { exact: true }).fill('Committed without acknowledgment');
    await page.route(
        '**/cms-preview/draft',
        async (route) => {
            if (route.request().method() !== 'PUT') return route.continue();
            await route.fetch();
            return route.abort('failed');
        },
        { times: 1 },
    );
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText(/The save could not be confirmed. Your edits remain here./, { exact: false }).waitFor();
    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'Committed without acknowledgment');
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page
        .getByText(/This draft changed in another window/, { exact: false })
        .first()
        .waitFor();
    await page.getByRole('button', { name: 'Review saved version', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Keep editing', exact: true }).click();
    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'Committed without acknowledgment');
    await page.getByRole('button', { name: 'Review saved version', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Discard edits and load saved version', exact: true }).click();
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await page.getByText('All changes saved', { exact: false }).waitFor();
});

run('DEL03a saved editor responsive controls and canonical timezone work without changing publication', async () => {
    await page.goto(info.previewUrl.replace('/cms-preview', '/edit'));
    await page.getByLabel('Wedding timezone', { exact: true }).fill('America/New_York');
    await page.getByRole('button', { name: 'Save changes', exact: true }).click();
    await page.waitForURL(info.previewUrl.replace('/cms-preview', ''));
    await page.goto(info.previewUrl);
    await page.getByText('Wedding timezone: America/New_York', { exact: false }).waitFor();
    await section('Wedding details');
    await preview().locator('.ee-details-date').getByText('America/New_York', { exact: false }).waitFor();
    await page.locator('img').evaluateAll(async (images) => {
        await Promise.all(
            images.map(async (image) => {
                image.loading = 'eager';
                await image.decode();
            }),
        );
    });
    await preview()
        .locator('img')
        .evaluateAll(async (images) => {
            await Promise.all(
                images.map(async (image) => {
                    image.loading = 'eager';
                    await image.decode();
                }),
            );
        });
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const dark of [false, true]) {
            await page.evaluate((value) => document.documentElement.classList.toggle('dark', value), dark);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await page.screenshot({ path: join(artifacts, `del03a-editor-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
        }
    }
    const popup = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'View saved draft', exact: true }).click();
    const saved = await popup;
    await saved.getByText('Private saved draft · not your published website', { exact: true }).waitFor();
    await saved.locator('img').evaluateAll(async (images) => {
        await Promise.all(
            images.map(async (image) => {
                image.loading = 'eager';
                await image.decode();
            }),
        );
    });
    await saved.evaluate(() => document.fonts.ready);
    for (const width of [1440, 390]) {
        await saved.setViewportSize({ width, height: 1000 });
        assert.ok(await saved.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await saved.screenshot({ path: join(artifacts, `del03a-saved-private-${width}.png`), fullPage: true });
    }
    await saved.close();
});

run('DEL03a1 friendly validation summary opens invalid groups and focuses their marked fields', async () => {
    await section('Entourage');
    await page.getByRole('button', { name: 'Add role group', exact: true }).click();
    const response = page.waitForResponse((r) => r.url().endsWith('/draft') && r.request().method() === 'PUT');
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    assert.equal((await response).status(), 422);
    const summary = page.getByRole('region', { name: 'Content errors' });
    await summary.waitFor();
    assert.equal(await page.locator('.cms-error-summary').count(), 1);
    assert.equal(await page.locator('.cms-feedback').count(), 0);
    assert.equal(await page.getByText('Check this section', { exact: true }).count(), 0);
    assert.equal(await summary.evaluate((el) => el === document.activeElement), true);
    assert.notEqual(await summary.evaluate((el) => getComputedStyle(el).backgroundColor), 'rgba(0, 0, 0, 0)');
    const link = summary.getByRole('button', { name: /Entourage group \d+ needs a title/ }).first();
    await link.click();
    const title = page.getByLabel('Role group title', { exact: true }).last();
    assert.equal(await title.evaluate((el) => el === document.activeElement), true);
    assert.equal(await title.getAttribute('aria-invalid'), 'true');
    assert.ok(await title.getAttribute('aria-describedby'));
    assert.ok(!/content\.sections\.\d/.test(await page.locator('body').innerText()));
    await section('Welcome');
    await summary
        .getByRole('button', { name: /Entourage group \d+ needs a title/ })
        .first()
        .click();
    assert.equal(await title.isVisible(), true);
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.screenshot({ path: join(artifacts, `del03a1-errors-${width}.png`), fullPage: true });
    }
    await title.fill('Our family');
    await page.getByLabel('Add a name', { exact: true }).last().fill('Sam');
    await page.getByLabel('Add a name', { exact: true }).last().press('Enter');
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    assert.equal(await page.locator('.cms-error-summary, .cms-feedback').count(), 0);
});

run('DEL03a1 photo Fit and slideshow preferences survive Save, reload and private saved preview', async () => {
    await section('Gallery');
    const gallery = editor().getByRole('region', { name: 'Gallery editing' });
    const initial = await (await context.request.get(info.previewUrl + '/draft')).json();
    const initialGallery = initial.document.content.sections.find((s) => s.type === 'gallery');
    assert.equal(await gallery.getByLabel('Show section', { exact: true }).isChecked(), initialGallery.enabled);
    assert.equal(await page.getByLabel('Auto-play slideshow', { exact: true }).isChecked(), initialGallery.data.slideshow?.autoplay ?? true);
    await gallery.getByLabel('Show section', { exact: true }).check();
    await page.getByLabel('Auto-play slideshow', { exact: true }).check();
    for (const size of [
        [480, 800],
        [900, 500],
    ]) {
        await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
        await uploadPhoto(...size);
        await page.getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    await gallery.getByLabel('Photo frame', { exact: true }).first().selectOption('fit');
    await gallery.getByLabel('Photo frame', { exact: true }).last().selectOption('fill');
    await page.getByLabel('Time between photographs', { exact: true }).selectOption('8');
    await page.getByLabel('Auto-play slideshow', { exact: true }).uncheck();
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText('All changes saved', { exact: false }).waitFor();
    await page.reload();
    await section('Gallery');
    assert.equal(await page.getByLabel('Auto-play slideshow', { exact: true }).isChecked(), false);
    assert.equal(await gallery.getByLabel('Photo frame', { exact: true }).first().inputValue(), 'fit');
    const source = await (await context.request.get(info.previewUrl + '/draft')).json();
    const stored = source.document.content.sections.find((s) => s.type === 'gallery');
    assert.deepEqual(stored.data.slideshow, { autoplay: false, interval_seconds: 8 });
    const saved = await context.newPage();
    await saved.goto(info.previewUrl + '/saved-preview');
    await saved.locator('.ee-gallery img').first().waitFor();
    assert.equal(
        await saved
            .locator('.ee-gallery img')
            .first()
            .evaluate((el) => getComputedStyle(el).objectFit),
        'contain',
    );
    assert.equal(
        await saved
            .locator('.ee-gallery img')
            .last()
            .evaluate((el) => getComputedStyle(el).objectFit),
        'cover',
    );
    assert.equal(await saved.locator('.ee-footer-rings').count(), 1);
    for (const width of [1440, 390]) {
        await saved.setViewportSize({ width, height: 1000 });
        await saved.evaluate(async () => {
            await document.fonts.ready;
            await Promise.all(
                [...document.images].map((i) => {
                    i.loading = 'eager';
                    return Promise.race([i.decode().catch(() => undefined), new Promise((resolve) => setTimeout(resolve, 3000))]);
                }),
            );
        });
        assert.ok(await saved.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await saved.screenshot({ path: join(artifacts, `del03a1-saved-${width}.png`), fullPage: true });
    }
    await saved.close();
});

run('DEL03a1 slideshow advances only in view, respects reduced motion and stops for manual interaction', async () => {
    await section('Gallery');
    await page.getByLabel('Auto-play slideshow', { exact: true }).check();
    await page.getByLabel('Time between photographs', { exact: true }).selectOption('5');
    const gallery = preview().locator('#our-photos');
    await gallery.scrollIntoViewIfNeeded();
    assert.equal(await gallery.getAttribute('data-playing'), 'false');
    await page.clock.install();
    await page.evaluate(() => {
        window.__cmsGalleryIntervals = new Set();
        const start = window.setInterval.bind(window),
            stop = window.clearInterval.bind(window);
        window.setInterval = (...args) => {
            const id = start(...args);
            window.__cmsGalleryIntervals.add(id);
            return id;
        };
        window.clearInterval = (id) => {
            window.__cmsGalleryIntervals.delete(id);
            stop(id);
        };
    });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await preview().locator('#our-photos[data-playing=true]').waitFor();
    const active = () => gallery.locator('.ee-gallery-slide:not([hidden])').getAttribute('aria-label');
    const first = await active();
    await page.clock.fastForward(5100);
    assert.notEqual(await active(), first);
    await gallery.getByRole('button', { name: 'Next photograph', exact: true }).click();
    const manual = await active();
    assert.equal(await gallery.getAttribute('data-playing'), 'false');
    await page.clock.fastForward(15000);
    assert.equal(await active(), manual);
    await gallery.getByRole('button', { name: 'Play slideshow', exact: true }).click();
    await gallery.locator('button:focus').evaluate((el) => el.blur());
    await preview().locator('#our-photos[data-playing=true]').waitFor();
    await preview().locator('#invitation').scrollIntoViewIfNeeded();
    await preview().locator('#our-photos[data-playing=false]').waitFor();
    await gallery.scrollIntoViewIfNeeded();
    await preview().locator('#our-photos[data-playing=true]').waitFor();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await preview().locator('#our-photos[data-playing=false]').waitFor();
    assert.equal(await gallery.getByRole('button', { name: 'Play slideshow', exact: true }).isDisabled(), true);
    assert.equal(await page.evaluate(() => window.__cmsGalleryIntervals.size), 0);
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await preview().locator('#our-photos[data-playing=true]').waitFor();
    assert.equal(await page.evaluate(() => window.__cmsGalleryIntervals.size), 1);
    await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).uncheck();
    assert.equal(await preview().locator('#our-photos').count(), 0);
    assert.equal(await page.evaluate(() => window.__cmsGalleryIntervals.size), 0);
});

run('DEL03a1 hidden gallery survives reload and its landing marker disappears with the section', async () => {
    await section('Gallery');
    await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText('All changes saved', { exact: false }).waitFor();
    await page.reload();
    await section('Gallery');
    assert.equal(await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).isChecked(), false);
    assert.equal(await preview().locator('[data-landing-section="our-photos"]').count(), 0);
    await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).check();
    await preview().locator('[data-landing-section="our-photos"]').waitFor();
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const dark of [false, true]) {
            await page.evaluate((value) => document.documentElement.classList.toggle('dark', value), dark);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await page.screenshot({ path: join(artifacts, `del03a1-editor-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
        }
    }
});

run('DEL03a valid empty section documents load safely without inventing or deleting content', async () => {
    const before = await (await context.request.get(info.previewUrl + '/draft')).json();
    const saveSource = async (document, version) =>
        page.evaluate(
            async ({ url, document, version }) => {
                const cookie = window.document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
                const response = await fetch(url, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)),
                    },
                    body: JSON.stringify({ document, draft_version: version }),
                });
                return { status: response.status, body: await response.json() };
            },
            { url: info.previewUrl + '/draft', document, version },
        );
    const empty = structuredClone(before.document);
    empty.content.sections = [];
    const result = await saveSource(empty, before.draft_version);
    assert.equal(result.status, 200);
    await page.reload();
    await page.getByText('This draft has no content sections. Its saved data has been preserved.', { exact: true }).waitFor();
    const source = await (await context.request.get(info.previewUrl + '/draft')).json();
    assert.deepEqual(source.document.content.sections, []);
    assert.equal((await saveSource(before.document, source.draft_version)).status, 200);
    await page.reload();
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
});

run('DEL03a2 all slideshow intervals, on/off and hidden gallery preferences survive Save and reload', async () => {
    await section('Gallery');
    const gallery = editor().getByRole('region', { name: 'Gallery editing' });
    await gallery.getByLabel('Show section', { exact: true }).check();
    for (let i = 0; i < 2; i++) {
        await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
        await uploadPhoto();
        await page.getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    const before = await (await context.request.get(info.previewUrl + '/images')).json();
    for (const [autoplay, interval, visible] of [
        [true, 5, true],
        [false, 8, false],
        [true, 12, false],
        [false, 5, true],
    ]) {
        await page.getByLabel('Auto-play slideshow', { exact: true }).check();
        await page.getByLabel('Time between photographs', { exact: true }).selectOption(String(interval));
        await page.getByLabel('Auto-play slideshow', { exact: true }).setChecked(autoplay);
        await gallery.getByLabel('Show section', { exact: true }).setChecked(visible);
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('All changes saved', { exact: false }).waitFor();
        const source = await (await context.request.get(info.previewUrl + '/draft')).json();
        const stored = source.document.content.sections.find((s) => s.type === 'gallery');
        assert.deepEqual(stored.data.slideshow, { autoplay, interval_seconds: interval });
        await page.reload();
        await section('Gallery');
        assert.equal(await page.getByLabel('Auto-play slideshow', { exact: true }).isChecked(), autoplay);
        await page.getByLabel('Auto-play slideshow', { exact: true }).check();
        assert.equal(await page.getByLabel('Time between photographs', { exact: true }).inputValue(), String(interval));
        await page.getByLabel('Auto-play slideshow', { exact: true }).setChecked(autoplay);
        assert.equal(await gallery.getByLabel('Show section', { exact: true }).isChecked(), visible);
        await preview().locator('.enchanted-editorial').waitFor();
        if (visible) await preview().locator('#our-photos').waitFor();
        assert.equal(await preview().locator('#our-photos').count(), Number(visible));
        assert.deepEqual((await (await context.request.get(info.previewUrl + '/draft')).json()).document, source.document);
        const saved = await context.newPage();
        await saved.goto(info.previewUrl + '/saved-preview');
        await saved.getByText('Private saved draft', { exact: false }).waitFor();
        await saved.locator('.enchanted-editorial').waitFor();
        assert.equal(await saved.locator('#our-photos').count(), Number(visible));
        if (visible) assert.equal(await saved.locator('.ee-slideshow-playback').count(), Number(autoplay));
        await saved.close();
    }
    assert.equal((await (await context.request.get(info.previewUrl + '/images')).json()).total, before.total);
});

async function checkMobileMenu(root) {
    const header = root.locator('.ee-header');
    await header.scrollIntoViewIfNeeded();
    const toggle = root.getByRole('button', { name: 'Open wedding menu', exact: true });
    await toggle.waitFor();
    const nav = root.getByRole('navigation', { name: 'Wedding sections' });
    assert.equal(await nav.isVisible(), false);
    await toggle.focus();
    await toggle.press('Enter');
    assert.equal(await nav.isVisible(), true);
    assert.equal(await nav.getByRole('link', { name: 'Invitation', exact: true }).evaluate((el) => el === document.activeElement), true);
    await nav.getByRole('link', { name: 'Invitation', exact: true }).press('Tab');
    assert.equal(await nav.getByRole('link', { name: 'Wedding details', exact: true }).evaluate((el) => el === document.activeElement), true);
    await nav.getByRole('link', { name: 'Wedding details', exact: true }).press('Escape');
    assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
    assert.equal(await toggle.evaluate((el) => el === document.activeElement), true);
    await toggle.press('Space');
    await nav.getByRole('link', { name: 'Wedding details', exact: true }).click();
    assert.equal(await nav.isVisible(), false);
    assert.equal(await root.locator('#wedding-details').evaluate((el) => el === document.activeElement), true);
    assert.ok(
        await root
            .locator('#wedding-details')
            .evaluate((el) => Math.abs(el.getBoundingClientRect().top - parseFloat(getComputedStyle(el).scrollMarginTop)) < 4),
    );
    await header.scrollIntoViewIfNeeded();
    await toggle.click();
    await root.locator('h1').click();
    assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
}

run('DEL03a2 mobile menu works in live and saved previews with keyboard, focus, hidden sections and desktop navigation', async () => {
    await section('Gallery');
    await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText('All changes saved', { exact: false }).waitFor();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'View website preview', exact: true }).click();
    await checkMobileMenu(preview());
    await preview().getByRole('button', { name: 'Open wedding menu', exact: true }).click();
    assert.equal(await preview().getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Photos', exact: true }).count(), 0);
    await page.screenshot({ path: join(artifacts, 'del03a2-live-mobile-menu.png'), fullPage: true });
    const saved = await context.newPage();
    await saved.setViewportSize({ width: 390, height: 844 });
    await saved.goto(info.previewUrl + '/saved-preview');
    await checkMobileMenu(saved);
    await saved.getByRole('button', { name: 'Open wedding menu', exact: true }).click();
    await saved.screenshot({ path: join(artifacts, 'del03a2-saved-mobile-menu.png'), fullPage: true });
    assert.equal(await saved.locator('html').evaluate((el) => el.scrollWidth <= el.clientWidth), true);
    await saved.setViewportSize({ width: 1440, height: 1000 });
    const nav = saved.getByRole('navigation', { name: 'Wedding sections' });
    await nav.waitFor();
    assert.equal(await saved.getByRole('button', { name: /wedding menu/ }).isVisible(), false);
    await nav.getByRole('link', { name: 'Wedding details', exact: true }).click();
    assert.equal(await saved.locator('#wedding-details').evaluate((el) => el === document.activeElement), true);
    await saved.screenshot({ path: join(artifacts, 'del03a2-saved-desktop.png'), fullPage: true });
    await saved.close();
});

run('DEL03a2 waypoint discovery keeps stable milestone identity and document order across reorder, hiding and removal', async () => {
    await section('Gallery');
    await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await section('Our Story');
    for (const title of ['First waypoint moment', 'Second waypoint moment']) {
        await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
        await page.getByLabel('Milestone title', { exact: true }).last().fill(title);
        await preview().getByRole('heading', { name: title, exact: true }).waitFor();
    }
    const rows = page.locator('[data-milestone-editor]');
    const ids = await rows.evaluateAll((els) => els.map((el) => el.dataset.milestoneEditor));
    const destinations = await waypoints();
    assert.equal(new Set(destinations.map((p) => p.id)).size, destinations.length);
    assert.deepEqual(
        destinations.filter((p) => p.kind === 'milestone').map((p) => p.id),
        ids.map((id) => 'milestone:' + id),
    );
    assert.deepEqual(
        destinations.filter((p) => p.kind === 'section').map((p) => p.section),
        ['invitation', 'wedding-details', 'our-story', 'entourage', 'rsvp', 'closing'],
    );
    await rows.last().getByRole('button', { name: 'Move milestone up', exact: true }).click();
    const reorderedIds = await rows.evaluateAll((els) => els.map((el) => el.dataset.milestoneEditor));
    await preview()
        .locator('.ee-timeline-entry[data-milestone-id="' + reorderedIds[0] + '"]')
        .first()
        .waitFor();
    assert.deepEqual(
        (await waypoints()).filter((p) => p.kind === 'milestone').map((p) => p.id),
        reorderedIds.map((id) => 'milestone:' + id),
    );
    const row = rows.filter({ has: page.getByLabel('Milestone title', { exact: true }) }).last();
    await row.locator('details').evaluate((el) => (el.open = true));
    await row.getByLabel('Show this milestone', { exact: true }).uncheck();
    const hiddenId = await row.getAttribute('data-milestone-editor');
    await preview()
        .locator('[data-landing-milestone="' + hiddenId + '"]')
        .waitFor({ state: 'detached' });
    assert.ok(!(await waypoints()).some((p) => p.id === 'milestone:' + hiddenId));
    await row.getByLabel('Show this milestone', { exact: true }).check();
    await preview()
        .locator('[data-landing-milestone="' + hiddenId + '"]')
        .waitFor();
    await row.getByRole('button', { name: 'Remove milestone', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Remove milestone', exact: true }).click();
    await preview()
        .locator('[data-landing-milestone="' + hiddenId + '"]')
        .waitFor({ state: 'detached' });
    assert.ok(!(await waypoints()).some((p) => p.id === 'milestone:' + hiddenId));
    await editor().getByRole('region', { name: 'Our Story editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await preview().locator('#our-story').waitFor({ state: 'detached' });
    assert.ok(!(await waypoints()).some((p) => p.section === 'our-story'));
    await editor().getByRole('region', { name: 'Our Story editing' }).getByLabel('Show section', { exact: true }).check();
    await preview().locator('#our-story').waitFor();
    const desktop = await waypoints();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'View website preview', exact: true }).click();
    assert.deepEqual(await waypoints(), desktop);
    await preview().locator('#our-story').scrollIntoViewIfNeeded();
    await page.screenshot({ path: join(artifacts, 'del03a2-waypoints-mobile.png'), fullPage: true });
});

run('DEL03a2 photograph dissolve keeps controls and geometry stable, cleans up rapid changes and respects reduced motion', async () => {
    await section('Gallery');
    await editor().getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).check();
    const gallery = preview().locator('#our-photos');
    await gallery.scrollIntoViewIfNeeded();
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const next = gallery.getByRole('button', { name: 'Next photograph', exact: true });
    const stage = gallery.locator('.ee-gallery-stage');
    const before = await stage.boundingBox();
    await next.click();
    await gallery.locator('.ee-gallery-outgoing').waitFor({ state: 'attached' });
    const overlay = gallery.locator('.ee-gallery-outgoing');
    assert.equal(await overlay.getAttribute('aria-hidden'), 'true');
    assert.equal(await overlay.getAttribute('inert'), '');
    assert.equal(await overlay.evaluate((el) => getComputedStyle(el).animationDuration), '0.42s');
    assert.equal(await gallery.locator('.ee-gallery-controls').count(), 1);
    await overlay.waitFor({ state: 'detached' });
    const after = await stage.boundingBox();
    assert.ok(Math.abs(before.height - after.height) < 1);
    assert.equal(await next.evaluate((el) => el === document.activeElement), true);
    await next.click({ clickCount: 3, delay: 10 });
    assert.ok((await gallery.locator('.ee-gallery-outgoing').count()) <= 1);
    await gallery.locator('.ee-gallery-outgoing').waitFor({ state: 'detached' });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await next.click();
    assert.equal(await gallery.locator('.ee-gallery-outgoing').count(), 0);
    assert.equal(await gallery.getAttribute('data-playing'), 'false');
});

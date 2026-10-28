import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';

const enabled = Boolean(process.env.QA_CMS_EDITOR_INFO && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let info, browser, context, page, artifacts, publicUrl;
const errors = [];
before(async () => {
    if (!enabled) return;
    info = JSON.parse(await readFile(process.env.QA_CMS_EDITOR_INFO, 'utf8'));
    assert.match(info.origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    assert.match(info.root, /rsvp-cms-editor-/);
    assert.equal(info.publishing, true);
    artifacts = join(info.root, 'qa-del03b1');
    await mkdir(artifacts, { recursive: true });
    publicUrl = info.origin + '/w/synthetic-editor-preview';
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
beforeEach(async () => {
    if (!enabled) return;
    errors.length = 0;
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    context.on('page', (tab) => tab.on('pageerror', (error) => errors.push(error.message)));
    page = await context.newPage();
    page.setDefaultTimeout(15000);
    await page.goto(info.origin + '/login');
    await page.getByLabel('Email address').fill(info.owner.email);
    await page.getByLabel('Password', { exact: true }).fill(info.owner.password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
    await openEditor();
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
const frame = () => page.frameLocator('iframe[title="Wedding website preview"]');
async function enterInvitation(tab) {
    await tab.locator('.ee-photobooth, [data-ee-phase="landed"]').first().waitFor();
    const open = tab.getByRole('button', { name: 'Open Invitation', exact: true });
    if (await open.isVisible()) await open.click();
}
async function useWebsiteLoader() {
    // The accepted Photobooth Open intentionally bypasses network gates. Test the ordinary loader with that optional intro off.
    await page.getByRole('button', { name: 'Welcome', exact: true }).click();
    const opening = page.getByLabel('Photobooth opening', { exact: true });
    if (await opening.isChecked()) {
        await opening.uncheck();
        await save();
        await publish();
    }
}
async function openEditor() {
    await page.goto(info.previewUrl);
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await page.getByText('Preview is up to date', { exact: true }).waitFor();
}
async function save() {
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText('All changes saved', { exact: false }).waitFor();
}
async function publish() {
    await page.getByRole('button', { name: /^(Publish|Republish)$/ }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /^Confirm (Publish|Republish)$/ })
        .click();
    await page.getByRole('button', { name: 'Published', exact: true }).waitFor();
    assert.equal(page.url(), info.previewUrl);
}
async function props() {
    const version = await page.locator('#app').evaluate((el) => JSON.parse(el.dataset.page).version);
    const result = await context.request.get(info.previewUrl, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version } });
    assert.equal(result.status(), 200);
    return (await result.json()).props;
}

run('DEL03b1 fresh editor, library and published website contain no default photographs or placeholder images', async () => {
    assert.equal(await page.getByRole('button', { name: 'Reload saved draft', exact: true }).count(), 0);
    assert.equal(await page.getByRole('button', { name: 'Publish', exact: true }).isDisabled(), true);
    await frame().locator('[data-ee-phase="landed"]').waitFor();
    assert.equal(await frame().locator('img, .ee-photo-fallback').count(), 0);
    const initial = await props();
    assert.deepEqual(initial.initialImages, []);
    assert.deepEqual(initial.initialDocument.content.sections.find((s) => s.type === 'gallery').data.items, []);
    const library = await context.request.get(info.origin + initial.endpoints.images);
    assert.deepEqual((await library.json()).images, []);
    await page.getByRole('button', { name: 'Gallery', exact: true }).click();
    await page.getByText('Make this space yours.', { exact: false }).waitFor();
    await save();
    await publish();
    const visitor = await context.newPage();
    await visitor.goto(publicUrl);
    await enterInvitation(visitor);
    await visitor.locator('[data-ee-phase="landed"]').waitFor();
    assert.equal(await visitor.locator('img, .ee-photo-fallback').count(), 0);
    await visitor.screenshot({ path: join(artifacts, 'fresh-published-1440.png'), fullPage: true });
    await visitor.setViewportSize({ width: 390, height: 844 });
    await visitor.screenshot({ path: join(artifacts, 'fresh-published-390.png'), fullPage: true });
});

run('DEL03b1 editor publish/republish requires saved state, supports cancel and preserves immutable live content', async () => {
    await page.getByRole('button', { name: 'Our Story', exact: true }).click();
    await page.getByLabel('Story heading', { exact: true }).fill('Private polish story');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1', { exact: true }).fill('Only publish after saving and confirmation.');
    assert.equal(await page.getByRole('button', { name: /^(Republish|Published)$/ }).isDisabled(), true);
    const visitor = await context.newPage();
    await visitor.goto(publicUrl);
    await enterInvitation(visitor);
    assert.equal(await visitor.getByText('Private polish story', { exact: true }).count(), 0);
    await save();
    await visitor.reload();
    assert.equal(await visitor.getByText('Private polish story', { exact: true }).count(), 0);
    await page.getByRole('button', { name: 'Republish', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Keep editing', exact: true }).click();
    await visitor.reload();
    assert.equal(await visitor.getByText('Private polish story', { exact: true }).count(), 0);
    await publish();
    await visitor.reload();
    await visitor.getByRole('heading', { name: 'Private polish story', exact: true }).waitFor();
    await page.screenshot({ path: join(artifacts, 'editor-published.png'), fullPage: true });
});

run('DEL03b1 stale editor publication fails accessibly without discarding local content', async () => {
    await page.getByRole('button', { name: 'Our Story', exact: true }).click();
    await page.getByLabel('Story heading', { exact: true }).fill('Saved local polish');
    await save();
    await page.getByRole('button', { name: 'Republish', exact: true }).click();
    const current = await props();
    current.initialDocument.content.sections.find((s) => s.type === 'story').data.heading = 'Saved in another window';
    const changed = await context.request.put(info.origin + current.endpoints.draft, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': current.csrfToken },
        data: { document: current.initialDocument, draft_version: current.draftVersion },
    });
    assert.equal(changed.status(), 200);
    await page.getByRole('dialog').getByRole('button', { name: 'Confirm Republish', exact: true }).click();
    await page.getByRole('alert').filter({ hasText: 'changed' }).waitFor();
    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'Saved local polish');
    await page.getByRole('dialog').getByRole('button', { name: 'Keep editing', exact: true }).click();
});

run('DEL03b1 bootstrap and image readiness overlays prevent incomplete display, then land and reanchor the bird', async () => {
    await useWebsiteLoader();
    const current = await props();
    const png = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 100;
        canvas.height = 120;
        const brush = canvas.getContext('2d');
        brush.fillStyle = '#aab9ad';
        brush.fillRect(0, 0, 100, 120);
        return canvas.toDataURL('image/png').split(',')[1];
    });
    const uploaded = await context.request.post(info.origin + current.endpoints.upload, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': current.csrfToken },
        multipart: { image: { name: 'owner-upload.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') } },
    });
    assert.equal(uploaded.status(), 201);
    const { image } = await uploaded.json();
    current.initialDocument.content.photo_slots.hero = { asset_id: image.id, alt: 'Actual owner upload', focal: 'center', crop: 'natural' };
    const saved = await context.request.put(info.origin + current.endpoints.draft, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': current.csrfToken },
        data: { document: current.initialDocument, draft_version: current.draftVersion },
    });
    assert.equal(saved.status(), 200);
    await openEditor();
    await publish();
    const visitor = await context.newPage();
    await visitor.emulateMedia({ reducedMotion: 'no-preference' });
    let resumeImage, imagePaused;
    const waitingImage = new Promise((resolve) => {
        imagePaused = resolve;
    });
    const releaseImage = new Promise((resolve) => {
        resumeImage = resolve;
    });
    await visitor.route('**/media/*', async (route) => {
        imagePaused();
        await releaseImage;
        await route.continue();
    });
    let resumeModule, modulePaused;
    const paused = new Promise((resolve) => {
        modulePaused = resolve;
    });
    await visitor.route('**/public-enchanted-*.js', async (route) => {
        await new Promise((resolve) => {
            resumeModule = resolve;
            modulePaused();
        });
        await route.continue();
    });
    const navigation = visitor.goto(publicUrl, { waitUntil: 'domcontentloaded' });
    await paused;
    await visitor.locator('.ee-bootstrap-loader').waitFor({ state: 'visible' });
    assert.equal(await visitor.locator('.ee-server-content').isVisible(), false);
    await visitor.screenshot({ path: join(artifacts, 'bootstrap-loader.png') });
    resumeModule();
    await navigation;
    await waitingImage;
    await visitor.locator('[data-ee-phase="loading"]').waitFor();
    assert.equal(await visitor.locator('.ee-loaded-content').getAttribute('inert'), '');
    assert.equal(await visitor.locator('.ee-hummingbird').evaluate((el) => getComputedStyle(el).animationName), 'ee-bird-hover');
    await visitor.locator('.ee-hummingbird').evaluate((element) => {
        window.__loadingWings = [...element.querySelectorAll('.ee-bird-wing')].map((wing) => wing.getAnimations()[0]);
    });
    await visitor.screenshot({ path: join(artifacts, 'image-loader.png') });
    resumeImage();
    await visitor.locator('[data-ee-phase="landed"]').waitFor();
    assert.equal(await visitor.locator('.ee-loading-veil').isVisible(), false);
    assert.match(await visitor.locator('.ee-hummingbird').getAttribute('data-orientation'), /^(LEFT|RIGHT)$/);
    assert.deepEqual(
        await visitor
            .locator('.ee-hummingbird')
            .evaluate((element) =>
                [...element.querySelectorAll('.ee-bird-wing')].map(
                    (wing, index) => wing.getAnimations()[0] === window.__loadingWings[index] && wing.getAnimations()[0].playState === 'running',
                ),
            ),
        [true, true],
    );
    for (const width of [1440, 390]) {
        await visitor.setViewportSize({ width, height: 1000 });
        await visitor.waitForFunction(() => {
            const bird = document.querySelector('.ee-hummingbird');
            const beak = bird.querySelector('[data-bird-beak]').getBoundingClientRect();
            const point = document.querySelector('[data-landing-id="section:invitation"] circle').getBoundingClientRect();
            const tipX = bird.dataset.orientation === 'RIGHT' ? beak.right : beak.left;
            return Math.hypot(tipX - point.x - point.width / 2, beak.top - point.y - point.height / 2) < 6;
        });
    }
});

run('DEL03b1 reduced motion disables loader flight/hover; failed images settle to fallback; desktop nav scrolls smoothly', async () => {
    const visitor = await context.newPage();
    await visitor.route('**/media/*', (route) => route.abort('failed'));
    await visitor.goto(publicUrl);
    await enterInvitation(visitor);
    await visitor.locator('[data-ee-phase="landed"]').waitFor();
    assert.equal(await visitor.locator('.ee-photo-fallback').count(), 1);
    assert.equal(await visitor.locator('.ee-hummingbird').evaluate((el) => getComputedStyle(el).animationName), 'none');
    assert.equal(await visitor.locator('.ee-loading-bird').evaluate((el) => getComputedStyle(el).transitionDuration), '0s');
    await visitor.unroute('**/media/*');
    await visitor.addInitScript(() => {
        const scroll = window.scrollTo.bind(window);
        window.__scrollOptions = [];
        window.scrollTo = (options) => {
            window.__scrollOptions.push(options);
            scroll(options);
        };
    });
    await visitor.emulateMedia({ reducedMotion: 'no-preference' });
    await visitor.reload();
    await visitor.locator('[data-ee-phase="landed"]').waitFor();
    await visitor.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Our story', exact: true }).click();
    assert.equal(await visitor.evaluate(() => window.__scrollOptions.at(-1).behavior), 'smooth');
    assert.equal(await visitor.locator('#our-story').evaluate((el) => el === document.activeElement), true);
    assert.ok(visitor.url().endsWith('#our-story'));
    await visitor.emulateMedia({ reducedMotion: 'reduce' });
    await visitor.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Invitation', exact: true }).click();
    assert.equal(await visitor.evaluate(() => window.__scrollOptions.at(-1).behavior), 'instant');
    const withoutScripts = await browser.newContext({ javaScriptEnabled: false });
    const fallback = await withoutScripts.newPage();
    await fallback.goto(publicUrl);
    assert.equal(await fallback.locator('.ee-bootstrap-loader').isVisible(), false);
    assert.equal(await fallback.getByRole('heading', { name: 'Alex & Jamie' }).count(), 1);
    await withoutScripts.close();
});

run('DEL03b1 live and saved private previews wait for their first photograph and keep reduced-motion loading static', async () => {
    let release;
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    await page.route('**/cms-preview/images/*', async (route) => {
        await gate;
        await route.continue();
    });
    await page.goto(info.previewUrl, { waitUntil: 'domcontentloaded' });
    await frame().locator('[data-ee-phase="loading"]').waitFor();
    assert.equal(await frame().locator('.ee-loaded-content').getAttribute('inert'), '');
    assert.equal(
        await frame()
            .locator('.ee-hummingbird')
            .evaluate((el) => getComputedStyle(el).animationName),
        'none',
    );
    release();
    await frame().locator('[data-ee-phase="landed"]').waitFor();
    await page.screenshot({ path: join(artifacts, 'editor-ready.png'), fullPage: true });
    const current = await props();
    const saved = await context.newPage();
    let releaseSaved;
    const savedGate = new Promise((resolve) => {
        releaseSaved = resolve;
    });
    await saved.route('**/cms-preview/images/*', async (route) => {
        await savedGate;
        await route.continue();
    });
    await saved.goto(info.origin + current.endpoints.preview, { waitUntil: 'domcontentloaded' });
    await saved.locator('[data-ee-phase="loading"]').waitFor();
    assert.equal(await saved.locator('.ee-loaded-content').getAttribute('inert'), '');
    releaseSaved();
    await saved.locator('[data-ee-phase="landed"]').waitFor();
    assert.equal(await saved.locator('.ee-loading-veil').isVisible(), false);
});

run('DEL03b1 published content remains covered until fonts settle and script failure leaves readable server content', async () => {
    await useWebsiteLoader();
    const visitor = await context.newPage();
    let release;
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    await visitor.route('**/*.ttf', async (route) => {
        await gate;
        await route.continue();
    });
    await visitor.goto(publicUrl, { waitUntil: 'domcontentloaded' });
    await visitor.locator('[data-ee-phase="loading"]').waitFor();
    assert.equal(await visitor.locator('.ee-loaded-content').isVisible(), false);
    release();
    await visitor.locator('[data-ee-phase="landed"]').waitFor();
    const failed = await context.newPage();
    await failed.route('**/public-enchanted-*.js', (route) => route.abort('failed'));
    await failed.goto(publicUrl);
    await failed.locator('.ee-server-content').waitFor({ state: 'visible' });
    assert.equal(await failed.locator('.ee-bootstrap-loader').isVisible(), false);
});

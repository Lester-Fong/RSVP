import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { after, before, test } from 'node:test';
import { createCmsEditorPreview } from './cms-editor-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let host, browser, artifacts;
before(async () => {
    if (!enabled) return;
    host = await createCmsEditorPreview({ publishing: true, personal: true });
    assert.notEqual(new URL(host.origin).port, '8000');
    artifacts = join(host.root, 'qa-photobooth');
    await mkdir(artifacts);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const owner = await browser.newContext({ reducedMotion: 'reduce' });
    try {
        const page = await owner.newPage();
        await page.goto(host.origin + '/login');
        await page.getByLabel('Email address').fill(host.owner.email);
        await page.getByLabel('Password', { exact: true }).fill(host.owner.password);
        await page.getByRole('button', { name: 'Log in', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await page.goto(host.previewUrl);
        await page.getByLabel('Photobooth opening', { exact: true }).check();
        for (const [section, label, photo, width, height, frame, crop] of [
            ['Welcome', 'Main wedding photo', 'hero.jpg', 600, 900, 'fit', 'natural'],
            ['Wedding details', 'Wedding details photo', 'couple-closeup.jpg', 900, 600, 'fit', 'portrait'],
            ['Our Story', 'Our Story photo', 'story-photo.jpg', 600, 600, 'fill', 'square'],
        ]) {
            await page.getByRole('navigation', { name: 'Editor sections' }).getByRole('button', { name: section, exact: true }).click();
            await page.getByRole('group', { name: label, exact: true }).getByRole('button', { name: 'Choose photo', exact: true }).click();
            const dialog = page.getByRole('dialog');
            await page.waitForFunction(() => {
                const input = document.querySelector('[role=dialog] input[type=file]');
                return input && !input.disabled;
            });
            const uploaded = page.waitForResponse(
                (response) => response.request().method() === 'POST' && response.url() === host.previewUrl + '/images',
            );
            const source = await readFile(resolve('storage/app/private/dev-preview/enchanted-editorial/' + photo));
            const resized = await page.evaluate(
                async ({ source, width, height }) => {
                    const image = new Image();
                    image.src = 'data:image/jpeg;base64,' + source;
                    await image.decode();
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const scale = Math.max(width / image.naturalWidth, height / image.naturalHeight);
                    canvas
                        .getContext('2d')
                        .drawImage(
                            image,
                            (width - image.naturalWidth * scale) / 2,
                            (height - image.naturalHeight * scale) / 2,
                            image.naturalWidth * scale,
                            image.naturalHeight * scale,
                        );
                    return canvas.toDataURL('image/jpeg').split(',')[1];
                },
                { source: source.toString('base64'), width, height },
            );
            await dialog.locator('input[type=file]').setInputFiles({ name: photo, mimeType: 'image/jpeg', buffer: Buffer.from(resized, 'base64') });
            const response = await uploaded;
            assert.equal(response.status(), 201);
            const { image } = await response.json();
            await dialog
                .getByRole('button')
                .filter({ has: page.locator(`img[src="${image.preview_url}"]`) })
                .click();
            await dialog.waitFor({ state: 'hidden' });
            const field = page.getByRole('group', { name: label, exact: true });
            await field.getByLabel('Photo frame', { exact: true }).selectOption(frame);
            await field.getByLabel('Photo crop').selectOption(crop);
        }
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('All changes saved', { exact: false }).waitFor();
        await page.reload();
        assert.equal(await page.getByLabel('Photobooth opening', { exact: true }).isChecked(), true);
        assert.equal(await page.locator('.ee-photobooth').count(), 0, 'Private editor never intercepts with a public intro.');
        await page.getByRole('button', { name: 'Republish', exact: true }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Confirm Republish', exact: true }).click();
        await page.getByRole('button', { name: 'Published', exact: true }).waitFor();
    } finally {
        await owner.close();
    }
});
after(async () => {
    await browser?.close();
    await host?.close();
});
const run = (name, callback) => test(name, { skip: !enabled, timeout: 45000 }, callback);
const publicUrl = () => host.origin + '/w/synthetic-editor-preview';
const assertHeroOutline = async (page) => {
    const outline = await page.locator('#invitation').evaluate((element) => getComputedStyle(element).outlineStyle);
    assert.equal(outline, 'none', 'Hero keeps focus without a presentation outline.');
};
async function assertFilledFrames(page) {
    await page.waitForFunction(() =>
        [...document.querySelectorAll('.ee-booth-print img')].every((image) => image.complete && image.naturalWidth > 0),
    );
    const frames = await page.locator('.ee-booth-print').evaluateAll((elements) =>
        elements.map((frame) => {
            const image = frame.querySelector('img');
            return {
                frame: [frame.clientWidth, frame.clientHeight],
                crop: [image.parentElement.clientWidth, image.parentElement.clientHeight],
                image: [image.clientWidth, image.clientHeight],
                fit: getComputedStyle(image).objectFit,
                ratio: image.naturalWidth / image.naturalHeight,
            };
        }),
    );
    assert.equal(frames.length, 3);
    assert.deepEqual(frames.map((frame) => frame.ratio).sort(), [2 / 3, 1, 1.5], 'Real uploaded portrait, square and landscape assets.');
    for (const frame of frames) {
        assert.deepEqual(frame.frame, frames[0].frame, 'Identical print viewports.');
        assert.deepEqual(frame.crop, frame.frame, 'No inscribed crop wrapper leaves blank bands.');
        assert.deepEqual(frame.image, frame.frame, 'Image fills the entire viewport.');
        assert.equal(frame.fit, 'cover', 'Cover crops without distortion regardless of saved site framing.');
    }
}

for (const [device, width, height] of [
    ['desktop', 1440, 1000],
    ['mobile', 390, 844],
]) {
    run(`Photobooth ${device}: published photos print in stages, keyboard opens into the same bird, session revisits skip`, async () => {
        const context = await browser.newContext({ viewport: { width, height }, recordVideo: { dir: artifacts } });
        const page = await context.newPage(),
            errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        const video = page.video();
        try {
            await page.goto(publicUrl(), { waitUntil: 'domcontentloaded' });
            await page.locator('[data-print-state="printing"]').waitFor();
            await page.screenshot({ path: join(artifacts, `${device}-before-printing.png`) });
            assert.equal(await page.getByRole('button', { name: 'Open Invitation', exact: true }).isDisabled(), false);
            assert.equal(await page.locator('.ee-booth-print img').count(), 3);
            assert.equal(new Set(await page.locator('.ee-booth-print img').evaluateAll((images) => images.map((image) => image.src))).size, 3);
            assert.equal(await page.locator('.ee-loaded-content').getAttribute('inert'), '');
            assert.equal(await page.locator('.ee-hummingbird').count(), 1);
            assert.equal(await page.locator('.ee-loading-bird').isVisible(), false, 'The bird stays out of the booth scene.');
            await page.locator('.ee-loading-bird').evaluate((element) => (element.dataset.qaIdentity = 'same-bird'));
            const early = await page.locator('.ee-booth-strip').evaluate((element) => getComputedStyle(element).transform);
            await page.waitForTimeout(1400);
            await assertFilledFrames(page);
            assert.notEqual(await page.locator('.ee-booth-strip').evaluate((element) => getComputedStyle(element).transform), early);
            await page.screenshot({ path: join(artifacts, `${device}-printing.png`) });
            await page.locator('[data-print-state="settled"]').waitFor();
            await assertFilledFrames(page);
            await page.screenshot({ path: join(artifacts, `${device}-intro.png`) });
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            const open = page.getByRole('button', { name: 'Open Invitation', exact: true });
            await page.getByRole('button', { name: 'Skip animation', exact: false }).focus();
            await page.keyboard.press('Shift+Tab');
            assert.equal(await open.evaluate((element) => element === document.activeElement), true);
            assert.equal(
                await open.evaluate((element) => getComputedStyle(element).outlineStyle),
                'solid',
                'Keyboard controls retain a visible focus ring.',
            );
            await open.press('Enter');
            await page.locator('.ee-photobooth').waitFor({ state: 'detached' });
            await page.locator('[data-ee-phase="landed"]').waitFor();
            assert.equal(await page.locator('.ee-loading-bird[data-qa-identity="same-bird"]').count(), 1);
            assert.equal(await page.locator('#invitation').evaluate((element) => element === document.activeElement), true);
            await assertHeroOutline(page);
            assert.equal(
                await page.locator('.ee-hero-main img').evaluate((element) => getComputedStyle(element).objectFit),
                'contain',
                'Published website Fit preference remains intact.',
            );
            await page.screenshot({ path: join(artifacts, `${device}-hero.png`) });
            await page.reload();
            await page.locator('[data-ee-phase="landed"]').waitFor();
            assert.equal(await page.locator('.ee-photobooth').count(), 0);
            await assertHeroOutline(page);
            await page.goto(publicUrl() + '#invitation');
            await page.locator('[data-ee-phase="landed"]').waitFor();
            await page.locator('#invitation').focus();
            await assertHeroOutline(page);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
            if (video) await video.saveAs(join(artifacts, `${device}-intro-to-hero.webm`));
        }
    });
}

run('Photobooth Skip and Escape work before printing completes and clean scroll/focus without submitting RSVP', async () => {
    for (const action of ['Escape', 'Skip']) {
        const context = await browser.newContext();
        try {
            const page = await context.newPage();
            let submissions = 0;
            page.on('request', (request) => {
                if (request.method() === 'POST' && request.url().includes('/rsvp/')) submissions++;
            });
            await page.goto(host.owner.personal.representativeUrl);
            await page.getByRole('dialog').waitFor();
            if (action === 'Escape') await page.keyboard.press('Escape');
            else await page.getByRole('button', { name: 'Skip animation', exact: false }).click();
            await page.locator('.ee-photobooth').waitFor({ state: 'detached' });
            await page.locator('[data-ee-phase="landed"]').waitFor();
            await assertHeroOutline(page);
            assert.equal(await page.evaluate(() => document.body.style.overflow), '');
            assert.equal(await page.getByLabel('Maya Santos', { exact: true }).inputValue(), 'pending');
            assert.equal(submissions, 0);
        } finally {
            await context.close();
        }
    }
});

run('Photobooth immediate Open cancels a stalled-image entrance idempotently without a second loading gate', async () => {
    const context = await browser.newContext();
    try {
        const page = await context.newPage();
        let submissions = 0;
        await page.route('**/media/**', () => {});
        page.on('request', (request) => {
            if (request.method() === 'POST') submissions++;
        });
        await page.goto(publicUrl(), { waitUntil: 'domcontentloaded' });
        const open = page.getByRole('button', { name: 'Open Invitation', exact: true });
        await open.waitFor();
        assert.equal(await open.isEnabled(), true);
        await open.dblclick();
        await page.locator('.ee-photobooth').waitFor({ state: 'detached' });
        await page.locator('[data-ee-phase="landed"]').waitFor();
        assert.equal(await page.locator('.ee-loading-veil').isVisible(), false);
        assert.equal(await page.locator('.ee-loaded-content').getAttribute('inert'), null);
        assert.equal(await page.evaluate(() => document.body.style.overflow), '');
        assert.equal(submissions, 0);
    } finally {
        await context.close();
    }
});

run('Photobooth handles reduced-motion changes, backgrounding and Back/Forward without restarting', async () => {
    const context = await browser.newContext();
    try {
        const page = await context.newPage();
        await page.goto(publicUrl());
        await page.locator('[data-print-state="printing"]').waitFor();
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.locator('[data-print-state="settled"]').waitFor();
        assert.equal(await page.locator('.ee-booth-strip').evaluate((element) => element.getAnimations().length), 0);
        await page.getByRole('button', { name: 'Open Invitation', exact: true }).press('Space');
        await page.locator('.ee-photobooth').waitFor({ state: 'detached' });
        await page.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Our story', exact: true }).click();
        await page.goBack();
        await page.goForward();
        assert.equal(await page.locator('.ee-photobooth').count(), 0);
    } finally {
        await context.close();
    }
    const background = await browser.newContext();
    try {
        const page = await background.newPage();
        await page.goto(publicUrl());
        await page.locator('[data-print-state="printing"]').waitFor();
        await page.evaluate(() => {
            Object.defineProperty(document, 'hidden', { configurable: true, value: true });
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await page.locator('[data-print-state="settled"]').waitFor();
        await page.getByRole('button', { name: 'Open Invitation', exact: true }).click();
        await page.locator('.ee-photobooth').waitFor({ state: 'detached' });
    } finally {
        await background.close();
    }
});

run('Photobooth narrow phones, short laptops and landscape keep names, printed paper and actionable CTA apart', async () => {
    for (const [width, height] of [
        [320, 568],
        [1366, 650],
        [844, 390],
        [390, 844],
        [768, 1024],
    ]) {
        const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
        try {
            const page = await context.newPage();
            await page.goto(publicUrl());
            await page.locator('[data-print-state="settled"]').waitFor();
            const bounds = await page.evaluate(() => {
                const rect = (selector) => {
                    const r = document.querySelector(selector).getBoundingClientRect();
                    return { top: r.top, bottom: r.bottom, left: r.left, right: r.right };
                };
                return { names: rect('#ee-booth-title'), slot: rect('.ee-booth-slot'), paper: rect('.ee-booth-strip'), open: rect('.ee-booth-open') };
            });
            assert.ok(bounds.names.bottom < bounds.slot.top);
            assert.ok(bounds.paper.bottom < bounds.open.top, JSON.stringify({ width, height, bounds }));
            assert.ok(bounds.open.bottom <= height && bounds.open.left >= 0 && bounds.open.right <= width, JSON.stringify({ width, height, bounds }));
            assert.ok(bounds.paper.left >= 0 && bounds.paper.right <= width);
            await page.screenshot({ path: join(artifacts, `responsive-${width}-${height}.png`) });
        } finally {
            await context.close();
        }
    }
});

run('Photobooth deep links and public/personal session sharing preserve RSVP authority and generic privacy', async () => {
    const context = await browser.newContext();
    try {
        const page = await context.newPage();
        await page.goto(publicUrl() + '#our-story');
        await page.locator('[data-ee-phase="landed"]').waitFor();
        assert.equal(await page.locator('.ee-photobooth').count(), 0);
        await page.goto(host.owner.personal.representativeUrl + '#rsvp');
        await page.locator('[data-ee-phase="landed"]').waitFor();
        assert.equal(await page.locator('.ee-photobooth').count(), 0);
        await page.getByLabel('Maya Santos', { exact: true }).selectOption('attending');
        await page.getByLabel('Nico Santos', { exact: true }).selectOption('attending');
        await page.getByRole('button', { name: 'Save responses', exact: true }).click();
        await page.locator('[data-rsvp-confirmation="attending"]').waitFor();
        await page.goto(publicUrl());
        await page.locator('[data-ee-phase="landed"]').waitFor();
        assert.equal(await page.locator('.ee-photobooth').count(), 0);
        assert.doesNotMatch(await page.content(), /Maya Santos|Nico Santos|personal-rsvp-data/);
        await page.goto(host.owner.personal.representativeUrl);
        await page.locator('[data-ee-phase="landed"]').waitFor();
        assert.equal(await page.locator('.ee-photobooth').count(), 0);
        assert.equal(await page.getByLabel('Maya Santos', { exact: true }).inputValue(), 'attending');
    } finally {
        await context.close();
    }
});

run('Photobooth reduced motion, failed images and suppressed animation always offer an accessible exit', async () => {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    try {
        const page = await context.newPage();
        await page.route('**/media/**', (route) => route.abort());
        await page.goto(publicUrl());
        await page.locator('[data-print-state="settled"]').waitFor();
        assert.equal(await page.locator('.ee-booth-strip').evaluate((element) => element.getAnimations().length), 0);
        assert.equal(await page.locator('.ee-booth-print img').count(), 0);
        await page.setViewportSize({ width: 844, height: 390 });
        assert.ok(await page.getByRole('button', { name: 'Skip animation', exact: false }).isVisible());
        assert.ok(
            await page.locator('.ee-loading-bird').evaluate((element) => {
                const bounds = element.getBoundingClientRect();
                return bounds.left >= 0 && bounds.right <= innerWidth;
            }),
        );
        await page.getByRole('button', { name: 'Open Invitation', exact: true }).click();
        await page.locator('[data-ee-phase="landed"]').waitFor();
    } finally {
        await context.close();
    }
    const staticContext = await browser.newContext();
    try {
        const page = await staticContext.newPage();
        await page.goto(publicUrl());
        await page.addStyleTag({ content: '.ee-booth-strip{animation:none!important}' });
        await page.locator('[data-print-state="settled"]').waitFor();
        await page.getByRole('button', { name: 'Open Invitation', exact: true }).click();
        await page.locator('[data-ee-phase="landed"]').waitFor();
    } finally {
        await staticContext.close();
    }
});

run('Photobooth no-JS and unavailable session storage keep the published invitation directly accessible', async () => {
    for (const javaScriptEnabled of [false, true]) {
        const context = await browser.newContext({ javaScriptEnabled });
        try {
            if (javaScriptEnabled)
                await context.addInitScript(() => {
                    Storage.prototype.getItem = () => {
                        throw Error('Storage unavailable');
                    };
                });
            const page = await context.newPage();
            await page.goto(publicUrl());
            await page.getByRole('heading', { name: /Alex.*Jamie/ }).waitFor();
            assert.equal(await page.locator('.ee-photobooth').count(), 0);
            assert.equal(await page.locator('form').count(), 0);
        } finally {
            await context.close();
        }
    }
    console.log('Photobooth screenshots and motion: ' + artifacts);
});

run('Photobooth uses honest zero/one/two-photo published strips and keeps long published names usable on narrow screens', async () => {
    const owner = await browser.newContext();
    const editor = await owner.newPage();
    let original, version, csrf, originalFacts;
    const headers = () => ({ 'X-CSRF-TOKEN': csrf, Accept: 'application/json' });
    const overview = host.previewUrl.replace('/cms-preview', '');
    async function publish(summary) {
        const response = await owner.request.post(overview + '/publish', {
            headers: headers(),
            data: {
                draft_version: summary.draftVersion,
                publication_version: summary.publicationVersion,
                snapshot_token: summary.snapshotToken,
            },
        });
        assert.equal(response.status(), 200);
    }
    async function save(document) {
        const response = await owner.request.put(host.previewUrl + '/draft', { headers: headers(), data: { document, draft_version: version } });
        assert.equal(response.status(), 200);
        const saved = await response.json();
        version = saved.draft_version;
        await publish(saved.publication);
    }
    try {
        await editor.goto(host.origin + '/login');
        await editor.getByLabel('Email address').fill(host.owner.email);
        await editor.getByLabel('Password', { exact: true }).fill(host.owner.password);
        await editor.getByRole('button', { name: 'Log in', exact: true }).click();
        await editor.waitForURL('**/dashboard');
        await editor.goto(host.previewUrl);
        const props = await editor.locator('#app').evaluate((element) => JSON.parse(element.dataset.page).props);
        csrf = props.csrfToken;
        originalFacts = props.wedding;
        const saved = await (await owner.request.get(host.previewUrl + '/draft')).json();
        original = saved.document;
        version = saved.draft_version;
        for (const count of [2, 1, 0]) {
            const document = structuredClone(original);
            if (count < 3) document.content.photo_slots.story = null;
            if (count < 2) document.content.photo_slots.details = null;
            if (count < 1) document.content.photo_slots.hero = null;
            await save(document);
            const visitor = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 390, height: 844 } });
            try {
                const page = await visitor.newPage();
                await page.goto(publicUrl());
                await page.locator('[data-print-state="settled"]').waitFor();
                assert.equal(await page.locator('.ee-booth-print img').count(), count);
                assert.equal(await page.locator('.ee-booth-print').count(), Math.max(1, count));
                assert.equal(await page.getByRole('button', { name: 'Open Invitation', exact: true }).isEnabled(), true);
                await page.screenshot({ path: join(artifacts, `sparse-${count}-photos.png`) });
            } finally {
                await visitor.close();
            }
        }
        await save(original);
        const response = await owner.request.put(overview, {
            maxRedirects: 0,
            headers: headers(),
            data: {
                partner_one_name: 'Alexandra Marie Del Rosario',
                partner_two_name: 'Juan Miguel De Los Santos',
                wedding_date: originalFacts.wedding_date,
                wedding_time: originalFacts.wedding_time,
                timezone: props.timeZone,
            },
        });
        assert.equal(response.status(), 302);
        await editor.reload();
        await publish(await editor.locator('#app').evaluate((element) => JSON.parse(element.dataset.page).props.publication));
        const visitor = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 320, height: 568 } });
        try {
            const page = await visitor.newPage();
            await page.goto(publicUrl());
            await page.locator('[data-print-state="settled"]').waitFor();
            assert.match(await page.locator('#ee-booth-title').innerText(), /Alexandra Marie Del Rosario/);
            const open = await page.getByRole('button', { name: 'Open Invitation', exact: true }).boundingBox();
            assert.ok(open.y >= 0 && open.y + open.height <= 568);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await page.screenshot({ path: join(artifacts, 'long-names-320.png') });
        } finally {
            await visitor.close();
        }
    } finally {
        if (original && csrf) {
            const restored = await owner.request.put(overview, { headers: headers(), data: originalFacts, maxRedirects: 0 });
            assert.equal(restored.status(), 302);
            await save(original);
        }
        await owner.close();
    }
});

run('Photobooth bootstrap offers direct entry without a bird when the app module never arrives', async () => {
    for (const action of ['open', 'timeout']) {
        const context = await browser.newContext();
        try {
            const page = await context.newPage();
            await page.route('**/*.js', () => {});
            await page.goto(publicUrl(), { waitUntil: 'commit' });
            await page.locator('.ee-bootstrap-open').waitFor();
            assert.equal(await page.locator('.ee-bootstrap-loader svg').count(), 0);
            if (action === 'open') await page.locator('.ee-bootstrap-open').click();
            await page.locator('#ee-couple-names').waitFor();
            assert.equal(await page.locator('.ee-photobooth').count(), 0);
            assert.equal(await page.evaluate(() => document.documentElement.dataset.eeOpeningBypass), 'true');
        } finally {
            await context.close();
        }
    }
});

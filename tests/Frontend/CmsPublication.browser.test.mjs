import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';

const enabled = Boolean(process.env.QA_CMS_EDITOR_INFO && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let info, browser, context, anonymous, page, visitor, artifacts, overview, publicUrl;
const errors = [];
before(async () => {
    if (!enabled) return;
    info = JSON.parse(await readFile(process.env.QA_CMS_EDITOR_INFO, 'utf8'));
    assert.match(info.origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    assert.match(info.root, /rsvp-cms-editor-/);
    assert.equal(info.publishing, true, 'Use an explicitly publication-enabled disposable target.');
    overview = info.previewUrl.replace('/cms-preview', '');
    publicUrl = info.origin + '/w/synthetic-editor-preview';
    artifacts = join(info.root, 'qa-del03b');
    await mkdir(artifacts, { recursive: true });
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
beforeEach(async () => {
    if (!enabled) return;
    errors.length = 0;
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    anonymous = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    page = await context.newPage();
    visitor = await anonymous.newPage();
    for (const tab of [page, visitor]) {
        tab.setDefaultTimeout(15000);
        tab.on('pageerror', (error) => errors.push(error.message));
    }
    await page.goto(info.origin + '/login');
    await page.getByLabel('Email address').fill(info.owner.email);
    await page.getByLabel('Password', { exact: true }).fill(info.owner.password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
});
afterEach(async () => {
    if (!enabled) return;
    await context.close();
    await anonymous.close();
    assert.deepEqual(errors, []);
});
after(async () => {
    await browser?.close();
});
const run = (name, action) => test(name, { skip: !enabled }, action);
const section = (name) => page.getByRole('navigation', { name: 'Editor sections' }).getByRole('button', { name, exact: true }).click();
const source = async () => (await (await context.request.get(info.previewUrl + '/draft')).json()).document;
async function enterInvitation(tab) {
    await tab.locator('.ee-photobooth, [data-ee-phase="landed"]').first().waitFor();
    const open = tab.getByRole('button', { name: 'Open Invitation', exact: true });
    if (await open.isVisible()) await open.click();
}
async function openEditor() {
    await page.goto(info.previewUrl);
    await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
    await page.getByText('Preview content checked', { exact: true }).waitFor();
}
async function save() {
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText('All changes saved', { exact: false }).waitFor();
}
async function upload() {
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.waitForFunction(() => {
        const input = document.querySelector('[role=dialog] input[type=file]');
        return input && !input.disabled;
    });
    const png = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 640;
        canvas.height = 800;
        const brush = canvas.getContext('2d');
        const gradient = brush.createLinearGradient(0, 0, 640, 800);
        gradient.addColorStop(0, '#8c5d46');
        gradient.addColorStop(1, '#e6ceb3');
        brush.fillStyle = gradient;
        brush.fillRect(0, 0, 640, 800);
        return canvas.toDataURL('image/png').split(',')[1];
    });
    const response = page.waitForResponse((r) => r.url().endsWith('/cms-preview/images') && r.request().method() === 'POST');
    await page
        .getByRole('dialog')
        .locator('input[type=file]')
        .setInputFiles({ name: 'synthetic-review.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
    const uploaded = await response;
    assert.equal(uploaded.status(), 201);
    const { image } = await uploaded.json();
    await page.getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
    return image;
}
async function publish() {
    await page.goto(overview);
    const trigger = page.getByRole('button', { name: /^(Publish invitation|Republish changes)$/ });
    if ((await trigger.count()) && (await trigger.isEnabled())) {
        await trigger.click();
        const dialog = page.getByRole('dialog');
        await dialog.getByText('Only your saved draft is published.', { exact: false }).waitFor();
        await dialog.getByRole('button', { name: /^(Publish now|Republish)$/ }).click();
    }
    await page.getByRole('heading', { name: 'Published', exact: true }).waitFor();
}
async function ensurePublished() {
    await openEditor();
    const doc = await source();
    const gallery = doc.content.sections.find((s) => s.type === 'gallery');
    await section('Gallery');
    await page.getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).check();
    for (let count = gallery.data.items.length; count < 2; count++) await upload();
    if (!doc.content.sections.find((s) => s.type === 'story').data.paragraphs.length) {
        await section('Our Story');
        await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
        await page.getByLabel('Story paragraph 1').fill('Published narrative');
    }
    if (await page.getByRole('button', { name: 'Save Draft', exact: true }).isEnabled()) await save();
    await publish();
}
async function capture(tab, name) {
    await tab.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all(
            [...document.images].map((image) => {
                image.loading = 'eager';
                return image.decode().catch(() => {});
            }),
        );
    });
    await tab.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth + 1);
    assert.ok(await tab.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await tab.screenshot({ path: join(artifacts, name + '.png'), fullPage: true });
}

run('DEL03b saved draft publishes, remains immutable during unsaved and saved edits, then changes only on explicit republish', async () => {
    assert.equal((await anonymous.request.get(publicUrl)).status(), 404);
    await openEditor();
    await section('Countdown');
    await page.getByRole('region', { name: 'Countdown editing' }).getByLabel('Show section', { exact: true }).check();
    await page.getByLabel('Countdown heading', { exact: true }).fill('A little while until forever');
    await page.getByLabel('Countdown subtitle', { exact: true }).fill('We cannot wait to celebrate together.');
    await section('Our Story');
    await page.getByLabel('Story heading', { exact: true }).fill('Our published story');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1').fill('First published story');
    await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
    await page.getByLabel('Milestone title', { exact: true }).fill('Our first adventure');
    await page.getByLabel('Milestone date precision').selectOption('year');
    await page.getByLabel('Milestone year').fill('2020');
    await section('RSVP information');
    await page.getByLabel('RSVP information message', { exact: true }).fill('Please follow the RSVP instructions sent by the couple.');
    await section('Gallery');
    const hero = await upload();
    await upload();
    await page.getByLabel('Caption 1', { exact: true }).fill('First public caption');
    await page.getByLabel('Photo frame', { exact: true }).first().selectOption('fit');
    await page.getByLabel('Photo frame', { exact: true }).last().selectOption('fill');
    for (const name of ['Welcome', 'Wedding details', 'Our Story']) {
        await section(name);
        await page.getByRole('button', { name: 'Choose photo', exact: true }).first().click();
        await page
            .getByRole('dialog')
            .getByRole('button')
            .filter({ has: page.locator('img[src="' + hero.preview_url + '"]') })
            .click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    await save();
    await publish();
    await capture(page, 'owner-published-1440');
    await visitor.goto(publicUrl);
    await enterInvitation(visitor);
    await visitor.getByText('First published story', { exact: true }).waitFor();
    await visitor.getByRole('heading', { name: 'A little while until forever', exact: true }).waitFor();
    assert.equal(await visitor.locator('#countdown .ee-landing-marker').getAttribute('data-landing-id'), 'section:countdown');
    assert.equal(await visitor.locator('.ee-gallery-slide').count(), 2);
    assert.equal(await visitor.locator('form, input, #personal-rsvp-data, [data-page]').count(), 0);
    for (const width of [1440, 390]) {
        await visitor.setViewportSize({ width, height: 1000 });
        await capture(visitor, 'published-' + width);
    }
    await page.getByRole('link', { name: 'Edit wedding website', exact: true }).click();
    await section('Countdown');
    await page.getByLabel('Countdown heading', { exact: true }).fill('Our favorite day is nearly here');
    await section('Our Story');
    await page.getByLabel('Story paragraph 1').fill('Saved after publication');
    await visitor.reload();
    await visitor.getByText('First published story', { exact: true }).waitFor();
    await visitor.getByRole('heading', { name: 'A little while until forever', exact: true }).waitFor();
    assert.equal(await visitor.getByRole('heading', { name: 'Our favorite day is nearly here', exact: true }).count(), 0);
    assert.equal(await visitor.getByText('Saved after publication', { exact: true }).count(), 0);
    const noCsrf = await context.request.post(overview + '/publish', { data: {} });
    assert.equal(noCsrf.status(), 419);
    await save();
    await visitor.reload();
    await visitor.getByText('First published story', { exact: true }).waitFor();
    await visitor.getByRole('heading', { name: 'A little while until forever', exact: true }).waitFor();
    await page.goto(overview);
    await page.getByRole('button', { name: 'Republish changes', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click();
    await visitor.reload();
    await visitor.getByText('First published story', { exact: true }).waitFor();
    await publish();
    await visitor.reload();
    await visitor.getByText('Saved after publication', { exact: true }).waitFor();
    await visitor.getByRole('heading', { name: 'Our favorite day is nearly here', exact: true }).waitFor();
    assert.equal(await visitor.getByText('First published story', { exact: true }).count(), 0);
});

run('DEL04.2 published Guest Guide exposes one accessible section, safe generic RSVP guidance, and a static no-JavaScript fallback', async () => {
    await openEditor();
    await section('Guest Guide');
    const region = page.getByRole('region', { name: 'Guest Guide editing' });
    await region.getByLabel('Show section', { exact: true }).check();
    await region.getByLabel('Show frequently asked questions').check();
    await region.getByLabel('Show Attire Guide').check();
    await region.getByLabel('Dress code', { exact: true }).fill('Garden formal');
    for (let index = 0; index < 5; index++) await region.getByRole('button', { name: 'Add color', exact: true }).click();
    const saveButton = page.getByRole('button', { name: 'Save Draft', exact: true });
    if (await saveButton.isEnabled()) {
        await saveButton.click();
        await page.getByText(/Draft saved privately/).waitFor();
    } else {
        await page.getByText('All changes saved', { exact: false }).waitFor();
    }
    await publish();

    await visitor.goto(publicUrl);
    await enterInvitation(visitor);
    await visitor.setViewportSize({ width: 1440, height: 900 });
    const guideNav = visitor.locator('nav.ee-navigation a[href="#guest-guide"]');
    assert.equal(await guideNav.count(), 1);
    assert.equal(await guideNav.getAttribute('href'), '#guest-guide');
    await visitor.getByRole('heading', { name: 'Garden formal', exact: true }).waitFor();
    assert.equal(await visitor.locator('#guest-guide [data-landing-id="section:guest-guide"]').count(), 1);
    assert.ok((await visitor.locator('#guest-guide details').count()) >= 3);
    const rsvpFaq = visitor.locator('#guest-guide details').filter({ hasText: 'How do I RSVP?' });
    await rsvpFaq.locator('summary').focus();
    await rsvpFaq.locator('summary').press('Enter');
    await visitor.getByText('personal invitation link shared with you', { exact: false }).waitFor();
    assert.equal(await rsvpFaq.getByRole('link', { name: 'Open your personal RSVP', exact: true }).count(), 0);
    assert.equal(await visitor.locator('a[href^="/rsvp/"]').count(), 0);

    for (const width of [1440, 390]) {
        await visitor.setViewportSize({ width, height: 844 });
        await visitor.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth + 1);
        assert.ok(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        assert.ok(
            await visitor.locator('#guest-guide .ee-guide-swatches').evaluate((list) => {
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
        await visitor.screenshot({ path: join(artifacts, `del04-2-guide-public-${width}.png`), fullPage: true });
    }
    await visitor.getByRole('button', { name: 'Open wedding menu', exact: true }).click();
    const mobileGuideNav = visitor.locator('nav.ee-navigation a[href="#guest-guide"]');
    await mobileGuideNav.click();
    await visitor.waitForFunction(() => location.hash === '#guest-guide');
    assert.equal(await visitor.getByRole('button', { name: 'Open wedding menu', exact: true }).getAttribute('aria-expanded'), 'false');

    if (info.personal) {
        await visitor.goto(info.owner.personal.representativeUrl);
        const personalFaq = visitor.locator('#guest-guide details').filter({ hasText: 'How do I RSVP?' });
        await personalFaq.locator('summary').click();
        const personalLink = personalFaq.getByRole('link', { name: 'Open your personal RSVP', exact: true });
        assert.equal(await personalLink.getAttribute('href'), '#rsvp');
        await personalLink.click();
        await visitor.waitForFunction(() => location.hash === '#rsvp');
        await visitor.screenshot({ path: join(artifacts, 'del04-2-guide-personal-390.png'), fullPage: true });
    }
    const noJs = await browser.newContext({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false, reducedMotion: 'reduce' });
    try {
        const staticPage = await noJs.newPage();
        assert.equal((await staticPage.goto(publicUrl)).status(), 200);
        assert.equal(await staticPage.locator('nav.ee-navigation a[href="#guest-guide"]').count(), 1);
        const staticFaq = staticPage.locator('#guest-guide details').filter({ hasText: 'How do I RSVP?' });
        assert.equal(await staticFaq.locator('summary').count(), 1);
        await staticFaq.locator('summary').press('Enter');
        assert.equal(await staticFaq.getByText('personal invitation link shared with you', { exact: false }).count(), 1);
        assert.ok(await staticPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await staticPage.screenshot({ path: join(artifacts, 'del04-2-guide-public-no-js-390.png'), fullPage: true });
    } finally {
        await noJs.close();
    }
});

run(
    'DEL03b hidden and historical media stay private, unpublish denies page and images, then an explicit republish restores only visible media',
    async () => {
        await ensurePublished();
        await visitor.goto(publicUrl);
        await enterInvitation(visitor);
        const photo = await visitor.locator('.ee-gallery-slide img').last().getAttribute('src');
        assert.equal((await anonymous.request.get(info.origin + photo)).status(), 200);
        await openEditor();
        await section('Gallery');
        await page.getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).uncheck();
        await save();
        assert.equal((await anonymous.request.get(info.origin + photo)).status(), 200);
        await publish();
        await visitor.reload();
        assert.equal(await visitor.locator('#our-photos').count(), 0);
        assert.equal((await anonymous.request.get(info.origin + photo)).status(), 404);
        assert.equal((await source()).content.sections.find((s) => s.type === 'gallery').data.items.length, 2);
        await page.getByRole('button', { name: 'Website actions', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Unpublish invitation', exact: true }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Unpublish invitation', exact: true }).click();
        await page.getByRole('heading', { name: 'Draft', exact: true }).waitFor();
        assert.equal((await anonymous.request.get(publicUrl)).status(), 404);
        assert.equal((await anonymous.request.get(info.origin + photo)).status(), 404);
        await ensurePublished();
        assert.equal((await anonymous.request.get(publicUrl)).status(), 200);
        assert.equal((await anonymous.request.get(info.origin + photo)).status(), 200);
    },
);

run(
    'DEL03b mobile section navigation closes the menu, updates hashes and focus, scrolls smoothly, offsets a sticky header and respects reduced motion',
    async () => {
        await ensurePublished();
        await visitor.addInitScript(() => {
            window.__sectionScroll = [];
            const scroll = window.scrollTo.bind(window);
            window.scrollTo = (...args) => {
                window.__sectionScroll.push(args[0]);
                return scroll(...args);
            };
        });
        await visitor.emulateMedia({ reducedMotion: 'no-preference' });
        await visitor.goto(publicUrl);
        await enterInvitation(visitor);
        const menu = visitor.getByRole('button', { name: 'Open wedding menu', exact: true });
        await menu.click();
        await visitor.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Photos', exact: true }).click();
        assert.equal(await menu.getAttribute('aria-expanded'), 'false');
        assert.ok(visitor.url().endsWith('#our-photos'));
        assert.equal(await visitor.locator('#our-photos').evaluate((el) => el === document.activeElement), true);
        assert.equal(await visitor.evaluate(() => window.__sectionScroll.at(-1).behavior), 'smooth');
        await visitor.waitForFunction(() => Math.abs(document.getElementById('our-photos').getBoundingClientRect().top - 28) < 3);
        await visitor.emulateMedia({ reducedMotion: 'reduce' });
        await visitor.locator('.ee-header').evaluate((el) => {
            el.style.position = 'sticky';
            el.style.top = '0px';
        });
        await menu.click();
        await visitor.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Our story', exact: true }).click();
        assert.equal(await visitor.evaluate(() => window.__sectionScroll.at(-1).behavior), 'instant');
        assert.ok(visitor.url().endsWith('#our-story'));
        assert.ok(
            await visitor.evaluate(
                () =>
                    document.getElementById('our-story').getBoundingClientRect().top >=
                    document.querySelector('.ee-header').getBoundingClientRect().bottom - 2,
            ),
        );
        await visitor.locator('.ee-header').evaluate((el) => {
            el.style.position = '';
            el.style.top = '';
        });
        await visitor.goBack();
        assert.ok(visitor.url().endsWith('#our-photos'));
        await visitor.goto(publicUrl + '#our-photos');
        await visitor.waitForFunction(() => Math.abs(document.getElementById('our-photos').getBoundingClientRect().top - 28) < 3);
        await visitor.setViewportSize({ width: 1440, height: 1000 });
        await visitor.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Photos', exact: true }).click();
        assert.ok(visitor.url().endsWith('#our-photos'));
        assert.equal(await visitor.getByRole('button', { name: /wedding menu/ }).isVisible(), false);
    },
);

run('DEL03b escaped Blade fallback has the same visible story, milestones, photos and navigation without JavaScript', async () => {
    await ensurePublished();
    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    try {
        const fallback = await noJs.newPage();
        const response = await fallback.goto(publicUrl);
        assert.equal(response.status(), 200);
        await fallback.getByRole('heading', { level: 1 }).waitFor();
        const doc = await source();
        const story = doc.content.sections.find((s) => s.type === 'story');
        await fallback.getByText(story.data.paragraphs[0], { exact: true }).waitFor();
        const countdown = doc.content.sections.find((s) => s.type === 'countdown');
        await fallback.getByRole('heading', { name: countdown.data.heading, exact: true }).waitFor();
        assert.equal(await fallback.locator('#countdown .ee-countdown-units').count(), 0);
        await fallback.locator('#countdown .ee-countdown-date').getByText('12 May 2027').waitFor();
        assert.equal(await fallback.locator('.ee-gallery-slide').count(), 2);
        assert.equal(await fallback.getByRole('navigation', { name: 'Wedding sections' }).isVisible(), true);
        assert.equal(await fallback.getByRole('button', { name: /wedding menu/ }).count(), 0);
        assert.equal(await fallback.locator('form, #personal-rsvp-data').count(), 0);
        assert.ok(await fallback.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await fallback.screenshot({ path: join(artifacts, 'no-js-390.png'), fullPage: true });
        await visitor.goto(publicUrl);
        await enterInvitation(visitor);
        await visitor.evaluate(() => document.fonts.ready);
        for (const selector of ['.ee-hero-main', '.ee-details-main', '.ee-story-main', '.ee-gallery-photo']) {
            const measure = (element) => {
                const image = element.querySelector('img');
                const box = image.getBoundingClientRect();
                const style = getComputedStyle(image);
                return { width: box.width, height: box.height, fit: style.objectFit, focus: style.objectPosition };
            };
            const staticPhoto = await fallback.locator(selector).first().evaluate(measure);
            const livePhoto = await visitor.locator(selector).first().evaluate(measure);
            assert.equal(staticPhoto.fit, livePhoto.fit);
            assert.equal(staticPhoto.focus, livePhoto.focus);
            assert.ok(Math.abs(staticPhoto.width - livePhoto.width) < 2 && Math.abs(staticPhoto.height - livePhoto.height) < 2, selector);
        }
        await fallback.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Photos', exact: true }).click();
        assert.ok(fallback.url().endsWith('#our-photos'));
    } finally {
        await noJs.close();
    }
});

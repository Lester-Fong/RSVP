import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import { createEnchantedEditorialServer } from './enchanted-editorial-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
const photosAvailable = process.env.QA_ENCHANTED_PHOTOS === '1';
const artifacts = resolve(process.env.QA_ENCHANTED_ARTIFACTS || 'storage/app/private/dev-preview/enchanted-editorial/qa-b2b');
let server, browser, context, page, origin;
const measurements = [];
const errors = [];

before(async () => {
    if (!enabled) return;
    await mkdir(artifacts, { recursive: true });
    server = await createEnchantedEditorialServer();
    origin = `http://127.0.0.1:${server.httpServer.address().port}`;
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    try {
        browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    } catch (error) {
        console.error('Enchanted browser setup failed:', error);
        throw error;
    }
});
beforeEach(async () => {
    if (!enabled) return;
    errors.length = 0;
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce', timezoneId: 'America/Los_Angeles' });
    page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.text().includes('Hydration')) errors.push(message.text());
    });
    page.setDefaultTimeout(15000);
});
afterEach(async () => {
    if (!enabled) return;
    await context.close();
    assert.deepEqual(errors, []);
});
after(async () => {
    if (!enabled) return;
    if (measurements.length) await writeFile(resolve(artifacts, 'measurements.json'), JSON.stringify(measurements, null, 4) + '\n');
    await browser?.close();
    await server?.close();
});

async function load(query = 'canvas=1', advanceFrames = false) {
    await page.goto(`${origin}/enchanted-editorial.html?${query}`);
    if (advanceFrames) {
        await page.evaluate(() => document.fonts.ready);
        await page.clock.runFor(64);
    }
    await page.getByRole('heading', { level: 1 }).waitFor();
    await page.evaluate(() => document.fonts.ready);
    for (const picture of await page.locator('img:visible').elementHandles()) {
        if (await picture.evaluate((image) => image.isConnected)) {
            await picture.scrollIntoViewIfNeeded().catch(() => {});
            await picture.evaluate((image) => image.decode().catch(() => {}));
        }
    }
    await page.evaluate(() => window.scrollTo(0, 0));
}
async function contained() {
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No document overflow');
    const escaped = await page.locator('h1, h2, .ee-venue, .ee-body').evaluateAll((nodes) =>
        nodes
            .filter((node) => {
                const box = node.getBoundingClientRect();
                return box.left < 0 || box.right > innerWidth + 1 || node.scrollWidth > node.clientWidth + 1;
            })
            .map((node) => node.className),
    );
    assert.deepEqual(escaped, [], 'Text fits its own container, rather than being clipped');
}

test('photographic composition, loaded fonts and visual evidence at all target widths', { skip: !enabled || !photosAvailable }, async () => {
    for (const [width, height] of [
        [390, 844],
        [414, 896],
        [768, 1024],
        [1024, 900],
        [1440, 1000],
        [1920, 1080],
    ]) {
        await page.setViewportSize({ width, height });
        await load();
        await contained();
        const overlappingDecor = await page.evaluate(() =>
            ['countdown', 'rsvp'].filter((id) => {
                const section = document.getElementById(id);
                const art = section.querySelector('.ee-botanical').getBoundingClientRect();
                const range = document.createRange();
                range.selectNodeContents(section.querySelector('h2'));
                const text = range.getBoundingClientRect();
                return art.left < text.right && art.right > text.left && art.top < text.bottom && art.bottom > text.top;
            }),
        );
        assert.deepEqual(overlappingDecor, [], 'Botanical artwork stays clear of heading text');
        assert.equal(await page.locator('.ee-hero-composition img, .ee-details-composition img').count(), 4);
        assert.ok(await page.locator('img:visible').evaluateAll((nodes) => nodes.every((image) => image.complete && image.naturalWidth > 0)));
        assert.ok(await page.evaluate(() => document.fonts.check('40px "Enchanted Serif"') && document.fonts.check('14px "Enchanted Sans"')));
        assert.equal(await page.locator('form, input, audio, video').count(), 0);
        const boxes = {};
        for (const name of ['ee-hero-copy', 'ee-hero-main', 'ee-hero-inset', 'ee-details-main', 'ee-details-copy']) {
            boxes[name] = await page.locator(`.${name}`).boundingBox();
        }
        if (width >= 1024) assert.ok(boxes['ee-hero-main'].x > boxes['ee-hero-copy'].x + boxes['ee-hero-copy'].width);
        if (width <= 768) assert.ok(boxes['ee-hero-main'].y > boxes['ee-hero-copy'].y + boxes['ee-hero-copy'].height);
        measurements.push({ width, height, boxes });
        await page.screenshot({ path: resolve(artifacts, `default-${width}.png`), fullPage: true });
        if ([390, 1440].includes(width))
            for (const id of ['our-story', 'countdown', 'our-photos', 'entourage', 'rsvp']) {
                await page.locator(`#${id}`).screenshot({ path: resolve(artifacts, `${id}-${width}.png`) });
            }
    }
});

test('alternate portrait/landscape photos and long names remain composed without cropping text', { skip: !enabled || !photosAvailable }, async () => {
    for (const variant of ['alternate', 'long', 'unbroken']) {
        for (const width of [390, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            await load(`canvas=1&case=${variant}`);
            await contained();
            await page.screenshot({ path: resolve(artifacts, `${variant}-${width}.png`), fullPage: true });
        }
    }
    assert.match(await page.locator('h1').innerText(), /AlexandriaAlexandria/i);
});

test('unassigned photographs stay empty and failed uploads provide visible graceful fallbacks', { skip: !enabled }, async () => {
    await page.setViewportSize({ width: 390, height: 844 });
    await load('canvas=1&case=missing');
    assert.equal(await page.locator('.ee-hero-composition [role="img"], .ee-details-composition [role="img"]').count(), 0);
    assert.equal(await page.locator('.ee-hero-inset, .ee-details-inset').count(), 0);
    await contained();
    await page.screenshot({ path: resolve(artifacts, 'missing-390.png'), fullPage: true });
    await page.route('**/__enchanted-assets/*', (route) => route.fulfill({ status: 404, body: '' }));
    await load();
    assert.equal(await page.locator('.ee-hero-composition [role="img"], .ee-details-composition [role="img"]').count(), 4);
});

test('keyboard skip, functional section links, touch targets and meaningful map links', { skip: !enabled }, async () => {
    await page.setViewportSize({ width: 390, height: 844 });
    await load();
    await page.keyboard.press('Tab');
    assert.equal(await page.locator(':focus').innerText(), 'Skip to invitation');
    assert.equal(await page.locator(':focus').evaluate((node) => getComputedStyle(node).outlineStyle), 'solid');
    await page.keyboard.press('Enter');
    assert.ok(page.url().endsWith('#invitation'));
    await page.getByRole('link', { name: 'The wedding details' }).click();
    assert.ok(page.url().endsWith('#wedding-details'));
    assert.ok(Math.abs((await page.locator('#wedding-details').boundingBox()).y - 28) < 4);
    const menu = page.getByRole('button', { name: 'Open wedding menu', exact: true });
    assert.ok((await menu.boundingBox()).height >= 44);
    await menu.click();
    for (const link of await page.locator('.ee-navigation a, .ee-outline-link, .ee-map-link').all()) {
        assert.ok((await link.boundingBox()).height >= 44);
    }
    assert.match(await page.getByRole('link', { name: 'View ceremony map', exact: false }).getAttribute('href'), /^https:/);
    assert.equal(await page.locator('.ee-map-link').first().getAttribute('rel'), 'noopener noreferrer');
    await page.keyboard.press('Escape');
    await page.screenshot({ path: resolve(artifacts, 'keyboard-390.png'), fullPage: true });
});

test('server-delivered invitation remains readable and navigable without JavaScript', { skip: !enabled }, async () => {
    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const fallback = await noJs.newPage();
    const response = await fallback.goto(`${origin}/enchanted-editorial.html?canvas=1`);
    assert.equal(response.status(), 200);
    await fallback.getByRole('heading', { name: 'Isabel & Gabriel' }).waitFor();
    assert.equal(await fallback.getByText('The Garden Chapel', { exact: true }).count(), 1);
    assert.equal(await fallback.locator('.ee-hero h1').evaluate((node) => getComputedStyle(node).fontFamily.includes('Enchanted Serif')), true);
    await fallback.getByRole('link', { name: 'The wedding details' }).click();
    assert.ok(fallback.url().endsWith('#wedding-details'));
    await fallback.screenshot({ path: resolve(artifacts, 'no-javascript-390.png'), fullPage: true });
    await noJs.close();
});

test('private photo allowlist, loopback binding and no-store protections', { skip: !enabled }, async () => {
    assert.equal(server.httpServer.address().address, '127.0.0.1');
    const response = await context.request.get(`${origin}/enchanted-editorial.html`);
    assert.equal(response.headers()['cache-control'], 'private, no-store');
    assert.equal(response.headers()['referrer-policy'], 'no-referrer');
    assert.match(response.headers()['x-robots-tag'], /noindex/);
    assert.ok(!(await response.text()).includes('storage/app/private'));
    for (const path of ['/__enchanted-assets/unknown.jpg', '/__enchanted-assets/gallery-07.jpg', '/__enchanted-assets/%2e%2e%2fhero.jpg']) {
        assert.equal((await context.request.get(origin + path)).status(), 404);
    }
    assert.equal((await context.request.post(`${origin}/__enchanted-assets/hero.jpg`, { data: '' })).status(), 404);
    const fileProbe = await context.request.get(
        origin + '/@fs/' + resolve('storage/app/private/dev-preview/enchanted-editorial/hero.jpg').replaceAll('\\', '/'),
    );
    assert.equal(fileProbe.status(), 403);
    if (photosAvailable) {
        const photo = await context.request.get(`${origin}/__enchanted-assets/hero.jpg`);
        assert.equal(photo.status(), 200);
        assert.equal(photo.headers()['content-type'], 'image/jpeg');
        assert.equal(photo.headers()['cache-control'], 'private, no-store');
    }
});

test(
    'preview controls support light/dark, visible focus and reduced motion without changing the invitation palette',
    { skip: !enabled },
    async () => {
        for (const colorScheme of ['light', 'dark']) {
            await page.emulateMedia({ colorScheme, reducedMotion: 'reduce' });
            await page.setViewportSize({ width: 390, height: 844 });
            await load('case=missing');
            await contained();
            const expected = colorScheme === 'dark' ? 'rgb(23, 23, 23)' : 'rgb(250, 250, 250)';
            assert.equal(await page.locator('.enchanted-preview-bar').evaluate((node) => getComputedStyle(node).backgroundColor), expected);
            assert.equal(await page.locator('.enchanted-editorial').evaluate((node) => getComputedStyle(node).backgroundColor), 'rgb(248, 245, 238)');
            assert.equal(await page.locator('.ee-hero .ee-outline-link').evaluate((node) => getComputedStyle(node).transitionDuration), '0s');
            await page.keyboard.press('Tab');
            assert.equal(await page.locator(':focus').evaluate((node) => getComputedStyle(node).outlineStyle), 'solid');
            await page.screenshot({ path: resolve(artifacts, `controls-${colorScheme}-390.png`), fullPage: true });
        }
    },
);

test('complete invitation flow has ordered sections, working anchors and informational RSVP only', { skip: !enabled }, async () => {
    await load();
    assert.deepEqual(
        await page.locator('main [data-landing-id^="section:"]').evaluateAll((els) => els.map((el) => el.dataset.landingSection)),
        await page.locator('main > section').evaluateAll((els) => els.map((el) => el.id)),
    );
    assert.deepEqual(await page.locator('main > section').evaluateAll((nodes) => nodes.map((node) => node.id)), [
        'invitation',
        'our-story',
        'wedding-details',
        'countdown',
        'our-photos',
        'entourage',
        'rsvp',
    ]);
    for (const link of await page.locator('.ee-navigation a').all()) {
        await link.click();
        const target = await link.getAttribute('href');
        assert.equal(await page.locator(target).count(), 1);
    }
    assert.match(await page.locator('#rsvp').innerText(), /does not collect RSVP responses/);
    assert.equal(await page.locator('form, input, audio, video').count(), 0);
    assert.equal(await page.getByRole('button', { name: /submit|save response/i }).count(), 0);
});

test(
    'manual gallery visits all six real files, wraps, selects and keeps keyboard focus without autoplay',
    { skip: !enabled || !photosAvailable },
    async () => {
        await load();
        const next = page.getByRole('button', { name: 'Next photograph', exact: true });
        const visible = page.locator('.ee-gallery-slide:not([hidden]) img');
        const visited = new Set();
        for (let index = 1; index <= 6; index++) {
            await visible.scrollIntoViewIfNeeded();
            await visible.evaluate((image) => image.decode());
            assert.match(await visible.getAttribute('src'), new RegExp(`gallery-0${index}\\.jpg$`));
            assert.ok(await visible.evaluate((image) => image.naturalWidth === 1280));
            visited.add(await visible.getAttribute('src'));
            await page.locator('#our-photos').screenshot({ path: resolve(artifacts, `gallery-0${index}-1440.png`) });
            await next.click();
            assert.equal(await next.evaluate((node) => node === document.activeElement), true);
        }
        assert.equal(visited.size, 6);
        assert.match(await visible.getAttribute('src'), /gallery-01/);
        await page.getByRole('button', { name: 'Previous photograph', exact: true }).click();
        assert.match(await visible.getAttribute('src'), /gallery-06/);
        await page.getByRole('button', { name: 'Show photograph 3', exact: true }).click();
        const stage = page.locator('.ee-gallery-stage');
        await stage.focus();
        await page.keyboard.press('ArrowRight');
        assert.match(await visible.getAttribute('src'), /gallery-04/);
        await page.keyboard.press('Home');
        assert.match(await visible.getAttribute('src'), /gallery-01/);
        await page.keyboard.press('End');
        assert.match(await visible.getAttribute('src'), /gallery-06/);
        await page.clock.install();
        await page.clock.runFor(12000);
        assert.match(await visible.getAttribute('src'), /gallery-06/);
        for (const button of await page.locator('.ee-gallery button').all()) assert.ok((await button.boundingBox()).height >= 44);
    },
);

test('real touch swipe changes gallery while vertical gestures preserve selection', { skip: !enabled || !photosAvailable }, async () => {
    await page.setViewportSize({ width: 390, height: 844 });
    await load();
    const stage = page.locator('.ee-gallery-stage');
    await stage.scrollIntoViewIfNeeded();
    const box = await stage.boundingBox();
    const client = await context.newCDPSession(page);
    const x = box.x + box.width * 0.72,
        y = box.y + box.height * 0.5;
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x - 100, y: y + 2 }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    assert.match(await page.locator('.ee-gallery-slide:not([hidden]) img').getAttribute('src'), /gallery-02/);
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x - 5, y: y - 90 }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    assert.match(await page.locator('.ee-gallery-slide:not([hidden]) img').getAttribute('src'), /gallery-02/);
    await client.detach();
});

test('countdown uses Manila facts in a different browser zone, ticks, reaches start and handles past/missing times', { skip: !enabled }, async () => {
    await page.clock.install({ time: new Date('2027-02-19T05:58:59Z') });
    await page.clock.pauseAt(new Date('2027-02-19T05:58:59Z'));
    await load('canvas=1&case=missing', true);
    assert.deepEqual(await page.locator('.ee-countdown-units dd').allTextContents(), ['01', '01', '01', '01']);
    assert.equal(await page.locator('.ee-countdown-date time').getAttribute('datetime'), '2027-02-20T07:00:00.000Z');
    await page.clock.runFor(1000);
    assert.deepEqual(await page.locator('.ee-countdown-units dd').allTextContents(), ['01', '01', '01', '00']);
    await page.clock.fastForward(90060000);
    assert.match(await page.locator('.ee-countdown-arrived').innerText(), /has arrived/);
    assert.equal(await page.locator('.ee-countdown-units').count(), 0);
    await load('canvas=1&case=missing', true);
    assert.equal(await page.locator('.ee-countdown-arrived').count(), 1);
    await page.clock.fastForward(86400000);
    assert.equal(await page.locator('.ee-countdown-arrived').count(), 1);
    await load('canvas=1&case=date-pending', true);
    assert.equal(await page.locator('.ee-countdown-units').count(), 0);
    assert.match(await page.locator('.ee-countdown-note').innerText(), /confirmed soon/);
});

test('optional/reordered sections, short/long stories, many sponsors and missing gallery images stay contained', { skip: !enabled }, async () => {
    for (const [variant, width] of [
        ['minimal', 390],
        ['empty', 390],
        ['short-story', 390],
        ['long-story', 1440],
        ['many-sponsors', 390],
        ['many-sponsors', 1440],
        ['alternate', 768],
        ['reordered', 1440],
        ['single-photo', 390],
        ['portrait-gallery', 390],
        ['portrait-gallery', 1440],
    ]) {
        await page.setViewportSize({ width, height: 1000 });
        await load(`canvas=1&case=${variant}`);
        await contained();
        const overflow = await page
            .locator('.ee-story-prose, .ee-entourage-group li, .ee-gallery-caption')
            .evaluateAll((nodes) => nodes.some((node) => node.scrollWidth > node.clientWidth + 1));
        assert.equal(overflow, false);
        if (['minimal', 'empty'].includes(variant)) {
            assert.equal(await page.locator('#our-story, #our-photos, #entourage').count(), 0);
            assert.equal(
                await page
                    .locator('[data-landing-section="our-story"], [data-landing-section="our-photos"], [data-landing-section="entourage"]')
                    .count(),
                0,
            );
            assert.equal(await page.getByRole('link', { name: 'Our story', exact: true }).count(), 0);
        }
        if (variant === 'many-sponsors')
            assert.equal(await page.getByRole('region', { name: 'Our Cherished Friends and Mentors' }).locator('li').count(), 48);
        if (variant === 'reordered') assert.equal(await page.locator('main > section').nth(1).getAttribute('id'), 'rsvp');
        if (variant === 'single-photo') assert.equal(await page.locator('.ee-gallery button').count(), 0);
        await page.screenshot({ path: resolve(artifacts, `${variant}-${width}.png`), fullPage: true });
    }
    await page.route('**/__enchanted-assets/gallery-02.jpg', (route) => route.fulfill({ status: 404, body: '' }));
    await load();
    await page.getByRole('button', { name: 'Next photograph', exact: true }).click();
    await page.getByRole('img', { name: 'Couple photograph unavailable' }).waitFor();
    assert.equal(await page.locator('.ee-gallery-caption').getAttribute('aria-live'), 'polite');
});

test('story, entourage and captions are plain escaped content, never executable markup', { skip: !enabled }, async () => {
    await load('canvas=1&case=escaped');
    assert.match(await page.locator('.ee-story-copy').textContent(), /<script>/);
    assert.match(await page.locator('.ee-entourage-group li').textContent(), /<svg/);
    assert.match(await page.locator('.ee-gallery-caption').textContent(), /<img/);
    assert.equal(await page.locator('.ee-gallery-caption img').count(), 0);
    assert.equal(await page.locator('.ee-story-copy img, .ee-story-copy script, .ee-entourage-group svg').count(), 0);
    assert.equal(await page.evaluate(() => window.__xss), undefined);
    assert.equal(await page.getByRole('heading', { name: 'Our Chosen Family', exact: true }).count(), 1);
});

test('unmounting countdown clears its interval rather than keeping a hidden timer alive', { skip: !enabled }, async () => {
    await page.addInitScript(() => {
        const originalSet = window.setInterval.bind(window),
            originalClear = window.clearInterval.bind(window);
        const intervals = new Set();
        window.__countdownIntervals = intervals;
        window.setInterval = (handler, timeout, ...args) => {
            const id = originalSet(handler, timeout, ...args);
            if (timeout === 1000) intervals.add(id);
            return id;
        };
        window.clearInterval = (id) => {
            intervals.delete(id);
            originalClear(id);
        };
    });
    await load('canvas=1&case=timer-lifecycle');
    assert.equal(await page.evaluate(() => window.__countdownIntervals.size), 1);
    await page.getByRole('button', { name: 'Hide preview', exact: true }).click();
    assert.equal(await page.evaluate(() => window.__countdownIntervals.size), 0);
    await page.getByRole('button', { name: 'Show preview', exact: true }).click();
    assert.equal(await page.evaluate(() => window.__countdownIntervals.size), 1);
});

test('DEL02d twelve ordered timeline moments remain readable without JavaScript and adapt across widths', { skip: !enabled }, async () => {
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await load('canvas=1&case=timeline');
        const timeline = page.locator('.ee-story-timeline');
        assert.equal(await timeline.locator('.ee-timeline-entry').count(), 12);
        assert.deepEqual(await timeline.locator('time').allTextContents(), ['2020', '9 March 2025']);
        const positions = await timeline
            .locator('.ee-timeline-content')
            .evaluateAll((nodes) => nodes.slice(0, 2).map((n) => n.getBoundingClientRect().left));
        assert.ok(width >= 1024 ? positions[1] > positions[0] + 100 : Math.abs(positions[0] - positions[1]) < 1);
        assert.equal(await timeline.locator('h4').first().innerText(), 'A meaningful moment 1');
        assert.equal(await timeline.locator('h4').last().innerText(), 'A meaningful moment 12');
        assert.equal(await timeline.locator('.ee-timeline-photo').count(), 4);
        assert.ok(await timeline.locator('.ee-timeline-content').evaluateAll((nodes) => nodes.every((n) => n.scrollWidth <= n.clientWidth + 1)));
        await contained();
        await page.screenshot({ path: resolve(artifacts, 'del02d-timeline-' + width + '.png'), fullPage: true });
    }
    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
    const staticPage = await noJs.newPage();
    await staticPage.goto(origin + '/enchanted-editorial.html?canvas=1&case=timeline');
    assert.equal(await staticPage.locator('.ee-timeline-entry').count(), 12);
    assert.equal((await staticPage.locator('.ee-timeline-entry time').nth(1).textContent()).trim(), '9 March 2025');
    await noJs.close();
});

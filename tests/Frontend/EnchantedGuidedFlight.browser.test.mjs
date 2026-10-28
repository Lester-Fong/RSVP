import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import { createEnchantedEditorialServer } from './enchanted-editorial-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
const artifacts = resolve(process.env.QA_ENCHANTED_ARTIFACTS || 'storage/app/private/dev-preview/enchanted-editorial/qa-del07b');
let server, browser, context, page, origin;
const errors = [];

before(async () => {
    if (!enabled) return;
    await mkdir(artifacts, { recursive: true });
    server = await createEnchantedEditorialServer();
    origin = `http://127.0.0.1:${server.httpServer.address().port}`;
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});

beforeEach(async () => {
    if (!enabled) return;
    errors.length = 0;
    context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'no-preference' });
    page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`${origin}/enchanted-editorial.html?case=timeline`);
    await page.locator('[data-ee-phase="landed"]').waitFor();
});

afterEach(async () => {
    if (!enabled) return;
    await context.close();
    assert.deepEqual(errors, []);
});

after(async () => {
    await browser?.close();
    await server?.close();
});

const run = (name, action) => test(name, { skip: !enabled }, action);
const flight = () => page.locator('.ee-loading-bird');

run('DEL07b keeps one bird and follows document-ordered section and milestone waypoints', async () => {
    const waypoints = await page.locator('[data-landing-id]').evaluateAll((elements) =>
        elements.map((element, index) => ({
            id: element.dataset.landingId,
            y: index === 0 ? 0 : element.querySelector('[data-nectar-center]').getBoundingClientRect().top + scrollY - innerHeight * 0.38,
        })),
    );
    assert.ok(waypoints.length > 8);
    assert.equal(await page.locator('.ee-hummingbird').count(), 1);
    assert.equal(await flight().getAttribute('data-guided'), 'true');
    await flight().evaluate((element) => {
        window.__guidedWingAnimations = [...element.querySelectorAll('.ee-bird-wing')].map((wing) => wing.getAnimations()[0]);
    });

    const visited = [];
    for (const point of waypoints.slice(0, 7)) {
        await page.evaluate((y) => scrollTo({ top: y, behavior: 'instant' }), Math.max(0, point.y));
        await page.waitForFunction((id) => document.querySelector('.ee-loading-bird').dataset.waypointId === id, point.id);
        visited.push(await flight().getAttribute('data-waypoint-id'));
        const inViewport = await flight().evaluate((element) => {
            const box = element.getBoundingClientRect();
            const root = document.querySelector('.ee-loading-boundary').getBoundingClientRect();
            const marker = document.querySelector(`[data-landing-id="${CSS.escape(element.dataset.waypointId || '')}"]`)?.getBoundingClientRect();
            return {
                visible: box.bottom > 0 && box.top < innerHeight && box.right > 0 && box.left < innerWidth,
                top: box.top,
                bottom: box.bottom,
                style: element.getAttribute('style'),
                scrollY,
                rootTop: root.top,
                markerTop: marker?.top,
            };
        });
        assert.equal(inViewport.visible, true, `${point.id} bird box ${JSON.stringify(inViewport)}`);
    }
    assert.ok(visited.some((id) => id?.startsWith('milestone:')));
    assert.ok(new Set(visited).size > 2);
    assert.equal(await page.locator('.ee-hummingbird').count(), 1);
    assert.deepEqual(
        await flight().evaluate((element) =>
            [...element.querySelectorAll('.ee-bird-wing')].map(
                (wing, index) => wing.getAnimations()[0] === window.__guidedWingAnimations[index] && wing.getAnimations()[0].playState === 'running',
            ),
        ),
        [true, true],
    );
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path: resolve(artifacts, 'guided-flight-desktop.png'), fullPage: true });
});

async function nectarGap(marker) {
    return marker.evaluate((flower) => {
        const bill = document.querySelector('.ee-loading-bird [data-bird-beak]');
        const vertex = [...bill.getAttribute('d').matchAll(/[ML]\s*(-?[\d.]+)[ ,]+(-?[\d.]+)/g)][2];
        const tip = new DOMPoint(Number(vertex[1]), Number(vertex[2])).matrixTransform(bill.getScreenCTM());
        const circle = flower.querySelector('[data-nectar-center]');
        const nectar = new DOMPoint(circle.cx.baseVal.value, circle.cy.baseVal.value).matrixTransform(circle.getScreenCTM());
        const frame = document.querySelector('.ee-loading-bird');
        const transform = new DOMMatrix(getComputedStyle(frame).transform);
        return {
            gap: Math.hypot(tip.x - nectar.x, tip.y - nectar.y),
            scale: Math.hypot(transform.a, transform.b),
            id: frame.dataset.waypointId,
            orientation: frame.querySelector('.ee-hummingbird').dataset.orientation,
            rect: frame.getBoundingClientRect().toJSON(),
        };
    });
}

run('DEL07b refined Hero stays in the first viewport and hands the same fluttering bird to its nectar centre', async () => {
    for (const [width, height, variant] of [
        [1366, 768, 'timeline'],
        [1280, 600, 'missing'],
        [1920, 1080, 'timeline'],
        [390, 844, 'timeline'],
        [375, 667, 'missing'],
        [667, 375, 'missing'],
        [390, 667, 'long'],
    ]) {
        await page.setViewportSize({ width, height });
        await page.goto(`${origin}/enchanted-editorial.html?canvas=1&case=${variant}`);
        await page.locator('[data-ee-phase="landed"]').waitFor();
        const flower = page.locator('[data-landing-id="section:invitation"]');
        assert.equal(await flower.count(), 1);
        await page.waitForFunction(() => Math.abs(Number(document.querySelector('.ee-hummingbird').dataset.yaw)) === 1);
        await page.waitForTimeout(450);
        const landing = await nectarGap(flower);
        assert.ok(landing.gap < 3.5, JSON.stringify({ width, height, ...landing }));
        assert.ok(landing.rect.top > 0 && landing.rect.bottom < height, JSON.stringify(landing));
        assert.equal(await page.locator('.ee-hummingbird').count(), 1);
        assert.equal(await page.locator('.ee-loading-veil').isVisible(), false);
        await page.screenshot({ path: resolve(artifacts, `refined-hero-${width}-${height}.png`) });
    }
});

run('DEL07b every section and Story milestone reaches actual nectar at unit scale on desktop and mobile', async () => {
    for (const width of [1366, 390]) {
        await page.setViewportSize({ width, height: 844 });
        const stops = await page.locator('[data-landing-id]').evaluateAll((elements) => elements.map((node) => node.dataset.landingId));
        for (const id of stops) {
            const flower = page.locator(`[data-landing-id="${id}"]`);
            await flower.evaluate((node) => {
                const nectar = node.querySelector('[data-nectar-center]').getBoundingClientRect();
                scrollTo({
                    top: node.dataset.landingId === 'section:invitation' ? 0 : nectar.top + nectar.height / 2 + scrollY - innerHeight * 0.38,
                    behavior: 'instant',
                });
            });
            await page.waitForFunction((id) => document.querySelector('.ee-loading-bird').dataset.waypointId === id, id);
            await page.waitForTimeout(700);
            const landing = await nectarGap(flower);
            assert.ok(landing.gap < 3.5, JSON.stringify({ width, id, ...landing }));
            assert.ok(Math.abs(landing.scale - 1) < 0.001, JSON.stringify(landing));
            assert.ok(landing.rect.left >= 0 && landing.rect.right <= width, JSON.stringify(landing));
        }
    }
});

run('DEL07b mid-flight enlarges smoothly and reverses to accurate unit-scale flower rest', async () => {
    for (const [width, peak] of [
        [1366, 1.18],
        [390, 1.1],
    ]) {
        await page.setViewportSize({ width, height: 844 });
        await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
        const destination = page.locator('[data-landing-id="section:our-story"]');
        const activation = await destination.evaluate((node) => {
            const nectar = node.querySelector('[data-nectar-center]').getBoundingClientRect();
            return nectar.top + nectar.height / 2 + scrollY - innerHeight * 0.38;
        });
        await page.evaluate((y) => scrollTo({ top: y / 2, behavior: 'instant' }), activation);
        await page.waitForFunction((peak) => {
            const matrix = new DOMMatrix(getComputedStyle(document.querySelector('.ee-loading-bird')).transform);
            return Math.hypot(matrix.a, matrix.b) > peak - 0.015;
        }, peak);
        const scale = await flight().evaluate((node) => {
            const m = new DOMMatrix(getComputedStyle(node).transform);
            return Math.hypot(m.a, m.b);
        });
        assert.ok(scale <= peak + 0.001);
        await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
        await page.waitForTimeout(750);
        const landing = await nectarGap(page.locator('[data-landing-id="section:invitation"]'));
        assert.ok(landing.gap < 3.5 && Math.abs(landing.scale - 1) < 0.001, JSON.stringify(landing));
    }
});

run('DEL07b handles rapid reverse scroll, hidden sections and responsive resize without stale destinations', async () => {
    const photos = page.locator('#our-photos');
    await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight));
    await page.waitForTimeout(120);
    const atBottom = await flight().getAttribute('data-waypoint-id');
    await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
    await page.waitForTimeout(120);
    assert.notEqual(await flight().getAttribute('data-waypoint-id'), atBottom);

    await photos.evaluate((element) => {
        element.hidden = true;
    });
    await page.waitForTimeout(120);
    await page.evaluate(() => scrollTo({ top: document.documentElement.scrollHeight / 2, behavior: 'instant' }));
    await page.waitForTimeout(120);
    assert.notEqual(await flight().getAttribute('data-waypoint-id'), 'section:our-photos');
    const removedId = await page.locator('[data-landing-milestone]').first().getAttribute('data-landing-id');
    await page
        .locator('[data-milestone-id]')
        .first()
        .evaluate((element) => element.remove());
    // Reorder rendered sections without changing identities or adding route metadata.
    await page.locator('#countdown').evaluate((element) => element.before(document.querySelector('#entourage')));
    await page.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'Entourage', exact: true }).click();
    await page.waitForFunction(() => document.querySelector('.ee-loading-bird').dataset.waypointId === 'section:entourage');
    assert.notEqual(await flight().getAttribute('data-waypoint-id'), removedId);
    await page.setViewportSize({ width: 390, height: 820 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path: resolve(artifacts, 'guided-flight-mobile.png'), fullPage: true });
});

run('DEL07b guided flight releases observers and animation callbacks when the invitation unmounts', async () => {
    await page.addInitScript(() => {
        window.__guidedWork = { frames: new Set(), observers: new Set() };
        const request = requestAnimationFrame.bind(window),
            cancel = cancelAnimationFrame.bind(window);
        window.requestAnimationFrame = (callback) => {
            const id = request((now) => {
                window.__guidedWork.frames.delete(id);
                callback(now);
            });
            window.__guidedWork.frames.add(id);
            return id;
        };
        window.cancelAnimationFrame = (id) => {
            window.__guidedWork.frames.delete(id);
            cancel(id);
        };
        for (const name of ['ResizeObserver', 'MutationObserver', 'IntersectionObserver']) {
            const Original = window[name];
            window[name] = class extends Original {
                constructor(...args) {
                    super(...args);
                    this.__creation = new Error().stack;
                    window.__guidedWork.observers.add(this);
                }
                disconnect() {
                    window.__guidedWork.observers.delete(this);
                    super.disconnect();
                }
            };
        }
    });
    await page.goto(`${origin}/enchanted-editorial.html?case=timer-lifecycle`);
    await page.locator('[data-ee-phase="landed"]').waitFor();
    await page.evaluate(() => scrollTo(0, 1200));
    await page.getByRole('button', { name: 'Hide preview', exact: true }).click();
    await page.waitForTimeout(250);
    const remaining = await page.evaluate(() => ({
        frames: window.__guidedWork.frames.size,
        observers: [...window.__guidedWork.observers].map((observer) => observer.__creation),
    }));
    assert.deepEqual(remaining, { frames: 0, observers: [] });
    assert.equal(await page.locator('.ee-hummingbird').count(), 0);
});

run('DEL07b reduced motion stays static while navigation remains usable', async () => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.waitForFunction(() => document.querySelector('.ee-loading-bird')?.dataset.guided === 'false');
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.waitForFunction(() => document.querySelector('.ee-loading-bird')?.dataset.guided === 'true');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.reload();
    await page.locator('[data-ee-phase="landed"]').waitFor();
    assert.equal(await flight().getAttribute('data-guided'), 'false');
    const initial = await flight().getAttribute('style');
    await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight));
    await page.waitForTimeout(120);
    assert.equal(await flight().getAttribute('style'), initial);
    assert.equal(
        await flight()
            .locator('.ee-bird-wing')
            .first()
            .evaluate((element) => element.getAnimations().filter((animation) => animation.playState === 'running').length),
        0,
    );
    await page.getByRole('link', { name: 'Our story', exact: true }).click();
    assert.ok(page.url().endsWith('#our-story'));
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
});

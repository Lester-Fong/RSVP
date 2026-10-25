import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import { createEnchantedEditorialServer } from './enchanted-editorial-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
const artifacts = resolve(process.env.QA_ENCHANTED_ARTIFACTS || 'storage/app/private/dev-preview/enchanted-editorial/qa-del07a');
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
    context = await browser.newContext({ viewport: { width: 1440, height: 1100 }, reducedMotion: 'no-preference' });
    page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.text().includes('Hydration')) errors.push(message.text());
    });
    await page.goto(`${origin}/enchanted-editorial.html?case=hummingbird`);
    await page.locator('.hb-flight-bird .ee-hummingbird[data-active="true"]').waitFor();
});
afterEach(async () => {
    if (enabled) {
        await context.close();
        assert.deepEqual(errors, []);
    }
});
after(async () => {
    await browser?.close();
    await server?.close();
});
const run = (name, action) => test(name, { skip: !enabled }, action);
const bird = () => page.locator('.hb-flight-bird .ee-hummingbird');
const choose = (name) => page.getByRole('button', { name, exact: true }).click();

run('DEL07a five directional views morph continuously while the same wing animations keep running', async () => {
    const gradientIds = await page.locator('.ee-hummingbird [id]').evaluateAll((elements) => elements.map((element) => element.id));
    assert.equal(new Set(gradientIds).size, gradientIds.length, 'Every displayed bird owns its gradient IDs.');
    await bird().evaluate((element) => {
        window.__birdElement = element;
        window.__wingAnimations = [...element.querySelectorAll('.ee-bird-wing')].map((wing) => wing.getAnimations()[0]);
    });
    const widths = [];
    for (const [name, yaw] of [
        ['LEFT', -1],
        ['FRONT LEFT', -0.5],
        ['FRONT', 0],
        ['FRONT RIGHT', 0.5],
        ['RIGHT', 1],
    ]) {
        await choose(name);
        await page.waitForFunction((target) => document.querySelector('.hb-flight-bird .ee-hummingbird').dataset.yaw === target, yaw.toFixed(3));
        widths.push(
            await bird()
                .locator('[data-bird-beak]')
                .evaluate((element) => element.getBBox().width),
        );
        assert.equal(
            await bird()
                .locator('path')
                .evaluateAll((paths) => paths.some((path) => /NaN|Infinity/.test(path.getAttribute('d')))),
            false,
        );
    }
    assert.ok(widths[0] > widths[1] && widths[1] > widths[2] && widths[2] < widths[3] && widths[3] < widths[4]);
    await bird().evaluate((element) => {
        window.__turnSamples = [];
        const sample = () => {
            window.__turnSamples.push(Number(element.dataset.yaw));
            if (Number(element.dataset.yaw) > -0.999) requestAnimationFrame(sample);
        };
        requestAnimationFrame(sample);
    });
    await choose('LEFT');
    await page.waitForFunction(() => document.querySelector('.hb-flight-bird .ee-hummingbird').dataset.yaw === '-1.000');
    const state = await bird().evaluate((element) => ({
        same: element === window.__birdElement,
        wings: [...element.querySelectorAll('.ee-bird-wing')].map(
            (wing, index) => wing.getAnimations()[0] === window.__wingAnimations[index] && wing.getAnimations()[0].playState === 'running',
        ),
        intermediates: new Set(window.__turnSamples.filter((yaw) => yaw > -0.9 && yaw < 0.9)).size,
    }));
    assert.equal(state.same, true);
    assert.deepEqual(state.wings, [true, true]);
    assert.ok(state.intermediates > 5);
    assert.equal(await bird().evaluate((element) => getComputedStyle(element).animationName), 'ee-bird-hover');
    await choose('Pause motion');
    await page.screenshot({ path: resolve(artifacts, 'showcase-desktop.png'), fullPage: true });
});

run('DEL07a bounded flower flight takes off, curves, turns and meets both flowers without restarting the wings', async () => {
    await bird().evaluate((element) => {
        window.__flightWings = [...element.querySelectorAll('.ee-bird-wing')].map((wing) => wing.getAnimations()[0]);
    });
    await page.clock.install();
    await choose('Play flower flight');
    await page.clock.runFor(400);
    assert.equal(await page.locator('.hb-stage').getAttribute('data-motion-state'), 'Takeoff');
    await page.clock.runFor(1200);
    assert.equal(await page.locator('.hb-stage').getAttribute('data-motion-state'), 'Curved flight');
    await choose('Pause motion');
    const stopped = await page.locator('.hb-flight-bird').getAttribute('style');
    await page.clock.runFor(500);
    assert.equal(await page.locator('.hb-flight-bird').getAttribute('style'), stopped);
    await choose('Resume motion');
    await page.clock.runFor(2200);
    assert.equal(await page.locator('.hb-stage').getAttribute('data-motion-state'), 'Nectar hover');
    async function nectar(side) {
        return page.evaluate((side) => {
            const bill = document.querySelector('.hb-flight-bird [data-bird-beak]').getBoundingClientRect();
            const flower = document.querySelector(`.hb-flower-${side} circle`).getBoundingClientRect();
            const tipX = side === 'right' ? bill.right : bill.left;
            return Math.hypot(tipX - flower.x - flower.width / 2, bill.top - flower.y - flower.height / 2);
        }, side);
    }
    assert.ok((await nectar('right')) < 6);
    await page.clock.runFor(1900);
    assert.equal(await page.locator('.hb-stage').getAttribute('data-motion-state'), 'Turning');
    await page.clock.runFor(4200);
    assert.equal(await page.locator('.hb-stage').getAttribute('data-motion-state'), 'Nectar hover');
    assert.equal(await bird().getAttribute('data-orientation'), 'LEFT');
    assert.ok((await nectar('left')) < 6);
    assert.deepEqual(
        await bird().evaluate((element) =>
            [...element.querySelectorAll('.ee-bird-wing')].map((wing, index) => wing.getAnimations()[0] === window.__flightWings[index]),
        ),
        [true, true],
    );
    await page.screenshot({ path: resolve(artifacts, 'nectar-arrival-desktop.png') });
    await page.setViewportSize({ width: 390, height: 1000 });
    await choose('Play flower flight');
    await page.clock.runFor(10100);
    assert.ok((await nectar('left')) < 6);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path: resolve(artifacts, 'nectar-arrival-mobile.png'), fullPage: true });
});

run('DEL07a pause, resume, reduced motion and responsive views retain usable controls and static decorative fallback', async () => {
    await choose('Pause motion');
    assert.equal(
        await bird()
            .locator('.ee-bird-wing')
            .first()
            .evaluate((element) => element.getAnimations()[0].playState),
        'paused',
    );
    await choose('Resume motion');
    assert.equal(
        await bird()
            .locator('.ee-bird-wing')
            .first()
            .evaluate((element) => element.getAnimations()[0].playState),
        'running',
    );
    await page.setViewportSize({ width: 1440, height: 360 });
    await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight));
    await page.waitForFunction(() => document.querySelector('.hb-flight-bird .ee-hummingbird').dataset.active === 'false');
    assert.equal(
        await bird()
            .locator('.ee-bird-wing')
            .first()
            .evaluate((element) => element.getAnimations()[0].playState),
        'paused',
    );
    await page.setViewportSize({ width: 1440, height: 1100 });
    await page.evaluate(() => scrollTo(0, 0));
    await page.waitForFunction(() => document.querySelector('.hb-flight-bird .ee-hummingbird').dataset.active === 'true');
    await page.evaluate(() => {
        Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
        Object.defineProperty(document, 'hidden', { value: true, configurable: true });
        document.dispatchEvent(new Event('visibilitychange'));
    });
    await page.waitForFunction(() => document.querySelector('.hb-flight-bird .ee-hummingbird').dataset.active === 'false');
    await page.evaluate(() => {
        delete document.visibilityState;
        delete document.hidden;
        document.dispatchEvent(new Event('visibilitychange'));
    });
    await choose('Resume motion');
    await page.waitForFunction(() => document.querySelector('.hb-flight-bird .ee-hummingbird').dataset.active === 'true');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.getByText('Reduced motion · static illustration', { exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Play flower flight', exact: true }).isDisabled(), true);
    await choose('FRONT RIGHT');
    assert.equal(await bird().getAttribute('data-yaw'), '0.500');
    assert.equal(
        await bird().evaluate((element) => element.getAnimations({ subtree: true }).filter((animation) => animation.playState === 'running').length),
        0,
    );
    assert.equal(await bird().getAttribute('aria-hidden'), 'true');
    assert.equal(await bird().evaluate((element) => getComputedStyle(element).pointerEvents), 'none');
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.screenshot({ path: resolve(artifacts, `reduced-motion-${width}.png`), fullPage: true });
    }
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.getByRole('button', { name: 'FRONT', exact: true }).focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.getByRole('button', { name: 'FRONT', exact: true }).getAttribute('aria-pressed'), 'true');
});

run('DEL07a removing and restoring birds releases their observers and scheduled turns', async () => {
    await page.addInitScript(() => {
        window.__motionWork = { raf: new Set(), observers: new Set() };
        const request = window.requestAnimationFrame.bind(window),
            cancel = window.cancelAnimationFrame.bind(window);
        window.requestAnimationFrame = (callback) => {
            const id = request((now) => {
                window.__motionWork.raf.delete(id);
                callback(now);
            });
            window.__motionWork.raf.add(id);
            return id;
        };
        window.cancelAnimationFrame = (id) => {
            window.__motionWork.raf.delete(id);
            cancel(id);
        };
        for (const name of ['IntersectionObserver', 'ResizeObserver']) {
            const Original = window[name];
            window[name] = class extends Original {
                constructor(...args) {
                    super(...args);
                    window.__motionWork.observers.add(this);
                }
                disconnect() {
                    window.__motionWork.observers.delete(this);
                    super.disconnect();
                }
            };
        }
    });
    await page.reload();
    await bird().waitFor();
    await choose('Play flower flight');
    await choose('Hide bird');
    await page.waitForFunction(() => document.querySelectorAll('.ee-hummingbird').length === 0 && window.__motionWork.raf.size === 0);
    assert.equal(await page.evaluate(() => window.__motionWork.observers.size), 2, 'Only the showcase stage observers remain.');
    await choose('Show bird');
    assert.equal(await page.locator('.ee-hummingbird').count(), 6);
    await choose('Hide bird');
    await page.waitForFunction(() => window.__motionWork.observers.size === 2 && window.__motionWork.raf.size === 0);
});

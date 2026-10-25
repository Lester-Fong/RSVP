import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { after, before, test } from 'node:test';
import { createEnchantedEditorialServer } from './enchanted-editorial-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let server, browser, origin;
before(async () => {
    if (!enabled) return;
    server = await createEnchantedEditorialServer(); // Disposable internal test service, never an owner preview URL.
    origin = `http://127.0.0.1:${server.httpServer.address().port}`;
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
after(async () => {
    await browser?.close();
    await server?.close();
});
const run = (name, callback) => test(name, { skip: !enabled }, callback);

async function fixture(options = {}, prepare) {
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 }, reducedMotion: 'no-preference', ...options });
    const errors = [];
    const page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    page.setDefaultTimeout(15000);
    if (prepare) await page.addInitScript(prepare);
    await page.goto(origin + '/enchanted-editorial.html?canvas=1&case=timeline-lifecycle', { waitUntil: 'domcontentloaded' });
    await page.locator('.ee-timeline-entry').first().waitFor();
    await page.evaluate(() => document.fonts.ready);
    return { context, page, errors };
}

for (const [device, width] of [
    ['desktop', 1366],
    ['mobile', 390],
]) {
    run(`Story parts progressively reveal on ${device}, preserve layout/flowers and rearm for reverse entry`, async () => {
        const { context, page, errors } = await fixture({ viewport: { width, height: 844 } });
        try {
            const first = page.locator('.ee-timeline-entry').first();
            const before = await first.evaluate((element) => ({ width: element.offsetWidth, height: element.offsetHeight }));
            await first.evaluate((element) => element.scrollIntoView({ block: 'center', behavior: 'instant' }));
            await page.waitForFunction(() =>
                document
                    .querySelector('.ee-timeline-entry h4')
                    .getAnimations()
                    .some((a) => a.id.startsWith('ee-story-reveal')),
            );
            const flower = await first.locator('[data-landing-id]').boundingBox();
            const parts = await first.evaluate((element) =>
                [...element.querySelectorAll('[data-story-reveal]')].map((part) => ({
                    kind: part.dataset.storyReveal,
                    visible: getComputedStyle(part).visibility,
                    opacity: Number(getComputedStyle(part).opacity),
                    animations: part
                        .getAnimations()
                        .filter((a) => a.id.startsWith('ee-story-reveal'))
                        .map((a) => {
                            a.pause();
                            a.currentTime = 120;
                            return a.effect.getKeyframes();
                        }),
                })),
            );
            assert.ok(parts.some((p) => p.kind === 'photo' && p.animations.length));
            assert.ok(parts.some((p) => p.kind === 'title' && p.animations.length));
            assert.ok(parts.every((p) => p.opacity >= 0.6 && p.visible === 'visible'));
            assert.equal(await first.locator('h4').getAttribute('aria-hidden'), null);
            assert.deepEqual(await first.evaluate((e) => ({ width: e.offsetWidth, height: e.offsetHeight })), before);
            assert.deepEqual(await first.locator('[data-landing-id]').boundingBox(), flower);
            assert.equal(await first.locator('[data-landing-id]').evaluate((e) => e.getAnimations().length), 0);
            await first.evaluate((e) => e.getAnimations({ subtree: true }).forEach((a) => a.play()));
            await page.waitForTimeout(950);
            assert.equal(await first.locator('h4').evaluate((e) => getComputedStyle(e).opacity), '1');
            const position = await first.evaluate((e) => ({ top: e.getBoundingClientRect().top + scrollY, height: e.offsetHeight }));
            await page.evaluate(({ top, height }) => scrollTo({ top: top + height + 80, behavior: 'instant' }), position);
            await page.waitForTimeout(100);
            const headingTop = await first.locator('h4').evaluate((e) => e.getBoundingClientRect().top + scrollY);
            await page.evaluate((top) => scrollTo({ top: top + 8, behavior: 'instant' }), headingTop);
            await page.waitForFunction(() =>
                document
                    .querySelector('.ee-timeline-entry h4')
                    .getAnimations()
                    .some((a) => a.id.startsWith('ee-story-reveal')),
            );
            const reverseFrames = await first.locator('h4').evaluate((e) =>
                e
                    .getAnimations()
                    .find((a) => a.id.startsWith('ee-story-reveal'))
                    .effect.getKeyframes(),
            );
            assert.match(reverseFrames[0].transform, /-8px/);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    });
}

run('Reduced motion is static at startup and immediately cancels an in-progress reveal', async () => {
    const initial = await fixture({ reducedMotion: 'reduce' });
    try {
        await initial.page.locator('.ee-timeline-entry').first().scrollIntoViewIfNeeded();
        assert.equal(await initial.page.locator('.ee-story-timeline').evaluate((e) => e.getAnimations({ subtree: true }).length), 0);
    } finally {
        await initial.context.close();
    }
    const { context, page, errors } = await fixture();
    try {
        await page
            .locator('.ee-timeline-entry')
            .first()
            .evaluate((e) => e.scrollIntoView({ block: 'center', behavior: 'instant' }));
        await page.waitForFunction(() =>
            document
                .querySelector('.ee-story-timeline')
                .getAnimations({ subtree: true })
                .some((a) => a.id.startsWith('ee-story-reveal')),
        );
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.waitForTimeout(80);
        assert.equal(await page.locator('.ee-story-timeline').evaluate((e) => e.getAnimations({ subtree: true }).length), 0);
        assert.ok(await page.locator('[data-story-reveal]').evaluateAll((nodes) => nodes.every((e) => getComputedStyle(e).opacity === '1')));
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

for (const failure of ['missing-observer', 'observer-failure', 'animation-failure']) {
    run(`Story stays readable when enhancement has ${failure}`, async () => {
        const setup =
            failure === 'missing-observer'
                ? () => {
                      window.IntersectionObserver = undefined;
                  }
                : failure === 'observer-failure'
                  ? () => {
                        const Native = window.IntersectionObserver;
                        window.IntersectionObserver = class extends Native {
                            observe(element) {
                                if (element.matches('.ee-timeline-entry')) throw Error('Synthetic observation failure');
                                super.observe(element);
                            }
                        };
                    }
                  : () => {
                        const animate = Element.prototype.animate;
                        Element.prototype.animate = function (...args) {
                            if (this.dataset.storyReveal === 'description') throw Error('Synthetic animation failure');
                            return animate.apply(this, args);
                        };
                    };
        const { context, page, errors } = await fixture({}, setup);
        try {
            await page
                .locator('.ee-timeline-entry')
                .first()
                .evaluate((e) => e.scrollIntoView({ block: 'center', behavior: 'instant' }));
            await page.waitForTimeout(950);
            assert.ok(
                await page
                    .locator('[data-story-reveal]')
                    .evaluateAll((nodes) => nodes.every((e) => getComputedStyle(e).opacity === '1' && getComputedStyle(e).visibility === 'visible')),
            );
            assert.equal(await page.locator('.ee-timeline-entry').count(), 12);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    });
}

run('Reorder, remove, hide and unmount keep stable waypoint identity and cancel detached animations/observers', async () => {
    const { context, page, errors } = await fixture({}, () => {
        window.__storyObservers = [];
        const Native = window.IntersectionObserver;
        window.IntersectionObserver = class extends Native {
            constructor(...args) {
                super(...args);
                this.observed = new Set();
                window.__storyObservers.push(this);
            }
            observe(e) {
                this.observed.add(e);
                super.observe(e);
            }
            disconnect() {
                this.observed.clear();
                super.disconnect();
            }
        };
    });
    try {
        const original = await page.locator('.ee-timeline-entry').evaluateAll((nodes) => nodes.map((n) => n.dataset.milestoneId));
        await page
            .locator('.ee-timeline-entry')
            .first()
            .evaluate((e) => e.scrollIntoView({ block: 'center', behavior: 'instant' }));
        await page.waitForFunction(() =>
            document
                .querySelector('.ee-story-timeline')
                .getAnimations({ subtree: true })
                .some((a) => a.id.startsWith('ee-story-reveal')),
        );
        await page.evaluate(() => {
            window.__detachedStoryAnimations = document.querySelector('.ee-story-timeline').getAnimations({ subtree: true });
            window.__detachedStoryAnimations.forEach((a) => a.pause());
        });
        await page.getByRole('button', { name: 'Hide timeline', exact: true }).evaluate((b) => b.click());
        await page.waitForFunction(() => !document.querySelector('.ee-story-timeline'));
        assert.ok(await page.evaluate(() => window.__detachedStoryAnimations.every((a) => a.playState === 'idle')));
        assert.ok(await page.evaluate(() => window.__storyObservers.every((o) => o.observed.size === 0)));
        await page.getByRole('button', { name: 'Show timeline', exact: true }).evaluate((b) => b.click());
        await page.getByRole('button', { name: 'Reverse moments', exact: true }).evaluate((b) => b.click());
        await page.waitForFunction((id) => document.querySelector('.ee-timeline-entry').dataset.milestoneId === id, original.at(-1));
        assert.deepEqual(
            await page.locator('.ee-timeline-entry').evaluateAll((nodes) => nodes.map((n) => n.dataset.milestoneId)),
            [...original].reverse(),
        );
        await page.getByRole('button', { name: 'Remove first moment', exact: true }).evaluate((b) => b.click());
        await page.waitForFunction(() => document.querySelectorAll('.ee-timeline-entry').length === 11);
        assert.deepEqual(
            await page.locator('[data-landing-id]').evaluateAll((nodes) => nodes.map((n) => n.dataset.landingId)),
            [...original]
                .reverse()
                .slice(1)
                .map((id) => 'milestone:' + id),
        );
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

run('No-JavaScript timeline has readable ordered text, photographs and year/date precision', async () => {
    const { context, page } = await fixture({ javaScriptEnabled: false });
    try {
        assert.equal(await page.locator('.ee-timeline-entry').count(), 12);
        assert.equal(await page.locator('.ee-timeline-entry').first().locator('time').textContent(), '2020');
        assert.match(await page.locator('.ee-timeline-entry').nth(1).locator('time').textContent(), /9 March 2025/);
        assert.equal(await page.locator('.ee-timeline-photo').count(), 4);
        assert.ok(
            await page
                .locator('[data-story-reveal]')
                .evaluateAll((nodes) => nodes.every((e) => getComputedStyle(e).opacity === '1' && getComputedStyle(e).visibility === 'visible')),
        );
    } finally {
        await context.close();
    }
});

test(
    'Port-8000 published, authorized personal and saved CMS previews reuse the story and one approved bird without writes',
    { skip: !enabled || !process.env.QA_CMS_EDITOR_INFO },
    async () => {
        const info = JSON.parse(await readFile(process.env.QA_CMS_EDITOR_INFO, 'utf8'));
        assert.equal(info.origin, 'http://127.0.0.1:8000');
        const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
        try {
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', (e) => errors.push(e.message));
            const response = await page.goto(info.origin + '/w/synthetic-editor-preview');
            assert.equal(response.status(), 200);
            await page.locator('[data-ee-phase="landed"]').waitFor();
            const ids = await page.locator('.ee-timeline-entry').evaluateAll((nodes) => nodes.map((n) => n.dataset.milestoneId));
            assert.ok(ids.length > 0);
            for (const id of ids) {
                await page.locator(`[data-milestone-id="${id}"]`).evaluate((e) => e.scrollIntoView({ block: 'start', behavior: 'instant' }));
                await page.waitForTimeout(1100);
                assert.equal(await page.locator('.ee-loading-bird').count(), 1);
                assert.ok(
                    await page
                        .locator('.ee-bird-wing')
                        .evaluateAll((nodes) => nodes.every((n) => n.getAnimations().some((a) => a.playState === 'running'))),
                );
            }
            assert.doesNotMatch(await page.locator('body').innerText(), /Maya Santos|Nico Santos/);
            if (info.owner.personal?.representativeUrl) {
                let personal;
                try {
                    personal = await page.goto(info.owner.personal.representativeUrl);
                } catch {
                    throw Error('Authorized personal page could not load.');
                }
                assert.equal(personal.status(), 200);
                assert.deepEqual(await page.locator('.ee-timeline-entry').evaluateAll((nodes) => nodes.map((n) => n.dataset.milestoneId)), ids);
            }
            await page.goto(info.origin + '/login');
            await page.getByLabel('Email address').fill(info.owner.email);
            await page.getByLabel('Password', { exact: true }).fill(info.owner.password);
            await page.getByRole('button', { name: 'Log in', exact: true }).click();
            await page.waitForURL((url) => url.pathname !== '/login');
            assert.equal((await page.goto(info.previewUrl + '/saved-preview')).status(), 200);
            await page.locator('[data-ee-phase="landed"]').waitFor();
            assert.ok(await page.locator('.ee-timeline-entry').count());
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    },
);

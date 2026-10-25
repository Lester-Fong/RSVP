import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { after, before, test } from 'node:test';

// Read-only against an explicitly selected disposable host; preserves owner-edited CMS/RSVP data.
const enabled = Boolean(process.env.QA_CMS_EDITOR_INFO && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let info, browser;
before(async () => {
    if (!enabled) return;
    info = JSON.parse(await readFile(process.env.QA_CMS_EDITOR_INFO, 'utf8'));
    assert.match(info.origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    assert.match(info.root, /rsvp-cms-editor-/);
    assert.equal(info.personal, true);
    assert.equal(info.publishing, true);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
after(async () => browser?.close());
const run = (name, fn) => test(name, { skip: !enabled }, fn);

async function assertContact(tab, marker) {
    await tab.waitForTimeout(750);
    const result = await marker.evaluate((element) => {
        const path = document.querySelector('.ee-loading-bird [data-bird-beak]');
        const tipVertex = [...path.getAttribute('d').matchAll(/[ML]\s*(-?[\d.]+)[ ,]+(-?[\d.]+)/g)][2];
        const tip = new DOMPoint(Number(tipVertex[1]), Number(tipVertex[2])).matrixTransform(path.getScreenCTM());
        const circle = element.querySelector('[data-nectar-center]');
        const nectar = new DOMPoint(circle.cx.baseVal.value, circle.cy.baseVal.value).matrixTransform(circle.getScreenCTM());
        const matrix = new DOMMatrix(getComputedStyle(document.querySelector('.ee-loading-bird')).transform);
        return {
            gap: Math.hypot(tip.x - nectar.x, tip.y - nectar.y),
            scale: Math.hypot(matrix.a, matrix.b),
            id: document.querySelector('.ee-loading-bird').dataset.waypointId,
            expected: element.dataset.landingId,
        };
    });
    assert.equal(result.id, result.expected);
    assert.ok(result.gap < 3.5 && Math.abs(result.scale - 1) < 0.001, JSON.stringify(result));
}

run('DEL07b published loader retains one bird, accurate section/closing landings and readable no-JS flowers', async () => {
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 }, reducedMotion: 'no-preference' });
    try {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        let releaseModule;
        const release = new Promise((done) => {
            releaseModule = done;
        });
        await page.route('**/public-enchanted-*.js', async (route) => {
            await release;
            await route.continue();
        });
        await page.goto(info.origin + '/w/synthetic-editor-preview', { waitUntil: 'commit' });
        await page.locator('.ee-bootstrap-loader').waitFor({ state: 'visible' });
        assert.equal(await page.locator('.ee-server-content').isVisible(), false);
        releaseModule();
        await page.locator('[data-ee-phase="landed"]').waitFor();
        await page.locator('.ee-loading-bird').evaluate((element) => {
            window.__publishedBird = element;
            window.__publishedWings = [...element.querySelectorAll('.ee-bird-wing')].map((wing) => wing.getAnimations()[0]);
        });
        for (const width of [1366, 390]) {
            await page.setViewportSize({ width, height: 844 });
            const ids = await page.locator('[data-landing-id]').evaluateAll((nodes) => nodes.map((node) => node.dataset.landingId));
            for (const id of ids) {
                const marker = page.locator(`[data-landing-id="${id}"]`);
                await marker.evaluate((element) => {
                    const nectar = element.querySelector('[data-nectar-center]').getBoundingClientRect();
                    scrollTo({
                        top: element.dataset.landingId === 'section:invitation' ? 0 : nectar.top + nectar.height / 2 + scrollY - innerHeight * 0.38,
                        behavior: 'instant',
                    });
                });
                await assertContact(page, marker);
            }
        }
        assert.deepEqual(
            await page.locator('.ee-loading-bird').evaluate((element) => ({
                same: element === window.__publishedBird,
                wings: [...element.querySelectorAll('.ee-bird-wing')].map(
                    (wing, index) => wing.getAnimations()[0] === window.__publishedWings[index],
                ),
            })),
            { same: true, wings: [true, true] },
        );
        assert.equal(await page.locator('.ee-hummingbird').count(), 1);
        assert.equal(await page.locator('form').count(), 0);
        assert.deepEqual(errors, []);
        const fallback = await browser.newContext({ javaScriptEnabled: false });
        try {
            const staticPage = await fallback.newPage();
            await staticPage.goto(info.origin + '/w/synthetic-editor-preview');
            assert.equal(await staticPage.locator('[data-landing-id="section:invitation"]').count(), 1);
            assert.ok((await staticPage.locator('[data-nectar-center]').count()) > 3);
            assert.equal(await staticPage.locator('.ee-bootstrap-loader').isVisible(), false);
        } finally {
            await fallback.close();
        }
    } finally {
        await context.close();
    }
});

run('DEL07b personal RSVP controls, saved preview and live editor keep one correctly landed bird', async () => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'no-preference' });
    try {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message.replace(/[a-f0-9]{64}/g, '[redacted]')));
        await page.goto(info.owner.personal.representativeUrl);
        await page.locator('[data-ee-phase="landed"]').waitFor();
        const marker = page.locator('[data-landing-section="rsvp"], [data-landing-section="personal-rsvp"]').first();
        await marker.evaluate((element) => {
            const rect = element.querySelector('[data-nectar-center]').getBoundingClientRect();
            scrollTo({ top: rect.top + rect.height / 2 + scrollY - innerHeight * 0.38, behavior: 'instant' });
        });
        await assertContact(page, marker);
        const collisions = await page.locator('.ee-loading-bird').evaluate((bird) => {
            const path = bird.querySelector('[data-bird-beak]').parentElement.getBoundingClientRect();
            return [...document.querySelectorAll('form button, form input, form select')].filter((node) => {
                const rect = node.getBoundingClientRect();
                return rect.width > 0 && rect.left < path.right && rect.right > path.left && rect.top < path.bottom && rect.bottom > path.top;
            }).length;
        });
        assert.equal(collisions, 0);
        assert.equal(await page.getByRole('button', { name: 'Save responses', exact: true }).count(), 1);
        assert.equal(await page.locator('.ee-hummingbird').count(), 1);
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto(info.origin + '/login');
        await page.getByLabel('Email address').fill(info.owner.email);
        await page.getByLabel('Password', { exact: true }).fill(info.owner.password);
        await page.getByRole('button', { name: 'Log in', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await page.goto(info.previewUrl);
        const frame = page.frameLocator('iframe[title="Wedding website preview"]');
        await frame.locator('[data-ee-phase="landed"]').waitFor();
        await assertContact(page, frame.locator('[data-landing-id="section:invitation"]'));
        assert.equal(await frame.locator('.ee-hummingbird').count(), 1);
        await page.goto(info.previewUrl + '/saved-preview');
        await page.locator('[data-ee-phase="landed"]').waitFor();
        await assertContact(page, page.locator('[data-landing-id="section:invitation"]'));
        assert.equal(await page.locator('.ee-hummingbird').count(), 1);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { test } from 'node:test';
import { createCmsEditorPreview } from './cms-editor-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
const baseline = process.env.QA_CMS_UX_BASELINE === '1';

test(
    baseline ? 'capture CMS workspace before refinement' : 'full-width CMS workspace reflows without hiding editing or preview controls',
    { skip: !enabled, timeout: 180000 },
    async () => {
        const host = await createCmsEditorPreview({ publishing: true });
        const artifacts = join(host.root, baseline ? 'cms-ux-before' : 'cms-ux-after');
        await mkdir(artifacts);
        const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
        const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        try {
            await page.goto(host.origin + '/login');
            await page.getByLabel('Email address').fill(host.owner.email);
            await page.getByLabel('Password', { exact: true }).fill(host.owner.password);
            await page.getByRole('button', { name: 'Log in', exact: true }).click();
            await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
            const overview = page.url();
            assert.ok(await page.locator('[data-sidebar="sidebar"]').count());
            await page.getByRole('link', { name: 'Edit wedding website', exact: true }).click();
            await page.getByText('Preview content checked', { exact: true }).waitFor();
            const measurements = [];
            for (const [width, height] of [
                [1366, 768],
                [1440, 900],
                [1920, 1080],
                [820, 1180],
                [390, 844],
            ]) {
                await page.setViewportSize({ width, height });
                const metrics = await page.evaluate(() => {
                    const rect = (selector) => {
                        const { x, y, width, height } = document.querySelector(selector).getBoundingClientRect();
                        return { x, y, width, height };
                    };
                    return {
                        width: innerWidth,
                        height: innerHeight,
                        dpr: devicePixelRatio,
                        sidebar: Boolean(document.querySelector('[data-sidebar="sidebar"]')),
                        controls: rect('.cms-editor-pane'),
                        preview: rect('iframe[title="Wedding website preview"]'),
                        overflow: document.documentElement.scrollWidth > innerWidth + 1,
                        pageScroll: document.documentElement.scrollHeight > innerHeight + 2,
                    };
                });
                measurements.push(metrics);
                if (!baseline) {
                    assert.equal(metrics.sidebar, false);
                    assert.equal(metrics.overflow, false);
                    if (width >= 1024) {
                        assert.ok(metrics.controls.width >= 340 && metrics.controls.width <= 420, JSON.stringify(metrics));
                        assert.ok(metrics.preview.width >= width - 480, JSON.stringify(metrics));
                        assert.ok(metrics.preview.height >= height - 250, JSON.stringify(metrics));
                        assert.equal(metrics.pageScroll, false);
                        assert.ok(metrics.preview.y + metrics.preview.height <= height + 1);
                    } else {
                        const input = page.locator('.cms-editor-fields input:not([type="checkbox"])').first();
                        assert.ok(await input.evaluate((el) => parseFloat(getComputedStyle(el).fontSize) >= 16));
                    }
                }
                await page.screenshot({ path: join(artifacts, `editor-${width}x${height}.png`), fullPage: true });
                if (width < 1024) {
                    await page.getByRole('button', { name: 'View website preview', exact: true }).click();
                    assert.ok(await page.locator('iframe').isVisible());
                    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                    await page.screenshot({ path: join(artifacts, `preview-${width}x${height}.png`), fullPage: true });
                    await page.getByRole('button', { name: 'Edit sections', exact: true }).click();
                }
            }
            await writeFile(join(artifacts, 'measurements.json'), JSON.stringify(measurements, null, 2));
            if (!baseline) {
                await page.setViewportSize({ width: 1440, height: 900 });
                await page.getByRole('button', { name: 'Our Story', exact: true }).click();
                await page.getByLabel('Story heading', { exact: true }).fill('Workspace regression story');
                await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
                await page.getByLabel('Story paragraph 1', { exact: true }).fill('A saved story for workspace regression coverage.');
                // Native document navigation must protect edits both through the return link and browser Back.
                for (const navigate of [
                    () => page.getByRole('link', { name: 'Back to wedding', exact: true }).click(),
                    () => page.goBack({ timeout: 1500 }),
                ]) {
                    const warning = page.waitForEvent('dialog');
                    const navigation = navigate().catch((error) => {
                        assert.match(error.message, /ERR_ABORTED|interrupted|page.goBack: Timeout 1500ms exceeded/);
                    });
                    const dialog = await warning;
                    assert.equal(dialog.type(), 'beforeunload');
                    await dialog.dismiss();
                    await navigation;
                    assert.equal(page.url(), host.previewUrl);
                    assert.equal(await page.getByLabel('Story heading', { exact: true }).inputValue(), 'Workspace regression story');
                }
                await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
                await page.getByText('All changes saved', { exact: false }).waitFor();
                const savedTab = context.waitForEvent('page');
                await page.getByRole('link', { name: 'View saved draft', exact: true }).click();
                const saved = await savedTab;
                await saved.getByRole('heading', { name: 'Workspace regression story', exact: true }).waitFor();
                await saved.close();
                await page.getByRole('button', { name: 'Publish', exact: true }).click();
                await page.getByRole('dialog').getByRole('button', { name: 'Confirm Publish', exact: true }).click();
                await page.getByRole('button', { name: 'Published', exact: true }).waitFor();
                await page.setViewportSize({ width: 1366, height: 768 });
                assert.ok(await page.evaluate(() => document.documentElement.scrollHeight <= innerHeight + 2));
                assert.ok(await page.locator('iframe').evaluate((el) => el.getBoundingClientRect().height > 250));
                await page.screenshot({ path: join(artifacts, 'published-editor-1366x768.png') });
                await page.setViewportSize({ width: 1366, height: 500 });
                assert.equal(await page.locator('.cms-editor-fields').evaluate((el) => getComputedStyle(el).overflowY), 'visible');
                assert.ok(await page.evaluate(() => document.documentElement.scrollHeight > innerHeight));
                await page.getByLabel('Story paragraph 1', { exact: true }).focus();
                assert.ok(
                    await page.getByLabel('Story paragraph 1', { exact: true }).evaluate((el) => {
                        const box = el.getBoundingClientRect();
                        return box.top >= 0 && box.bottom <= innerHeight;
                    }),
                );
                await page.screenshot({ path: join(artifacts, 'short-window-1366x500.png') });
                await page.setViewportSize({ width: 1440, height: 900 });
                await page.getByRole('button', { name: 'Welcome', exact: true }).click();
                const picker = page.getByRole('button', { name: 'Choose photo', exact: true });
                await picker.focus();
                await page.keyboard.press('Enter');
                await page.getByRole('dialog').waitFor();
                await page.keyboard.press('Escape');
                await page.getByRole('dialog').waitFor({ state: 'hidden' });
                assert.equal(await picker.evaluate((el) => el === document.activeElement), true);
                await page.getByRole('link', { name: 'Back to wedding', exact: true }).click();
                await page.waitForURL(overview);
                assert.ok(await page.locator('[data-sidebar="sidebar"]').isVisible());
            }
            assert.deepEqual(errors, []);
            console.log('CMS workspace evidence: ' + artifacts);
        } finally {
            await context.close();
            await browser.close();
            await host.close();
        }
    },
);

test('CMS workspace remains keyboard usable at genuine 200 percent browser zoom', { skip: !enabled || baseline, timeout: 120000 }, async () => {
    const host = await createCmsEditorPreview({ publishing: true });
    const artifacts = join(host.root, 'cms-ux-zoom');
    const profile = join(host.root, 'zoom-profile');
    await mkdir(artifacts);
    await mkdir(join(profile, 'Default'), { recursive: true });
    // Chromium's native profile zoom preference, confined to this disposable browser profile.
    // https://github.com/chromium/chromium/blob/main/chrome/browser/ui/zoom/chrome_zoom_level_prefs.cc
    await writeFile(
        join(profile, 'Default', 'Preferences'),
        JSON.stringify({ partition: { default_zoom_level: { x: Math.log(2) / Math.log(1.2) } } }),
    );
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const context = await chromium.launchPersistentContext(profile, {
        executablePath: process.env.QA_BROWSER_EXECUTABLE,
        headless: true,
        viewport: { width: 1440, height: 900 },
        reducedMotion: 'reduce',
    });
    const page = await context.newPage();
    try {
        await page.goto(host.origin + '/login');
        await page.getByLabel('Email address').fill(host.owner.email);
        await page.getByLabel('Password', { exact: true }).fill(host.owner.password);
        await page.getByRole('button', { name: 'Log in', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        await page.getByRole('link', { name: 'Invitation', exact: true }).click();
        await page.getByText('Preview content checked', { exact: true }).waitFor();
        assert.deepEqual(await page.evaluate(() => ({ width: innerWidth, dpr: devicePixelRatio })), { width: 720, dpr: 2 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        const story = page.getByRole('button', { name: 'Our Story', exact: true });
        await story.focus();
        await page.keyboard.press('Enter');
        await page.getByLabel('Story heading', { exact: true }).fill('Readable at browser zoom');
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('All changes saved', { exact: false }).waitFor();
        const protocol = await context.newCDPSession(page);
        const capture = async (name) => {
            const shot = await protocol.send('Page.captureScreenshot', { format: 'png', fromSurface: true });
            await writeFile(join(artifacts, name), Buffer.from(shot.data, 'base64'));
        };
        await page.evaluate(() => window.scrollTo(0, 0));
        await capture('editor-1440x900-200-percent.png');
        const preview = page.getByRole('button', { name: 'View website preview', exact: true });
        await preview.focus();
        await page.keyboard.press('Enter');
        assert.ok(await page.locator('iframe').isVisible());
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await capture('preview-1440x900-200-percent.png');
        console.log('CMS browser zoom evidence: ' + artifacts);
    } finally {
        await context.close();
        await host.close();
    }
});

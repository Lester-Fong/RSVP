import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

// Opt in only against an isolated local SQLite server, never the development database.
const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;

test('dashboard containment and real multipart photo saves', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    const first = 'Long Wedding Name '.repeat(7).trim();
    const second = 'W'.repeat(120);

    try {
        await page.goto(`${origin}/register`);
        await page.getByLabel('Name', { exact: true }).fill('Regression QA');
        await page.getByLabel('Email address').fill(`qa-${randomUUID()}@example.test`);
        const password = `${randomUUID()}Aa1!`;
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password').fill(password);
        await page.getByRole('button', { name: 'Create account', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await page.goto(`${origin}/weddings/create`);
        await page.getByLabel('Partner 1 name').fill(first);
        await page.getByLabel('Partner 2 name').fill(second);
        await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const overview = page.url();

        for (const [one, two] of [
            [first, second],
            [second, first],
        ]) {
            await page.getByRole('link', { name: 'Edit wedding', exact: true }).click();
            await page.getByLabel('Partner 1 name').fill(one);
            await page.getByLabel('Partner 2 name').fill(two);
            await page.getByRole('button', { name: 'Save changes', exact: true }).click();
            await page.waitForURL(overview);
            await page.goto(`${origin}/dashboard`);
            for (const width of [1440, 1024, 390]) {
                for (const theme of ['light', 'dark']) {
                    await page.setViewportSize({ width, height: 1000 });
                    await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                    const heading = page.locator('h2').first();
                    assert.equal(await heading.innerText(), `${one} & ${two}`);
                    assert.equal(await heading.getAttribute('title'), `${one} & ${two}`);
                    const bounds = await heading.evaluate((element) => {
                        const style = getComputedStyle(element);
                        const card = element.closest('a');
                        const box = element.getBoundingClientRect();
                        const cardBox = card.getBoundingClientRect();
                        return {
                            clamp: style.webkitLineClamp,
                            overflow: style.overflow,
                            wrap: style.overflowWrap,
                            height: box.height,
                            lineHeight: parseFloat(style.lineHeight),
                            clipped: element.scrollHeight > element.clientHeight,
                            contained: box.left >= cardBox.left && box.right <= cardBox.right,
                            noOverflow: document.documentElement.scrollWidth <= innerWidth && card.scrollWidth <= card.clientWidth,
                        };
                    });
                    assert.equal(bounds.clamp, '2');
                    assert.equal(bounds.overflow, 'hidden');
                    assert.equal(bounds.wrap, 'anywhere');
                    assert.ok(bounds.height <= bounds.lineHeight * 2 + 1);
                    assert.ok(bounds.clipped && bounds.contained && bounds.noOverflow, JSON.stringify(bounds));
                    await heading.locator('..').locator('..').locator('..').focus();
                    assert.ok(await heading.evaluate((element) => getComputedStyle(element.closest('a')).outlineWidth !== '0px'));
                    await page.screenshot({ path: join(tmpdir(), `rsvp-bugfix-${width}-${theme}.png`), animations: 'disabled' });
                }
            }
            await page.goto(overview);
        }

        await page.getByRole('link', { name: 'Customize invitation', exact: true }).click();
        await page.waitForURL('**/invitation/edit');
        const editor = page.url();
        const png = await page.evaluate(() => {
            const canvas = document.createElement('canvas');
            canvas.width = 1100;
            canvas.height = 1000;
            const ctx = canvas.getContext('2d');
            const pixels = ctx.createImageData(canvas.width, canvas.height);
            let seed = 17;
            for (let i = 0; i < pixels.data.length; i++) {
                seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
                pixels.data[i] = i % 4 === 3 ? 255 : seed >>> 24;
            }
            ctx.putImageData(pixels, 0, 0);
            return canvas.toDataURL('image/png').split(',')[1];
        });
        const image = Buffer.from(png, 'base64');
        assert.ok(image.length > 2 * 1024 * 1024 && image.length <= 5 * 1024 * 1024);
        await page.locator('#hero-photo').setInputFiles({ name: 'large-valid.png', mimeType: 'image/png', buffer: image });
        await page.getByLabel('Introduction message', { exact: true }).fill('Saved with a real multipart upload');
        await page.getByRole('checkbox', { name: /Reception details/ }).uncheck();
        const submission = page.waitForRequest((request) => request.method() === 'POST' && request.url().endsWith('/invitation'));
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        const request = await submission;
        assert.equal(new URL(request.url()).origin, origin);
        assert.match(request.headers()['content-type'], /multipart\/form-data/);
        await page.getByText('Draft saved', { exact: true }).waitFor();
        await page.reload();
        assert.equal(await page.getByLabel('Introduction message', { exact: true }).inputValue(), 'Saved with a real multipart upload');
        assert.equal(await page.getByRole('checkbox', { name: /Reception details/ }).isChecked(), false);
        const photoUrl = await page.locator('.invitation-hero-photo').getAttribute('src');
        const photo = await context.request.get(photoUrl);
        assert.equal(photo.status(), 200);
        assert.equal(photo.headers()['content-type'], 'image/webp');
        assert.match(photo.headers()['cache-control'], /private/);
        const savedBytes = await photo.body();

        if (process.env.QA_UNPREPARED_BASE_URL) {
            const unprepared = process.env.QA_UNPREPARED_BASE_URL;
            assert.match(unprepared, /^http:\/\/127\.0\.0\.1:\d+$/);
            await page.goto(editor.replace(origin, unprepared));
            await page.locator('#hero-photo').setInputFiles({ name: 'large-valid.png', mimeType: 'image/png', buffer: image });
            await page.getByLabel('Introduction message', { exact: true }).fill('Must not save after PHP rejection');
            await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
            await page
                .locator('#photo-error')
                .filter({ hasText: /exceeds the server upload limit/ })
                .waitFor();
            assert.match(await page.locator('.invitation-hero-photo').getAttribute('src'), /^blob:/);
            assert.equal(await page.getByLabel('Introduction message', { exact: true }).inputValue(), 'Must not save after PHP rejection');
            const smallPng = await page.evaluate(() => document.createElement('canvas').toDataURL('image/png').split(',')[1]);
            await page.locator('#hero-photo').setInputFiles({ name: 'small.png', mimeType: 'image/png', buffer: Buffer.from(smallPng, 'base64') });
            await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
            await page
                .locator('#photo-error')
                .filter({ hasText: /Photo uploads are temporarily unavailable/ })
                .waitFor();
            await page.goto(editor);
            assert.equal(await page.getByLabel('Introduction message', { exact: true }).inputValue(), 'Saved with a real multipart upload');
            assert.equal(await page.locator('.invitation-hero-photo').getAttribute('src'), photoUrl);
            assert.deepEqual(await (await context.request.get(photoUrl)).body(), savedBytes);
        }

        const token = decodeURIComponent((await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN').value);
        const saveUrl = editor.replace('/edit', '');
        for (const invalid of [
            { name: 'invalid.png', mimeType: 'image/png', buffer: Buffer.from('not an image') },
            { name: 'oversized.png', mimeType: 'image/png', buffer: Buffer.concat([image, Buffer.alloc(5 * 1024 * 1024 + 1 - image.length)]) },
        ]) {
            const response = await context.request.post(saveUrl, {
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
                multipart: {
                    _method: 'put',
                    draft_version: '1',
                    hero_photo_action: 'replace',
                    hero_photo: invalid,
                    content: JSON.stringify({
                        introduction_message: 'Must not save',
                        closing_message: 'Must not save',
                        sections: { ceremony: true, reception: true, closing: true },
                    }),
                },
            });
            assert.equal(response.status(), 422);
            const result = await response.json();
            assert.ok(result.errors.hero_photo, JSON.stringify(result));
            await page.reload();
            assert.equal(await page.getByLabel('Introduction message', { exact: true }).inputValue(), 'Saved with a real multipart upload');
            assert.equal(await page.locator('.invitation-hero-photo').getAttribute('src'), photoUrl);
            assert.deepEqual(await (await context.request.get(photoUrl)).body(), savedBytes);
        }
        await page.getByLabel('Introduction message', { exact: true }).fill('Text-only save still works');
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('Draft saved', { exact: true }).waitFor();
        await page.reload();
        assert.equal(await page.getByLabel('Introduction message', { exact: true }).inputValue(), 'Text-only save still works');
        assert.equal(await page.locator('.invitation-hero-photo').getAttribute('src'), photoUrl);
        assert.deepEqual(pageErrors, []);
        console.log(`Real multipart PNG: ${image.length} bytes; save/reload, rejected replacements, text-only save passed.`);
    } finally {
        await browser.close();
    }
});

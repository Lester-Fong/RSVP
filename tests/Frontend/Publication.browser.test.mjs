import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;

test('publication controls, anonymous snapshot rendering and unpublishing', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY = '1';
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    const anonymous = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const visitor = await anonymous.newPage();
    visitor.on('pageerror', (error) => pageErrors.push(error.message));

    async function selectPhoto(color) {
        const png = await page.evaluate((fill) => {
            const canvas = document.createElement('canvas');
            canvas.width = 400;
            canvas.height = 500;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = fill;
            ctx.fillRect(0, 0, 400, 500);
            return canvas.toDataURL('image/png').split(',')[1];
        }, color);
        await page.locator('#hero-photo').setInputFiles({ name: 'synthetic.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
    }

    async function save() {
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('Draft saved', { exact: true }).waitFor();
    }

    try {
        await page.goto(`${origin}/register`);
        await page.getByLabel('Name', { exact: true }).fill('Publication QA');
        await page.getByLabel('Email address').fill(`publish-${randomUUID()}@example.test`);
        const password = `${randomUUID()}Aa1!`;
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password').fill(password);
        await page.getByRole('button', { name: 'Create account', exact: true }).click();
        await page.waitForURL('**/onboarding');
        await page.goto(`${origin}/weddings/create`);
        await page.getByLabel('Partner 1 name').fill('Publishing Alex');
        await page.getByLabel('Partner 2 name').fill('Publishing Jamie');
        await page.getByLabel(/Wedding date/).fill('2027-05-12');
        await page.getByLabel(/Wedding time/).fill('16:30');
        await page.getByLabel(/Ceremony venue/).fill('Our chapel');
        await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
        await page.waitForURL('**/onboarding/congratulations');
        await page.getByRole('link', { name: 'Enter your wedding dashboard', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const overview = page.url();
        assert.equal(await page.getByRole('button', { name: 'Publish invitation', exact: true }).isDisabled(), true);
        await page.getByRole('link', { name: 'Invitation', exact: true }).click();
        await page.waitForURL('**/invitation/edit');
        const editor = page.url();
        await selectPhoto('#887766');
        await page.getByLabel('Introduction message', { exact: true }).fill('Our published message');
        await save();
        await page.goto(overview);

        for (const width of [1440, 1024, 390]) {
            for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                const trigger = page.getByRole('button', { name: 'Publish invitation', exact: true });
                await trigger.click();
                const dialog = page.getByRole('dialog');
                await dialog.waitFor();
                const box = await dialog.boundingBox();
                assert.ok(box.x >= 0 && box.x + box.width <= width && box.y >= 0 && box.y + box.height <= 1000);
                for (let i = 0; i < 6; i++) {
                    await page.keyboard.press('Tab');
                    assert.ok(await dialog.evaluate((element) => element.contains(document.activeElement)));
                }
                await page.screenshot({ path: join(tmpdir(), `rsvp-publish-dialog-${width}-${theme}.png`), animations: 'disabled' });
                await page.keyboard.press('Escape');
                await dialog.waitFor({ state: 'hidden' });
                assert.ok(await trigger.evaluate((element) => document.activeElement === element));
                await page.screenshot({ path: join(tmpdir(), `rsvp-publish-portal-${width}-${theme}.png`), animations: 'disabled' });
            }
        }

        await page.getByRole('button', { name: 'Publish invitation', exact: true }).click();
        await page.getByRole('button', { name: 'Publish now', exact: true }).click();
        await page.getByRole('heading', { name: 'Published', exact: true }).waitFor();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        const publicUrl = await page.locator('#public-wedding-url').inputValue();
        assert.equal(new URL(publicUrl).origin, origin);
        assert.ok(await page.locator('#public-wedding-url').evaluate((element) => document.activeElement === element));
        await page.evaluate(() =>
            Object.defineProperty(navigator, 'clipboard', {
                configurable: true,
                value: {
                    writeText: async (value) => {
                        window.__rsvpCopiedUrl = value;
                    },
                },
            }),
        );
        await page.getByRole('button', { name: 'Copy public URL', exact: true }).click();
        await page.getByText('Public URL copied.', { exact: true }).waitFor();
        assert.equal(await page.evaluate(() => window.__rsvpCopiedUrl), publicUrl);
        await page.evaluate(() =>
            Object.defineProperty(navigator, 'clipboard', {
                configurable: true,
                value: {
                    writeText: async () => {
                        throw new Error('Clipboard unavailable');
                    },
                },
            }),
        );
        await page.getByRole('button', { name: 'Copy public URL', exact: true }).click();
        await page.getByText('Select and copy the URL above.', { exact: true }).waitFor();
        assert.ok(
            await page
                .locator('#public-wedding-url')
                .evaluate((element) => document.activeElement === element && element.selectionEnd - element.selectionStart === element.value.length),
        );
        const html = await anonymous.request.get(publicUrl);
        assert.equal(html.status(), 200);
        assert.match(await html.text(), /property="og:title"/);
        assert.doesNotMatch(await html.text(), /"auth"|account_id|hero_photo_path|draft_version/);
        await visitor.goto(publicUrl);
        await visitor.getByRole('heading', { name: /Publishing Alex/ }).waitFor();
        const photoUrl = await visitor.locator('.invitation-hero-photo').getAttribute('src');
        assert.equal((await anonymous.request.get(photoUrl)).status(), 200);

        const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const noJsPage = await noJs.newPage();
        await noJsPage.goto(publicUrl);
        assert.equal(
            (await noJsPage.locator('.invitation-card').innerText()).replace(/\s+/g, ' ').trim(),
            (await visitor.locator('.invitation-card').innerText()).replace(/\s+/g, ' ').trim(),
        );
        for (const target of [visitor, noJsPage]) {
            assert.equal(await target.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            const styles = await target
                .locator('.invitation-card')
                .evaluate((element) => ({ background: getComputedStyle(element).backgroundColor, font: getComputedStyle(element).fontFamily }));
            assert.equal(styles.background, 'rgb(251, 249, 244)');
            assert.match(styles.font, /Georgia/);
        }
        await noJsPage.screenshot({ path: join(tmpdir(), 'rsvp-publish-public-nojs-390.png') });
        await noJs.close();
        await visitor.screenshot({ path: join(tmpdir(), 'rsvp-publish-public-390.png'), animations: 'disabled' });

        await page.goto(editor);
        await selectPhoto('#667788');
        await page.getByLabel('Introduction message', { exact: true }).fill('Unpublished draft changes');
        await save();
        await page.reload();
        const privatePhotoUrl = await page.locator('.invitation-hero-photo').getAttribute('src');
        assert.equal((await anonymous.request.get(privatePhotoUrl, { maxRedirects: 0 })).status(), 302);
        await visitor.reload();
        assert.equal(await visitor.locator('.invitation-intro').innerText(), 'Our published message');
        assert.equal(await visitor.locator('.invitation-hero-photo').getAttribute('src'), photoUrl);
        assert.equal((await anonymous.request.get(photoUrl)).status(), 200);
        await page.goto(overview);
        await page.getByText('Your saved draft has unpublished changes.', { exact: true }).waitFor();
        await page.getByRole('button', { name: 'Republish changes', exact: true }).click();
        await page.getByRole('button', { name: 'Republish', exact: true }).click();
        await page.getByText('Your published invitation matches the saved draft.', { exact: true }).waitFor();
        assert.equal(await page.getByRole('button', { name: 'Republish changes', exact: true }).count(), 0);
        await visitor.reload();
        assert.equal(await visitor.locator('.invitation-intro').innerText(), 'Unpublished draft changes');
        const nextPhoto = await visitor.locator('.invitation-hero-photo').getAttribute('src');
        assert.notEqual(nextPhoto, photoUrl);
        assert.equal((await anonymous.request.get(photoUrl)).status(), 404);
        await page.getByRole('button', { name: 'Website actions', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Unpublish invitation', exact: true }).click();
        await page.keyboard.press('Escape');
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        assert.ok(await page.getByRole('button', { name: 'Website actions', exact: true }).evaluate((element) => document.activeElement === element));
        await page.getByRole('button', { name: 'Website actions', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Unpublish invitation', exact: true }).click();
        await page.getByRole('button', { name: 'Unpublish invitation', exact: true }).click();
        await page.getByRole('heading', { name: 'Draft', exact: true }).waitFor();
        assert.ok(await page.locator('#publication-heading').evaluate((element) => document.activeElement === element));
        assert.equal((await anonymous.request.get(publicUrl)).status(), 404);
        assert.equal((await anonymous.request.get(nextPhoto)).status(), 404);
        await page.getByText(/^Last published /).waitFor();
        assert.deepEqual(pageErrors, []);
    } finally {
        await browser.close();
    }
});

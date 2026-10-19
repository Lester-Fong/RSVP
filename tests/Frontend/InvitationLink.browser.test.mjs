import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;
test('secure invitation links, current group names, publication gating and responsive controls', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.setDefaultNavigationTimeout(15000);
    const visitorContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const visitor = await visitorContext.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    visitor.on('pageerror', (error) => pageErrors.push(error.message));
    async function openLink() {
        await page.getByRole('button', { name: 'Actions for Santos Family', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Invitation link', exact: true }).click();
        await page.getByRole('dialog').waitFor();
    }
    async function finishDialog(button) {
        await page.getByRole('button', { name: button, exact: true }).click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    try {
        await page.goto(origin + '/register');
        await page.getByLabel('Name', { exact: true }).fill('Link QA');
        await page.getByLabel('Email address').fill('links-' + randomUUID() + '@example.test');
        const password = randomUUID() + 'Aa1!';
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password').fill(password);
        await page.getByRole('button', { name: 'Create account', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await page.goto(origin + '/weddings/create');
        await page.getByLabel('Partner 1 name').fill('Link QA Alex');
        await page.getByLabel('Partner 2 name').fill('Link QA Jamie');
        await page.getByLabel(/Wedding date/).fill('2027-05-12');
        await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const overview = page.url();
        await page.getByRole('link', { name: 'Customize invitation', exact: true }).click();
        await page.getByLabel('Introduction message', { exact: true }).fill('Published invitation message');
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('Draft saved', { exact: true }).waitFor();
        await page.goto(overview);
        await page.getByRole('link', { name: 'Guest Management', exact: true }).click();
        await page.waitForURL('**/guests');
        const guestList = page.url();
        await page.getByRole('button', { name: 'New invitation group', exact: true }).click();
        await page.getByLabel('Group label', { exact: true }).fill('Santos Family');
        await page.getByLabel('Contact email (optional)').fill('private-family@example.test');
        await page.getByLabel('Guest 1 name', { exact: true }).fill('Named Link Guest');
        await finishDialog('Create group');
        await openLink();
        await page.getByRole('button', { name: 'Generate link', exact: true }).click();
        const input = page.getByLabel('Invitation URL', { exact: true });
        await input.waitFor();
        const first = await input.inputValue();
        assert.match(new URL(first).pathname, /^\/invite\/[a-f0-9]{64}$/);
        assert.equal((await visitorContext.request.get(first)).status(), 404);
        await finishDialog('Done');
        assert.equal(await page.locator('#personalized-invitation-url').count(), 0);
        await page.goto(overview);
        await page.getByRole('button', { name: 'Publish invitation', exact: true }).click();
        await page.getByRole('button', { name: 'Publish now', exact: true }).click();
        await page.getByRole('heading', { name: 'Published', exact: true }).waitFor();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        await page.goto(guestList);
        await openLink();
        assert.equal(await input.count(), 0);
        await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async () => {} } }));
        await page.getByRole('button', { name: 'Copy link', exact: true }).click();
        await page.getByText('Invitation link copied.', { exact: true }).waitFor();
        assert.equal(await input.inputValue(), first);
        await finishDialog('Done');
        for (const width of [1440, 1024, 390]) {
            for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                await openLink();
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                const dialog = page.getByRole('dialog');
                const box = await dialog.boundingBox();
                assert.ok(box.x >= 0 && box.x + box.width <= width && box.y >= 0 && box.y + box.height <= 1000);
                for (let step = 0; step < 8; step++) {
                    await page.keyboard.press('Tab');
                    assert.ok(await dialog.evaluate((element) => element.contains(document.activeElement)));
                }
                await page.screenshot({ path: join(tmpdir(), 'rsvp-links-dialog-' + width + '-' + theme + '.png'), animations: 'disabled' });
                await page.keyboard.press('Escape');
                await dialog.waitFor({ state: 'hidden' });
                assert.ok(
                    await page
                        .getByRole('button', { name: 'Actions for Santos Family', exact: true })
                        .evaluate((element) => document.activeElement === element),
                );
            }
        }
        const publicResponse = await visitor.goto(first);
        assert.equal(publicResponse.status(), 200);
        assert.equal(publicResponse.headers()['referrer-policy'], 'no-referrer');
        assert.match(publicResponse.headers()['cache-control'], /no-store/);
        await visitor.getByRole('heading', { name: "You're invited, Santos Family!", exact: true }).waitFor();
        await visitor.locator('.invitation-greeting').getByText('Named Link Guest', { exact: true }).waitFor();
        assert.equal(await visitor.locator('title').textContent(), 'Wedding invitation');
        assert.equal(await visitor.locator('meta[property="og:title"]').getAttribute('content'), 'Wedding invitation');
        const html = await publicResponse.text();
        for (const privateValue of ['private-family@example.test', 'invitation_token', 'account_id', 'invitation_group_id', basename(first)])
            assert.ok(!html.includes(privateValue));
        assert.equal(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await visitor.screenshot({ path: join(tmpdir(), 'rsvp-links-public-390.png') });
        const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const staticPage = await noJs.newPage();
        await staticPage.goto(first);
        assert.equal(await staticPage.locator('.invitation-greeting').innerText(), await visitor.locator('.invitation-greeting').innerText());
        assert.equal(await staticPage.locator('#public-invitation').innerText(), await visitor.locator('#public-invitation').innerText());
        await noJs.close();
        await page.getByRole('button', { name: 'Edit guest Named Link Guest', exact: true }).click();
        await page.getByLabel('Guest name', { exact: true }).fill('Updated Saved Guest');
        await finishDialog('Save changes');
        await visitor.reload();
        await visitor.locator('.invitation-greeting').getByText('Updated Saved Guest', { exact: true }).waitFor();
        await openLink();
        await page.evaluate(() =>
            Object.defineProperty(navigator, 'clipboard', {
                configurable: true,
                value: {
                    writeText: async () => {
                        throw new Error('Denied');
                    },
                },
            }),
        );
        await page.getByRole('button', { name: 'Copy link', exact: true }).click();
        await page.getByText('Select and copy the link below.', { exact: true }).waitFor();
        assert.ok(await input.evaluate((element) => document.activeElement === element));
        const stale = await context.newPage();
        stale.setDefaultTimeout(15000);
        await stale.goto(guestList);
        await page.getByRole('button', { name: 'Regenerate link', exact: true }).click();
        await page.getByRole('button', { name: 'Cancel', exact: true }).click();
        assert.equal((await visitorContext.request.get(first)).status(), 200);
        await page.getByRole('button', { name: 'Regenerate link', exact: true }).click();
        await page.getByRole('button', { name: 'Regenerate this link', exact: true }).click();
        await input.waitFor();
        const second = await input.inputValue();
        assert.notEqual(first, second);
        assert.equal((await visitorContext.request.get(first)).status(), 404);
        assert.equal((await visitorContext.request.get(second)).status(), 200);
        await stale.getByRole('button', { name: 'Actions for Santos Family', exact: true }).click();
        await stale.getByRole('menuitem', { name: 'Invitation link', exact: true }).click();
        await stale.getByRole('button', { name: 'Copy link', exact: true }).click();
        await stale.getByRole('alert').filter({ hasText: 'changed in another window' }).waitFor();
        assert.equal(await stale.locator('#personalized-invitation-url').count(), 0);
        await stale.close();
        await page.getByRole('button', { name: 'Revoke link', exact: true }).click();
        await page.getByRole('button', { name: 'Revoke this link', exact: true }).click();
        await page.getByText('Link: Revoked', { exact: true }).last().waitFor();
        assert.equal((await visitorContext.request.get(second)).status(), 404);
        await finishDialog('Done');
        await page.getByRole('button', { name: 'Actions for Santos Family', exact: true }).click();
        assert.equal(await page.getByRole('menuitem', { name: 'Delete group', exact: true }).count(), 0);
        await page.keyboard.press('Escape');
        await openLink();
        await page.getByRole('button', { name: 'Regenerate link', exact: true }).click();
        await page.getByRole('button', { name: 'Regenerate this link', exact: true }).click();
        await input.waitFor();
        const third = await input.inputValue();
        assert.equal((await visitorContext.request.get(third)).status(), 200);
        await finishDialog('Done');
        await page.goto(overview);
        await page.getByRole('button', { name: 'Unpublish', exact: true }).click();
        await page.getByRole('button', { name: 'Unpublish invitation', exact: true }).click();
        await page.getByRole('heading', { name: 'Draft', exact: true }).waitFor();
        assert.equal((await visitorContext.request.get(third)).status(), 404);
        assert.deepEqual(pageErrors, []);
    } finally {
        await browser.close();
    }
});
function basename(url) {
    return new URL(url).pathname.split('/').pop();
}

import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { test } from 'node:test';
import { createCmsEditorPreview } from './cms-editor-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
async function register(page, origin) {
    const email = 'onboarding-' + randomUUID() + '@example.test';
    const password = randomUUID() + 'Aa1!';
    await page.goto(origin + '/register');
    await page.getByLabel('Name', { exact: true }).fill('Onboarding QA');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button', { name: 'Create account', exact: true }).click();
    await page.waitForURL('**/onboarding');
    return { email, password };
}
async function csrf(context) {
    const cookie = (await context.cookies()).find((value) => value.name === 'XSRF-TOKEN');
    assert.ok(cookie);
    return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value), Accept: 'application/json' };
}
for (const [width, motion] of [
    [1440, 'no-preference'],
    [390, 'reduce'],
]) {
    test('mandatory onboarding, saved-name celebration and returning single dashboard at ' + width, { skip: !enabled, timeout: 180000 }, async () => {
        const host = await createCmsEditorPreview({ publishing: true, personal: true });
        assert.notEqual(new URL(host.origin).port, '8000');
        const artifacts = join(host.root, 'qa-onboarding');
        await mkdir(artifacts);
        const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
        const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
        const context = await browser.newContext({ viewport: { width, height: 1000 }, reducedMotion: motion, recordVideo: { dir: artifacts } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        try {
            const login = await register(page, host.origin);
            assert.equal(await page.getByRole('link', { name: /Create.*wedding/ }).count(), 0);
            assert.equal(await page.getByLabel('Partner 1 name').getAttribute('required'), '');
            await page.getByLabel('Partner 1 name').fill('Marina QA');
            await page.getByLabel('Partner 2 name').fill('Elias QA');
            if (width === 1440) {
                await page.getByLabel(/Wedding date/).fill('2028-04-16');
                await page.getByLabel(/Wedding time/).fill('15:30');
                await page.getByLabel('Ceremony venue').fill('QA Chapel');
                await page.getByLabel('Reception venue').fill('QA Garden');
            }
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await page.screenshot({ path: join(artifacts, 'onboarding.png') });
            await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
            await page.waitForURL('**/onboarding/congratulations');
            await page.getByRole('heading', { name: 'Congratulations, Marina QA & Elias QA!', exact: true }).waitFor();
            const section = page.locator('main section');
            if (motion === 'reduce') assert.equal(await section.evaluate((element) => getComputedStyle(element).animationName), 'none');
            else
                await page.waitForFunction(() =>
                    getComputedStyle(document.querySelector('main section')).animationName.startsWith('welcome-arrival'),
                );
            await page.screenshot({ path: join(artifacts, 'congratulations.png'), animations: 'disabled' });
            const enter = page.getByRole('link', { name: 'Enter your wedding dashboard', exact: true });
            await enter.focus();
            await page.keyboard.press('Enter');
            await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
            const overview = page.url();
            await page.getByRole('heading', { name: 'Marina QA & Elias QA', exact: true }).waitFor();
            await page.goBack();
            assert.equal(await page.locator('.welcome-celebration').count(), 0);
            await page.goForward();
            await page.waitForURL(overview);
            assert.equal(await page.getByRole('link', { name: /Create.*wedding/ }).count(), 0);
            assert.equal(await page.getByRole('link', { name: 'Invitation', exact: true }).count(), 1);
            assert.equal(await page.getByRole('link', { name: 'Guests', exact: true }).count(), 1);
            assert.equal(await page.getByRole('link', { name: 'Attendance', exact: true }).count(), 1);
            await page.screenshot({ path: join(artifacts, 'dashboard.png') });
            await page.reload();
            await page.getByRole('heading', { name: 'Marina QA & Elias QA', exact: true }).waitFor();
            for (const path of ['/onboarding', '/onboarding/congratulations', '/dashboard', '/weddings/create']) {
                await page.goto(host.origin + path);
                assert.equal(page.url(), overview);
                assert.equal(await page.getByRole('heading', { name: /^Congratulations/ }).count(), 0);
            }
            const accountPath = new URL(overview).pathname.replace(/\/weddings\/\d+$/, '');
            assert.equal((await context.request.get(host.origin + accountPath + '/weddings/create')).status(), 403);
            const headers = await csrf(context);
            assert.equal(
                (
                    await context.request.post(host.origin + accountPath + '/weddings', {
                        headers,
                        data: { partner_one_name: 'Second', partner_two_name: 'Blocked' },
                    })
                ).status(),
                409,
            );
            const responses = await Promise.all(
                [1, 2].map(() =>
                    context.request.post(host.origin + '/weddings', {
                        headers,
                        data: { partner_one_name: 'Changed', partner_two_name: 'Retry' },
                        maxRedirects: 0,
                    }),
                ),
            );
            for (const response of responses) assert.match(response.headers().location, /\/dashboard$/);
            await page.goto(overview);
            await page.getByRole('heading', { name: 'Marina QA & Elias QA', exact: true }).waitFor();
            await context.request.post(host.origin + '/logout', { headers });
            await page.goto(host.origin + '/login');
            await page.getByLabel('Email address').fill(login.email);
            await page.getByLabel('Password', { exact: true }).fill(login.password);
            await page.getByRole('button', { name: 'Log in', exact: true }).click();
            await page.waitForURL(overview);
            assert.deepEqual(errors, []);
            console.log('ONB-01 artifacts: ' + artifacts);
        } finally {
            await context.close();
            await browser.close();
            await host.close();
        }
    });
}
test('overlapping first requests result in one workspace, one wedding and one creation redirect', { skip: !enabled, timeout: 180000 }, async () => {
    const host = await createCmsEditorPreview({ publishing: true, personal: true });
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const context = await browser.newContext();
    try {
        const page = await context.newPage();
        await register(page, host.origin);
        const headers = await csrf(context);
        const responses = await Promise.all(
            [1, 2].map(() =>
                context.request.post(host.origin + '/weddings', {
                    headers,
                    data: { partner_one_name: 'Concurrent', partner_two_name: 'QA' },
                    maxRedirects: 0,
                }),
            ),
        );
        assert.deepEqual(responses.map((response) => new URL(response.headers().location, host.origin).pathname).sort(), [
            '/dashboard',
            '/onboarding/congratulations',
        ]);
        await page.goto(host.origin + '/dashboard');
        assert.match(page.url(), /\/accounts\/\d+\/weddings\/\d+$/);
        await page.getByRole('heading', { name: 'Concurrent & QA', exact: true }).waitFor();
        await page.goto(host.origin + '/onboarding/congratulations');
        assert.match(page.url(), /\/accounts\/\d+\/weddings\/\d+$/);
    } finally {
        await browser.close();
        await host.close();
    }
});

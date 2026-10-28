import assert from 'node:assert/strict';
import test from 'node:test';
const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;
test('anonymous personal errors are private and owner credential recovery requires authentication', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    try {
        const context = await browser.newContext();
        const page = await context.newPage();
        const token = 'b'.repeat(64);
        const response = await page.goto(origin + '/rsvp/' + token);
        assert.equal(response.status(), 404);
        assert.ok(response.headers()['cache-control'].includes('no-store'));
        assert.equal(response.headers()['referrer-policy'], 'no-referrer');
        assert.equal((await context.cookies()).length, 0);
        assert.ok(!(await page.locator('body').innerText()).includes(token));
        const denied = await context.request.get(origin + '/accounts/1/weddings/1/invitation-groups/1/rsvp-access', {
            headers: { Accept: 'application/json' },
        });
        assert.equal(denied.status(), 401);
        assert.ok(!(await denied.text()).includes('token'));
    } finally {
        await browser.close();
    }
});

import assert from 'node:assert/strict';
import test from 'node:test';
const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;
test('retired household routes never disclose a roster or enable group writes', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    try {
        const context = await browser.newContext();
        const page = await context.newPage();
        const token = 'a'.repeat(64);
        const response = await page.goto(origin + '/invite/' + token + '?mode=legacy');
        assert.equal(response.status(), 404);
        assert.equal(response.headers()['referrer-policy'], 'no-referrer');
        assert.ok(response.headers()['cache-control'].includes('no-store'));
        assert.equal((await context.cookies()).length, 0);
        assert.equal((await context.request.post(origin + '/invite/' + token + '/rsvp', { data: { mode: 'legacy', responses: [] } })).status(), 404);
        for (const action of ['generate', 'copy', 'revoke', 'regenerate']) {
            assert.equal(
                (
                    await context.request.post(origin + '/accounts/1/weddings/1/invitation-groups/1/invitation-link/' + action, {
                        data: { link_version: 0 },
                    })
                ).status(),
                404,
            );
        }
        assert.ok(!(await page.locator('body').innerText()).includes(token));
    } finally {
        await browser.close();
    }
});

import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import tailwindcss from 'tailwindcss';
import { createServer } from 'vite';

// This host serves only synthetic Vue fixtures. No PHP, database or application endpoint.
const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let server, browser, context, page, origin, artifacts;
const errors = [];
before(async () => {
    if (!enabled) return;
    artifacts = await mkdtemp(join(tmpdir(), 'rsvp-5c2-visual-'));
    server = await createServer({
        configFile: false,
        root: resolve('.'),
        cacheDir: join(artifacts, 'vite-cache'),
        plugins: [
            vue(),
            {
                name: 'synthetic-rsvp-host',
                configureServer(host) {
                    host.middlewares.use(async (request, response, next) => {
                        if (request.url?.split('?')[0] !== '/__personal-rsvp-fixture') return next();
                        response.setHeader('Content-Type', 'text/html');
                        response.setHeader('Cache-Control', 'no-store');
                        response.end(
                            await host.transformIndexHtml(
                                request.url,
                                '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow"></head><body><div id="fixture"></div><script type="module" src="/tests/Frontend/fixtures/personal-rsvp-entry.ts"></script></body></html>',
                            ),
                        );
                    });
                },
            },
        ],
        resolve: { alias: { '@': resolve('resources/js') } },
        css: { postcss: { plugins: [tailwindcss(), autoprefixer()] } },
        server: { host: '127.0.0.1', port: 0 },
    });
    await server.listen();
    origin = `http://127.0.0.1:${server.httpServer.address().port}`;
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    console.log('Synthetic 5C.2 visual artifacts: ' + artifacts);
});
beforeEach(async () => {
    if (!enabled) return;
    errors.length = 0;
    context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.setDefaultNavigationTimeout(45000);
    page.on('pageerror', (error) => errors.push(error.message));
});
afterEach(async () => {
    if (!enabled) return;
    await context?.close();
    assert.deepEqual(errors, []);
});
after(async () => {
    await browser?.close();
    await server?.close();
});
const button = (name) => page.getByRole('button', { name, exact: true });
async function load(view = 'owner') {
    await page.goto(origin + '/__personal-rsvp-fixture?view=' + view, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => Boolean(window.__personalRsvpFixture), null, { timeout: 45000 });
}
async function open() {
    await button('Actions for Santos Family').click();
    await page.getByRole('menuitem', { name: 'RSVP Access', exact: true }).click();
    await page.getByRole('dialog').waitFor();
}
async function manage(name) {
    await button('Manage RSVP access for ' + name).click();
}
const calls = () => page.evaluate(() => window.__personalRsvpFixture.calls);
async function fail(kind) {
    await page.evaluate((value) => window.__personalRsvpFixture.fail(value), kind);
}
async function finish() {
    await page.getByRole('status').waitFor();
}
async function noOverflow() {
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
}

test('owner states, long and duplicate names, and eligible representatives remain scoped', { skip: !enabled }, async () => {
    await load();
    await open();
    const dialog = page.getByRole('dialog');
    assert.match(await dialog.innerText(), /Independent · Personal link: Active/);
    assert.match(await dialog.innerText(), /Personal link: Not generated/);
    assert.match(await dialog.innerText(), /Represented by Juan Santos/);
    assert.equal(await button('Manage RSVP access for Alex Santos').count(), 2);
    assert.equal(await page.getByText('Foreign wedding person', { exact: true }).count(), 0);
    assert.equal(await page.getByLabel('Personal invitation URL', { exact: true }).count(), 0);
    await manage('Juan Santos');
    assert.match(await dialog.innerText(), /This link answers for Juan Santos and Miguel Santos/);
    assert.ok(await button('Assign a representative').isDisabled());
    await button('Back to group').click();
    await manage('Miguel Santos');
    assert.equal(await button('Copy link').count(), 0);
    assert.equal(await button('Generate link').count(), 0);
    await button('Change or remove representative').click();
    const options = await page.getByLabel('Representative', { exact: true }).locator('option').allTextContents();
    assert.ok(options.includes('Juan Santos'));
    assert.ok(options.includes('Andrea Santos'));
    assert.ok(!options.includes('Maria Santos') && !options.includes('Miguel Santos') && !options.includes('Foreign wedding person'));
    assert.equal((await calls()).length, 0);
});

test('explicit generate/copy uses current versions; no URL survives closing or appears in storage', { skip: !enabled }, async () => {
    await load();
    await open();
    await manage('Maria Santos');
    await button('Generate link').click();
    await finish();
    const input = page.getByLabel('Personal invitation URL', { exact: true });
    assert.equal(await input.inputValue(), origin + '/synthetic-only/no-credential-2');
    assert.deepEqual((await calls())[0], {
        action: 'generate',
        input: { guestId: 2, action: 'generate', expectedVersion: 0, expectedAccessVersion: 3 },
    });
    assert.match(await page.getByRole('status').innerText(), /No invitation has been sent/);
    assert.deepEqual(await page.evaluate(() => ({ local: Object.keys(localStorage), session: Object.keys(sessionStorage) })), {
        local: [],
        session: [],
    });
    await page.keyboard.press('Escape');
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
    await open();
    await manage('Maria Santos');
    assert.equal(await input.count(), 0);
    await page.evaluate(() =>
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText: async () => {
                    throw new Error('synthetic denial');
                },
            },
        }),
    );
    await button('Recover link').click();
    await finish();
    await button('Copy link').click();
    await page.getByRole('alert').filter({ hasText: 'Clipboard access failed.' }).waitFor();
    assert.equal((await calls())[1].input.expectedVersion, 1);
    await button('Back to group').click();
    assert.equal(await input.count(), 0);
});

test('rotation retains dependents; revocation explicitly warns and removes their representation', { skip: !enabled }, async () => {
    await load();
    await open();
    await manage('Juan Santos');
    await button('More link actions').click();
    await page.getByRole('menuitem', { name: 'Regenerate link', exact: true }).click();
    assert.match(await page.getByRole('dialog').innerText(), /Existing dependent assignments stay in place/);
    assert.equal((await calls()).length, 0);
    await button('Regenerate this link').click();
    await finish();
    assert.match(await page.getByRole('dialog').innerText(), /Juan Santos and Miguel Santos/);
    await button('More link actions').click();
    await page.getByRole('menuitem', { name: 'Revoke link', exact: true }).click();
    assert.match(await page.getByRole('dialog').innerText(), /removed for: Miguel Santos/);
    assert.match(await page.getByRole('dialog').innerText(), /Saved attendance responses are preserved/);
    await button('Revoke this link').click();
    await finish();
    assert.match(await page.getByRole('dialog').innerText(), /Personal link: Revoked/);
    assert.equal(await page.getByLabel('Personal invitation URL', { exact: true }).count(), 0);
    await button('Back to group').click();
    await manage('Miguel Santos');
    assert.match(await page.getByRole('dialog').innerText(), /Independent · Personal link: Revoked/);
});

test('designation confirms old link invalidation, carries exact affected versions and never restores it', { skip: !enabled }, async () => {
    await load();
    await open();
    await manage('Andrea Santos');
    await button('Assign a representative').click();
    await page.getByLabel('Representative', { exact: true }).selectOption('1');
    await button('Review change').click();
    assert.match(await page.getByRole('dialog').innerText(), /existing personal link will stop working immediately/);
    assert.match(await page.getByRole('dialog').innerText(), /Saved attendance responses and timestamps are preserved/);
    assert.equal((await calls()).length, 0);
    await button('Confirm representation change').click();
    await finish();
    assert.deepEqual((await calls())[0].input, {
        dependentId: 3,
        representativeId: 1,
        expectedDependentLinkVersion: 1,
        expectedAccessVersions: { 1: 3, 3: 3 },
    });
    assert.equal(await button('Copy link').count(), 0);
    await button('Change or remove representative').click();
    await page.getByLabel('Representative', { exact: true }).selectOption('');
    await button('Review change').click();
    await button('Confirm representation change').click();
    await finish();
    assert.match(await page.getByRole('dialog').innerText(), /Personal link: Revoked/);
    assert.equal((await calls())[1].input.representativeId, null);
});

test('stale owner actions require explicit refresh and delayed replies cannot reopen closed secrets', { skip: !enabled }, async () => {
    await load();
    await open();
    await manage('Juan Santos');
    await fail('stale_access');
    await button('Recover link').click();
    await page.getByRole('alert').waitFor();
    assert.ok(await button('Recover link').isDisabled());
    await button('Refresh access').click();
    await finish();
    assert.ok(await button('Recover link').isEnabled());
    await page.evaluate(() => window.__personalRsvpFixture.delay());
    await button('Recover link').click();
    await page.keyboard.press('Escape');
    assert.ok(await page.getByRole('dialog').isVisible());
    assert.ok(await button('Recover link').isDisabled());
    await page.evaluate(() => {
        window.__personalRsvpFixture.close();
        window.__personalRsvpFixture.release();
    });
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
    await open();
    await manage('Juan Santos');
    assert.equal(await page.getByLabel('Personal invitation URL', { exact: true }).count(), 0);
});

test('owner failures release loading flags without bypassing stale or unavailable access', { skip: !enabled }, async () => {
    await load();
    await open();
    await manage('Juan Santos');
    for (const kind of ['validation', 'unavailable', 'session', 'network', 'rate_limited']) {
        await fail(kind);
        await button('Recover link').click();
        await page.getByRole('alert').waitFor();
        assert.ok(await button('Back to group').isEnabled());
        assert.equal(await page.getByLabel('Personal invitation URL', { exact: true }).count(), 0);
        assert.equal(await page.getByRole('status').count(), 0);
        if (kind === 'rate_limited') assert.ok(await button('Recover link').isEnabled());
        else {
            assert.ok(await button('Recover link').isDisabled());
            await button('Refresh access').click();
            await finish();
            assert.ok(await button('Recover link').isEnabled());
        }
    }
});

test('personal invitation presents only self and delegated targets, reusing the published renderer', { skip: !enabled }, async () => {
    await load('guest');
    assert.match(await page.locator('body').innerText(), /You're invited, Juan Santos!/);
    assert.match(await page.locator('body').innerText(), /Responding on behalf of Miguel Santos/);
    assert.equal(await page.locator('select').count(), 2);
    assert.doesNotMatch(await page.locator('body').innerText(), /Maria Santos|Andrea Santos|access_version|rsvp_version|@example/);
    assert.equal(await page.locator('.invitation-page').count(), 1);
    assert.equal(await page.locator('.invitation-hero-photo').count(), 1);
    await page.evaluate(() => window.__personalRsvpFixture.selfOnly());
    await page.waitForFunction(() => document.querySelectorAll('select').length === 1);
    assert.doesNotMatch(await page.locator('body').innerText(), /Miguel Santos/);
});

test('partial responses and updates carry per-target versions and persist after explicit reload', { skip: !enabled }, async () => {
    await load('guest');
    const choices = page.locator('select');
    await choices.nth(0).selectOption('attending');
    await button('Save responses').click();
    await finish();
    assert.deepEqual((await calls())[0].input, { access_version: 8, responses: [{ guest: 'a'.repeat(64), version: 2, status: 'attending' }] });
    assert.equal(await choices.nth(1).inputValue(), 'pending');
    assert.ok(await choices.nth(0).locator('option[value=pending]').isDisabled());
    await choices.nth(0).selectOption('declined');
    await button('Save responses').click();
    await finish();
    assert.equal((await calls())[1].input.responses[0].version, 3);
    await button('Reload saved choices').click();
    await finish();
    assert.equal(await choices.nth(0).inputValue(), 'declined');
    await button('Save responses').click();
    await page.getByText('No changed responses to save. Unanswered people remain pending.', { exact: true }).waitFor();
    assert.equal((await calls()).length, 3);
    assert.ok(await page.getByRole('status').evaluate((node) => node === document.activeElement));
});

test('guest conflicts preserve choices and require reload; unavailable access scrubs greeting and names', { skip: !enabled }, async () => {
    await load('guest');
    await page.locator('select').first().selectOption('attending');
    for (const kind of ['stale_response', 'stale_access', 'session', 'validation']) {
        await fail(kind);
        await button('Save responses').click();
        await page.getByRole('alert').waitFor();
        assert.ok(await button('Save responses').isDisabled());
        assert.equal(await page.locator('select').first().inputValue(), 'attending');
        assert.ok(await page.getByRole('alert').evaluate((node) => node === document.activeElement));
        await button('Reload saved choices').click();
        await finish();
        await page.locator('select').first().selectOption('attending');
    }
    await fail('rate_limited');
    await button('Save responses').click();
    await page.getByText('Please wait 17 seconds before trying again.', { exact: true }).waitFor();
    await fail('network');
    await button('Save responses').click();
    await page.getByRole('alert').waitFor();
    assert.equal(await page.locator('select').first().inputValue(), 'attending');
    await fail('unavailable');
    await button('Save responses').click();
    await page.getByText('This invitation is unavailable. Ask the couple for a current link.', { exact: true }).waitFor();
    assert.equal(await page.locator('select').count(), 0);
    assert.doesNotMatch(await page.locator('body').innerText(), /Juan Santos|Miguel Santos|Garden Chapel/);
});

test('in-flight guest submit disables controls and prevents duplicate calls', { skip: !enabled }, async () => {
    await load('guest');
    await page.locator('select').first().selectOption('attending');
    await page.evaluate(() => {
        window.__personalRsvpFixture.delay();
        const form = document.querySelector('form');
        form.requestSubmit();
        form.requestSubmit();
    });
    await page.getByRole('form').waitFor();
    assert.ok(await page.locator('select').first().isDisabled());
    assert.ok(await button('Saving…').isDisabled());
    assert.equal((await calls()).length, 1);
    await page.evaluate(() => window.__personalRsvpFixture.release());
    await finish();
});

test('owner dialog passes viewport, theme, keyboard trap/restoration and reduced-motion checks', { skip: !enabled }, async () => {
    await load();
    for (const width of [1440, 1024, 390])
        for (const theme of ['light', 'dark']) {
            await page.setViewportSize({ width, height: 1000 });
            await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
            await open();
            await noOverflow();
            const dialog = page.getByRole('dialog');
            const box = await dialog.boundingBox();
            assert.ok(box.x >= 0 && box.x + box.width <= width && box.y >= 0 && box.y + box.height <= 1000);
            assert.ok(await dialog.evaluate((element) => element.scrollWidth <= element.clientWidth));
            for (let index = 0; index < 24; index++) {
                await page.keyboard.press(index < 12 ? 'Tab' : 'Shift+Tab');
                assert.ok(await dialog.evaluate((element) => element.contains(document.activeElement)));
            }
            assert.equal(await dialog.evaluate((element) => getComputedStyle(element).animationName), 'none');
            assert.equal(
                await dialog.evaluate((element) => getComputedStyle(element).backgroundColor),
                theme === 'dark' ? 'rgb(10, 10, 10)' : 'rgb(255, 255, 255)',
            );
            assert.ok(
                await dialog.locator('button').evaluateAll((buttons) => buttons.every((element) => element.getBoundingClientRect().height >= 44)),
            );
            await page.screenshot({ path: join(artifacts, `owner-list-${width}-${theme}.png`), animations: 'disabled' });
            await manage('VeryLongGuestName'.repeat(16));
            await button('Assign a representative').click();
            await noOverflow();
            assert.ok(
                await page.getByLabel('Representative', { exact: true }).evaluate((element) => element.getBoundingClientRect().right <= innerWidth),
            );
            await page.screenshot({ path: join(artifacts, `owner-representative-${width}-${theme}.png`), animations: 'disabled' });
            await page.keyboard.press('Escape');
            await dialog.waitFor({ state: 'hidden' });
            assert.ok(await button('Actions for Santos Family').evaluate((element) => element === document.activeElement));
        }
});

test('guest long names wrap at all widths and keyboard controls retain the invitation styling', { skip: !enabled }, async () => {
    await load('guest');
    await page.evaluate(() => window.__personalRsvpFixture.longName());
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await noOverflow();
        await page.locator('select').focus();
        await page.keyboard.press('a');
        await page.keyboard.press('Tab');
        assert.ok(await button('Save responses').evaluate((element) => element === document.activeElement));
        await page.locator('.invitation-rsvp').scrollIntoViewIfNeeded();
        await page.screenshot({ path: join(artifacts, `guest-rsvp-${width}.png`), animations: 'disabled' });
        assert.equal(await page.locator('.invitation-greeting script').count(), 0);
        assert.match(await page.locator('.invitation-greeting').innerText(), /<script>private<\/script>/);
        assert.equal(await page.locator('select').evaluate((element) => getComputedStyle(element).fontSize), '16px');
    }
});

test('guest response state is not persisted in browser storage or history', { skip: !enabled }, async () => {
    await load('guest');
    await page.locator('select').first().selectOption('attending');
    await button('Save responses').click();
    await finish();
    assert.deepEqual(
        await page.evaluate(() => ({ local: Object.keys(localStorage), session: Object.keys(sessionStorage), history: history.state })),
        { local: [], session: [], history: null },
    );
});

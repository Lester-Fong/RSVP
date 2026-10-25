import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled =
    process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_RSVP_MODE === 'personal' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;

async function fixture(alternateOrigin, work) {
    const canonical = process.env.QA_BASE_URL;
    assert.match(canonical, /^http:\/\/127\.0\.0\.1:\d+$/);
    const origin = alternateOrigin ? canonical.replace('127.0.0.1', 'localhost') : canonical;
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    const errors = [];
    page.on('pageerror', () => errors.push('Browser exception'));
    page.on('console', (message) => {
        if (/\/rsvp\/[a-f0-9]{64}/i.test(message.text())) errors.push('Credential in console');
    });
    const button = (name) => page.getByRole('button', { name, exact: true });
    const input = page.getByLabel('Personal invitation URL', { exact: true });
    async function access() {
        await button('Actions for Santos Family').click();
        await page.getByRole('menuitem', { name: 'RSVP Access', exact: true }).click();
        await page.getByRole('dialog').waitFor();
    }
    const manage = async (name) => button('Manage RSVP access for ' + name).click();
    async function settled() {
        await page.waitForFunction(() =>
            [...document.querySelectorAll('[role="dialog"] button')].some(
                (button) => button.textContent.trim() === 'Back to group' && !button.disabled,
            ),
        );
    }
    try {
        await page.goto(origin + '/register');
        await page.getByLabel('Name', { exact: true }).fill('RSVP access regression');
        await page.getByLabel('Email address').fill(randomUUID() + '@example.test');
        const password = randomUUID() + 'Aa1!';
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password').fill(password);
        await button('Create account').click();
        await page.waitForURL('**/dashboard');
        await page.goto(origin + '/weddings/create');
        await page.getByLabel('Partner 1 name').fill('Synthetic Alex');
        await page.getByLabel('Partner 2 name').fill('Synthetic Jamie');
        await page.getByLabel(/Wedding date/).fill('2027-05-12');
        await button('Create wedding').click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        await page.getByRole('link', { name: 'Guests', exact: true }).click();
        await page.waitForURL('**/guests');
        await button('New invitation group').click();
        await page.getByLabel('Group label', { exact: true }).fill('Santos Family');
        for (const name of ['Juan', 'Maria', 'Andrea', 'Miguel']) {
            await page.getByLabel('Guest name', { exact: true }).fill(name);
            await page.getByLabel('Guest name', { exact: true }).press('Enter');
        }
        await button('Create group').click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        await page.reload();
        const household = await page.evaluate(() => JSON.parse(document.getElementById('app').getAttribute('data-page')).props.groups.data[0]);
        const base = new URL(page.url()).pathname.replace(/\/guests$/, '') + '/invitation-groups/' + household.id + '/rsvp-access';
        const api = async (path, body) => {
            const cookie = (await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN');
            return context.request.post(origin + path, {
                data: body,
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) },
            });
        };
        await access();
        await work({ page, context, origin, canonical, button, input, access, manage, settled, base, api, household });
        assert.deepEqual(errors, []);
    } catch (error) {
        // Assertions and Playwright call logs may otherwise print a live disposable credential.
        throw new Error(
            String(error.message)
                .replace(/(?:https?:\/\/[^\s"'<>]*)?\/rsvp\/[a-f0-9]{64}/gi, '[private link]')
                .replace(/[a-f0-9]{64}/gi, '[redacted]'),
        );
    } finally {
        await browser.close();
    }
}

test(
    'real HTTP generation keeps other guests available at an alternate portal origin, with current independent versions',
    { skip: !enabled },
    async () => {
        await fixture(true, async ({ page, context, origin, canonical, button, input, access, manage, settled, base, api, household }) => {
            let snapshot;
            const urls = [];
            for (const name of ['Juan', 'Maria', 'Andrea']) {
                await manage(name);
                const responsePromise = page.waitForResponse((response) => response.url().endsWith('/credential/generate'));
                await button('Generate link').click();
                const response = await responsePromise;
                assert.equal(response.status(), 200);
                assert.match(response.headers()['cache-control'], /no-store/);
                snapshot = (await response.json()).group;
                await settled();
                assert.equal(await page.getByRole('alert').count(), 0, 'A trusted canonical URL must not block the group');
                assert.equal(await input.count(), 1);
                const url = await input.inputValue();
                assert.equal(new URL(url).origin, canonical);
                urls.push(url);
                assert.equal(snapshot.members.find((person) => person.guest.name === name).link_version, 1);
                await button('Back to group').click();
                assert.equal(await input.count(), 0);
                if (name === 'Juan') {
                    await manage('Miguel');
                    await button('Assign a representative').click();
                    const juan = snapshot.members.find((person) => person.guest.name === 'Juan');
                    await page.getByLabel('Representative', { exact: true }).selectOption(String(juan.guest.id));
                    await button('Review change').click();
                    await button('Confirm representation change').click();
                    await settled();
                    assert.match(await page.getByRole('dialog').innerText(), /Represented by Juan/);
                    await button('Back to group').click();
                }
            }
            assert.equal(new Set(urls).size, 3);
            await manage('Juan');
            assert.match(await page.getByRole('dialog').innerText(), /Juan and Miguel/);
            await button('More link actions').click();
            await page.getByRole('menuitem', { name: 'Regenerate link', exact: true }).click();
            await button('Regenerate this link').click();
            await settled();
            assert.match(await page.getByRole('dialog').innerText(), /Juan and Miguel/);
            assert.equal(await input.count(), 1);
            await button('Back to group').click();
            await manage('Maria');
            assert.match(await page.getByRole('dialog').innerText(), /This link answers for Maria\./);

            // A genuine competing owner mutation still blocks a stale write; no automatic retry.
            const current = (await (await context.request.get(origin + base, { headers: { Accept: 'application/json' } })).json()).group;
            const maria = current.members.find((person) => person.guest.name === 'Maria');
            assert.equal(
                (
                    await api(base + '/guests/' + maria.guest.id + '/credential/regenerate', {
                        expectedVersion: maria.link_version,
                        expectedAccessVersion: maria.access_version,
                    })
                ).status(),
                200,
            );
            await button('Recover link').click();
            await settled();
            assert.match(await page.getByRole('alert').innerText(), /another window/);
            assert.ok(await button('Recover link').isDisabled());
            await button('Refresh access').click();
            await settled();
            assert.ok(await button('Recover link').isEnabled());
            assert.match(await page.getByRole('dialog').innerText(), /This link answers for Maria\./);
            await button('Recover link').click();
            await settled();
            assert.equal(await input.count(), 1);
            for (const [status, kind] of [
                [404, 'unavailable'],
                [409, 'stale_access'],
                [422, 'validation'],
            ]) {
                await button('Back to group').click();
                await manage('Maria');
                await page.route('**/credential/copy', (route) =>
                    route.fulfill({ status, contentType: 'application/json', body: JSON.stringify({ kind }) }),
                );
                await button('Recover link').click();
                await settled();
                assert.equal(await page.getByRole('alert').count(), 1);
                assert.equal(await page.getByRole('status').count(), 0);
                assert.equal(await input.count(), 0);
                assert.ok(await button('Recover link').isDisabled());
                assert.ok(await button('Back to group').isEnabled());
                await page.unroute('**/credential/copy');
                await button('Refresh access').click();
                await settled();
                assert.ok(await button('Recover link').isEnabled());
            }
            await page.keyboard.press('Escape');
            await page.getByRole('dialog').waitFor({ state: 'hidden' });
            await access();
            await manage('Maria');
            assert.equal(await input.count(), 0);
            await button('Back to group').click();
            await manage('Juan');
            await button('More link actions').click();
            await page.getByRole('menuitem', { name: 'Revoke link', exact: true }).click();
            await button('Revoke this link').click();
            await settled();
            assert.match(await page.getByRole('dialog').innerText(), /Personal link: Revoked/);
            await button('Back to group').click();
            await manage('Miguel');
            assert.match(await page.getByRole('dialog').innerText(), /Independent/);
            await button('Back to group').click();
            await manage('Maria');
            assert.ok(await button('Recover link').isEnabled());
            const props = await page.evaluate(() => document.getElementById('app').getAttribute('data-page'));
            assert.doesNotMatch(props, /\/rsvp\/[a-f0-9]{64}|token_hash|"token"/i);
            assert.equal(household.guests.length, 4);
        });
    },
);

test(
    'real HTTP recovery precedes a direct clipboard click, with truthful feedback, selectable fallback and secret cleanup',
    { skip: !enabled },
    async () => {
        await fixture(false, async ({ page, context, origin, button, input, access, manage, settled }) => {
            await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin });
            await manage('Juan');
            await button('Generate link').click();
            await settled();
            const recovered = await input.inputValue();
            await page.evaluate(() => {
                const write = navigator.clipboard.writeText.bind(navigator.clipboard);
                window.copyProbe = { calls: 0, direct: false, clicked: false };
                document.addEventListener(
                    'click',
                    () => {
                        window.copyProbe.clicked = true;
                    },
                    true,
                );
                document.addEventListener('click', () => {
                    window.copyProbe.clicked = false;
                });
                navigator.clipboard.writeText = (text) => {
                    window.copyProbe.calls++;
                    window.copyProbe.direct = window.copyProbe.clicked;
                    return write(text);
                };
            });
            let requests = 0;
            page.on('request', (request) => {
                if (request.url().includes('/rsvp-access')) requests++;
            });
            await button('Copy link').click();
            await settled();
            assert.deepEqual(await page.evaluate(() => ({ calls: window.copyProbe.calls, direct: window.copyProbe.direct })), {
                calls: 1,
                direct: true,
            });
            assert.equal(requests, 0, 'Copying a revealed URL must not wait for HTTP');
            assert.ok(await page.evaluate((expected) => navigator.clipboard.readText().then((value) => value === expected), recovered));
            assert.match(await page.getByRole('status').innerText(), /Link copied\. No invitation has been sent/);
            await page.evaluate(() => navigator.clipboard.writeText(''));

            await page.evaluate(() => {
                navigator.clipboard.writeText = () =>
                    new Promise((resolve, reject) => {
                        window.denyCopy = () => reject(new DOMException('Denied', 'NotAllowedError'));
                    });
            });
            await button('Copy link').click();
            assert.equal(await page.getByRole('status').count(), 0, 'Do not announce success before the write resolves');
            assert.ok(await button('Copy link').isDisabled());
            await page.evaluate(() => window.denyCopy());
            await settled();
            assert.match(await page.getByRole('alert').innerText(), /Clipboard access failed/);
            assert.ok(
                await input.evaluate(
                    (element) => element === document.activeElement && element.selectionEnd - element.selectionStart === element.value.length,
                ),
            );
            assert.ok((await input.inputValue()) === recovered);
            assert.ok(await button('Copy link').isEnabled());
            assert.equal(await button('Refresh access').count(), 0);
            await button('Back to group').click();
            await manage('Maria');
            assert.equal(await input.count(), 0);
            await page.keyboard.press('Escape');
            await page.getByRole('dialog').waitFor({ state: 'hidden' });
            await access();
            await manage('Juan');
            await button('Recover link').click();
            await settled();
            assert.ok((await input.inputValue()) === recovered);
            assert.doesNotMatch(await page.getByRole('status').innerText(), /Link copied/);

            const artifacts = await mkdtemp(join(tmpdir(), 'rsvp-access-regression-'));
            // Blur the URL for screenshots; do not write a credential into visual artifacts.
            for (const width of [1440, 1024, 390])
                for (const theme of ['light', 'dark']) {
                    await page.setViewportSize({ width, height: 1000 });
                    await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                    for (let index = 0; index < 16; index++) {
                        await page.keyboard.press(index < 8 ? 'Tab' : 'Shift+Tab');
                        assert.ok(await page.getByRole('dialog').evaluate((element) => element.contains(document.activeElement)));
                    }
                    await page.screenshot({
                        path: join(artifacts, `access-${width}-${theme}.png`),
                        mask: [input],
                        maskColor: '#737373',
                        animations: 'disabled',
                    });
                }
            console.log('RSVP access masked screenshots: ' + artifacts);
            await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
            assert.equal(await input.count(), 0);
            let release;
            let pending;
            const started = new Promise((resolve) => {
                pending = resolve;
            });
            const delayed = new Promise((resolve) => {
                release = resolve;
            });
            await page.route('**/credential/copy', async (route) => {
                const response = await route.fetch();
                pending();
                await delayed;
                await route.fulfill({ response });
            });
            await button('Recover link').click();
            await started;
            await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
            release();
            await settled();
            assert.equal(await input.count(), 0, 'A late HTTP recovery must not reveal a cleared URL');
            await page.unroute('**/credential/copy');
            await button('Recover link').click();
            await settled();
            assert.equal(await input.count(), 1);
            await page.keyboard.press('Escape');
            await page.getByRole('dialog').waitFor({ state: 'hidden' });
            assert.ok(await button('Actions for Santos Family').evaluate((element) => element === document.activeElement));
            await access();
            await manage('Juan');
            assert.equal(await input.count(), 0);
            await button('Recover link').click();
            await settled();
            await page.evaluate(() => document.querySelector('a[href$="/attendance"]').click());
            await page.waitForURL('**/attendance');
            await page.getByRole('link', { name: 'Guests', exact: true }).click();
            await page.waitForURL('**/guests');
            await access();
            await manage('Juan');
            assert.equal(await input.count(), 0, 'Inertia navigation must discard recovered credentials');
            const persisted = await page.evaluate(() =>
                JSON.stringify({ local: { ...localStorage }, session: { ...sessionStorage }, history: history.state }),
            );
            assert.doesNotMatch(persisted, /\/rsvp\/[a-f0-9]{64}/i);
        });
    },
);

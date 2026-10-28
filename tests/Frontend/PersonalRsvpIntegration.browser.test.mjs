import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled =
    process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_RSVP_MODE === 'personal' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;
test('connected personal owner and guest HTTP flows enforce scope, CSRF, versions, revocation and privacy', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const owner = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const visitors = await browser.newContext({ viewport: { width: 390, height: 1000 }, reducedMotion: 'reduce' });
    const page = await owner.newPage();
    const guest = await visitors.newPage();
    const errors = [];
    for (const tab of [page, guest]) {
        tab.setDefaultTimeout(15000);
        tab.on('pageerror', (error) => errors.push(error.message));
    }
    const artifacts = await mkdtemp(join(tmpdir(), 'rsvp-5c3a-connected-'));
    console.log('Connected personal-mode screenshots: ' + artifacts);
    const button = (name) => page.getByRole('button', { name, exact: true });
    async function enterInvitation(tab) {
        await tab.locator('.ee-booth-skip, [data-ee-phase="landed"]').first().waitFor();
        const skip = tab.getByRole('button', { name: 'Skip animation', exact: false });
        if (await skip.isVisible()) await skip.click();
        await tab.locator('[data-ee-phase="landed"]').waitFor();
    }
    async function access() {
        await button('Actions for Santos Family').click();
        await page.getByRole('menuitem', { name: 'RSVP Access', exact: true }).click();
        await page.getByRole('dialog').waitFor();
    }
    async function manage(name) {
        await button('Manage RSVP access for ' + name).click();
    }
    async function close() {
        await page.keyboard.press('Escape');
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    async function finished() {
        await page.getByRole('dialog').getByRole('status').waitFor();
    }
    async function generate(name) {
        await access();
        await manage(name);
        await button('Generate link').click();
        const input = page.getByLabel('Personal invitation URL', { exact: true });
        await input.waitFor();
        const url = await input.inputValue();
        await close();
        return url;
    }
    async function api(path, body) {
        const cookie = (await owner.cookies()).find((value) => value.name === 'XSRF-TOKEN');
        return owner.request.post(origin + path, {
            data: body,
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie?.value ?? '') },
        });
    }
    try {
        await page.goto(origin + '/register');
        await page.getByLabel('Name', { exact: true }).fill('Personal HTTP QA');
        await page.getByLabel('Email address').fill('personal-' + randomUUID() + '@example.test');
        const password = randomUUID() + 'Aa1!';
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password', { exact: true }).fill(password);
        await button('Create account').click();
        await page.waitForURL('**/onboarding');
        await page.goto(origin + '/weddings/create');
        await page.getByLabel('Partner 1 name').fill('Published Alex');
        await page.getByLabel('Partner 2 name').fill('Published Jamie');
        await page.getByLabel(/Wedding date/).fill('2027-05-12');
        await button('Create wedding').click();
        await page.waitForURL('**/onboarding/congratulations');
        await page.getByRole('link', { name: 'Enter your wedding dashboard', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const overview = page.url();
        await page.getByRole('link', { name: 'Design invitation', exact: true }).click();
        await button('Save Draft').click();
        await page.getByText(/Draft saved privately\. Your published website has not changed\./).waitFor();
        await page.goto(overview);
        await button('Publish invitation').click();
        await button('Publish now').click();
        await page.getByRole('heading', { name: 'Published', exact: true }).waitFor();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        await page.getByRole('link', { name: 'Guests & RSVPs', exact: true }).click();
        await page.waitForURL('**/guests');
        await button('New invitation group').click();
        await page.getByLabel('Group label', { exact: true }).fill('Santos Family');
        for (const name of ['Juan', 'Maria', 'Miguel', 'Andrea']) {
            await page.getByLabel('Guest name', { exact: true }).fill(name);
            await page.getByLabel('Guest name', { exact: true }).press('Enter');
        }
        await button('Create group').click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        await button('Actions for Santos Family').click();
        assert.equal(await page.getByRole('menuitem', { name: 'Invitation link', exact: true }).count(), 0);
        await page.keyboard.press('Escape');
        const juan = await generate('Juan');
        const maria = await generate('Maria');
        const anonymous = await visitors.request.get(juan);
        assert.equal(anonymous.status(), 200);
        assert.match(anonymous.headers()['cache-control'], /no-store/);
        assert.equal(anonymous.headers()['referrer-policy'], 'no-referrer');
        assert.equal(anonymous.headers()['x-robots-tag'], 'noindex, nofollow, noarchive');
        assert.equal(anonymous.headers()['set-cookie'], undefined);
        assert.deepEqual(await visitors.cookies(), []);
        const html = await (await visitors.request.get(juan)).text();
        assert.doesNotMatch(html, /Maria|Miguel|Andrea/);
        assert.ok(!html.includes(new URL(juan).pathname.split('/').pop()));
        await access();
        await manage('Juan');
        assert.equal(await page.getByLabel('Personal invitation URL', { exact: true }).count(), 0);
        await button('Recover link').click();
        await finished();
        assert.equal(await page.getByLabel('Personal invitation URL', { exact: true }).inputValue(), juan);
        await close();
        await access();
        await manage('Miguel');
        await button('Assign a representative').click();
        const options = await page
            .getByLabel('Representative', { exact: true })
            .locator('option')
            .evaluateAll((elements) => elements.map((element) => ({ text: element.textContent, value: element.value })));
        const juanOption = options.find((option) => option.text === 'Juan');
        await page.getByLabel('Representative', { exact: true }).selectOption(juanOption.value);
        await button('Review change').click();
        await button('Confirm representation change').click();
        await finished();
        await close();
        await guest.goto(juan);
        await enterInvitation(guest);
        assert.equal(await guest.locator('select').count(), 2);
        assert.doesNotMatch(await guest.locator('body').innerText(), /Maria|Andrea/);
        assert.match(await guest.locator('body').innerText(), /Responding on behalf of Miguel/);
        const initial = await (await visitors.request.get(juan, { headers: { Accept: 'application/json' } })).json();
        const noCsrf = await visitors.request.post(juan + '/responses', {
            data: {
                access_version: initial.rsvp.access_version,
                responses: [{ guest: initial.rsvp.guests[0].reference, version: initial.rsvp.guests[0].version, status: 'attending' }],
            },
        });
        assert.equal(noCsrf.status(), 419);
        let submissions = 0;
        guest.on('request', (request) => {
            if (request.method() === 'POST' && new URL(request.url()).pathname.endsWith('/responses')) submissions++;
        });
        await guest.locator('select').first().selectOption('attending');
        const submitted = guest.waitForResponse(
            (response) => response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/responses'),
        );
        await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
        assert.equal((await submitted).status(), 200);
        await guest.getByRole('heading', { name: 'Your RSVP', exact: true }).locator('..').locator('time').waitFor();
        assert.equal(submissions, 1);
        assert.equal(await guest.locator('select').nth(1).inputValue(), 'pending');
        assert.equal(await guest.getByRole('heading', { name: 'Your RSVP', exact: true }).locator('..').locator('time').count(), 1);
        await guest.reload();
        await enterInvitation(guest);
        assert.equal(await guest.locator('select').first().inputValue(), 'attending');
        const stale = await visitors.newPage();
        await stale.goto(juan);
        await enterInvitation(stale);
        await guest.locator('select').first().selectOption('declined');
        await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
        await guest.getByRole('status').waitFor();
        await stale.locator('select').nth(1).selectOption('attending');
        await stale.getByRole('button', { name: 'Save responses', exact: true }).click();
        await stale.getByRole('status').waitFor();
        await guest.getByRole('button', { name: 'Reload saved choices', exact: true }).click();
        await guest.getByText('Saved choices and current access reloaded.', { exact: true }).waitFor();
        // A round trip to the previous choice still advances its version: an old changed write must conflict.
        await guest.locator('select').first().selectOption('attending');
        await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
        await guest.getByRole('status').waitFor();
        await guest.locator('select').first().selectOption('declined');
        await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
        await guest.getByRole('status').waitFor();
        await stale.locator('select').first().selectOption('attending');
        await stale.locator('select').nth(1).selectOption('declined');
        await stale.getByRole('button', { name: 'Save responses', exact: true }).click();
        await stale
            .getByText('A response or name changed in another window. Reload and review the saved choices before trying again.', { exact: true })
            .waitFor();
        assert.ok(await stale.getByRole('button', { name: 'Save responses', exact: true }).isDisabled());
        await stale.getByRole('button', { name: 'Reload saved choices', exact: true }).click();
        await stale.getByText('Saved choices and current access reloaded.', { exact: true }).waitFor();
        assert.equal(await stale.locator('select').nth(1).inputValue(), 'attending');
        await stale.close();
        const mariaPage = await visitors.newPage();
        await mariaPage.goto(maria);
        await enterInvitation(mariaPage);
        assert.equal(await mariaPage.locator('select').count(), 1);
        assert.doesNotMatch(await mariaPage.locator('body').innerText(), /Juan|Miguel|Andrea/);
        await mariaPage.close();
        for (const width of [1440, 1024, 390]) {
            await guest.setViewportSize({ width, height: 1000 });
            assert.ok(await guest.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await guest.getByRole('heading', { name: 'Your RSVP', exact: true }).scrollIntoViewIfNeeded();
            await guest.screenshot({ path: join(artifacts, 'guest-' + width + '.png'), animations: 'disabled' });
        }
        for (const width of [1440, 1024, 390])
            for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                await access();
                await manage('Juan');
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                const description = await page
                    .getByRole('dialog')
                    .locator('p')
                    .filter({ hasText: 'Choose who responds for each person.' })
                    .boundingBox();
                const closeButton = await button('Close').boundingBox();
                assert.ok(description.x + description.width <= closeButton.x, 'Dialog explanation must leave room for its close control');
                for (let index = 0; index < 12; index++) {
                    await page.keyboard.press('Tab');
                    assert.ok(await page.getByRole('dialog').evaluate((element) => element.contains(document.activeElement)));
                }
                await page.screenshot({ path: join(artifacts, 'owner-' + width + '-' + theme + '.png'), animations: 'disabled' });
                await close();
            }
        await access();
        await manage('Juan');
        await button('More link actions').click();
        await page.getByRole('menuitem', { name: 'Regenerate link', exact: true }).click();
        await button('Regenerate this link').click();
        await finished();
        const rotated = await page.getByLabel('Personal invitation URL', { exact: true }).inputValue();
        assert.notEqual(rotated, juan);
        assert.equal((await visitors.request.get(juan)).status(), 404);
        assert.match(await (await visitors.request.get(rotated)).text(), /Miguel/);
        await close();
        await guest.goto(rotated);
        await enterInvitation(guest);
        await access();
        await manage('Miguel');
        await button('Change or remove representative').click();
        await page.getByLabel('Representative', { exact: true }).selectOption('');
        await button('Review change').click();
        await button('Confirm representation change').click();
        await finished();
        await close();
        await guest.locator('select').nth(1).selectOption('declined');
        await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
        await guest
            .getByText('Who this link can answer for has changed. Reload the invitation to review your current access.', { exact: true })
            .waitFor();
        await guest.getByRole('button', { name: 'Reload saved choices', exact: true }).click();
        await guest.getByText('Saved choices and current access reloaded.', { exact: true }).waitFor();
        assert.equal(await guest.locator('select').count(), 1);
        assert.doesNotMatch(await guest.locator('body').innerText(), /Miguel/);
        await page.reload();
        const household = await page.evaluate(() => JSON.parse(document.getElementById('app').getAttribute('data-page')).props.groups.data[0]);
        const juanId = household.guests.find((person) => person.name === 'Juan').id;
        assert.equal(
            (
                await owner.request.delete(origin + new URL(overview).pathname + '/invitation-groups/' + household.id + '/guests/' + juanId, {
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent((await owner.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN').value),
                    },
                })
            ).status(),
            422,
        );
        const legacyDenied = await api(new URL(overview).pathname + '/invitation-groups/' + household.id + '/invitation-link/generate', {
            link_version: 0,
        });
        assert.equal(legacyDenied.status(), 404);
        await access();
        await manage('Juan');
        await button('More link actions').click();
        await page.getByRole('menuitem', { name: 'Revoke link', exact: true }).click();
        await button('Revoke this link').click();
        await finished();
        await close();
        await guest.locator('select').first().selectOption('attending');
        await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
        await guest.getByText('This invitation is unavailable. Ask the couple for a current link.', { exact: true }).waitFor();
        assert.doesNotMatch(await guest.locator('body').innerText(), /Juan|Miguel|Published Alex/);
        const mariaState = (await (await visitors.request.get(maria, { headers: { Accept: 'application/json' } })).json()).rsvp;
        const bootstrap = await (await visitors.request.get(origin + '/invitation-rsvp/csrf-token')).json();
        const requestOptions = {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
            data: {
                access_version: mariaState.access_version,
                responses: [{ guest: mariaState.guests[0].reference, version: mariaState.guests[0].version, status: 'attending' }],
            },
        };
        for (let index = 0; index < 20; index++) assert.equal((await visitors.request.post(maria + '/responses', requestOptions)).status(), 200);
        const throttled = await visitors.request.post(maria + '/responses', requestOptions);
        assert.equal(throttled.status(), 429);
        assert.ok(Number(throttled.headers()['retry-after']) > 0);
        const andrea = await generate('Andrea');
        const andreaState = (await (await visitors.request.get(andrea, { headers: { Accept: 'application/json' } })).json()).rsvp;
        assert.equal(
            (
                await visitors.request.post(andrea + '/responses', {
                    ...requestOptions,
                    data: {
                        access_version: andreaState.access_version,
                        responses: [{ guest: andreaState.guests[0].reference, version: andreaState.guests[0].version, status: 'declined' }],
                    },
                })
            ).status(),
            200,
        );
        await page.reload();
        await page.getByRole('heading', { name: 'Guests & RSVPs', exact: true }).waitFor();
        assert.match(await page.locator('body').innerText(), /Declined/);
        for (const [key, count] of Object.entries({ invited: 4, attending: 2, declined: 2, pending: 0 })) {
            assert.equal(await page.getByTestId('attendance-' + key).innerText(), String(count));
        }
        assert.deepEqual(errors, []);
    } catch (error) {
        throw new Error(String(error.stack ?? error.message).replace(/[a-f0-9]{64,}/gi, '[redacted]'));
    } finally {
        await browser.close();
    }
});

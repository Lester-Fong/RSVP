import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;
test('guests save partial RSVPs, reload, update, resolve stale windows and retain responded people', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const owner = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const visitors = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const page = await owner.newPage();
    const guest = await visitors.newPage();
    const errors = [];
    let setupRequests = 0;
    let submissionRequests = 0;
    guest.on('request', (request) => {
        if (new URL(request.url()).pathname === '/invitation-rsvp/csrf-token') setupRequests++;
        if (request.method() === 'POST' && new URL(request.url()).pathname.endsWith('/rsvp')) submissionRequests++;
    });
    for (const tab of [page, guest]) {
        tab.setDefaultTimeout(15000);
        tab.on('pageerror', (error) => errors.push(error.message));
    }
    const members = ['Juan Santos', 'Maria Santos', 'Andrea Santos', 'Miguel Santos'];
    async function finish(label) {
        await page.getByRole('button', { name: label, exact: true }).click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    async function linkDialog(label = 'Santos Family') {
        await page.getByRole('button', { name: 'Actions for ' + label, exact: true }).click();
        await page.getByRole('menuitem', { name: 'Invitation link', exact: true }).click();
    }
    try {
        await page.goto(origin + '/register');
        await page.getByLabel('Name', { exact: true }).fill('RSVP QA');
        await page.getByLabel('Email address').fill('rsvp-' + randomUUID() + '@example.test');
        const password = randomUUID() + 'Aa1!';
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Create account', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await page.goto(origin + '/weddings/create');
        await page.getByLabel('Partner 1 name').fill('RSVP Published Alex');
        await page.getByLabel('Partner 2 name').fill('RSVP Published Jamie');
        await page.getByLabel(/Wedding date/).fill('2027-05-12');
        await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const overview = page.url();
        await page.getByRole('link', { name: 'Customize invitation', exact: true }).click();
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('Draft saved', { exact: true }).waitFor();
        await page.goto(overview);
        await page.getByRole('button', { name: 'Publish invitation', exact: true }).click();
        await finish('Publish now');
        await page.getByRole('link', { name: 'Guest Management', exact: true }).click();
        await page.getByRole('button', { name: 'New invitation group', exact: true }).click();
        await page.getByLabel('Group label', { exact: true }).fill('Santos Family');
        await page.getByLabel('Contact email (optional)').fill('private-rsvp@example.test');
        for (let index = 0; index < members.length; index++) {
            if (index > 0) await page.getByRole('button', { name: 'Add another guest', exact: true }).click();
            await page.getByLabel(`Guest ${index + 1} name`, { exact: true }).fill(members[index]);
        }
        await finish('Create group');
        await linkDialog();
        await page.getByRole('button', { name: 'Generate link', exact: true }).click();
        const url = await page.getByLabel('Invitation URL', { exact: true }).inputValue();
        await finish('Done');
        const response = await guest.goto(url);
        assert.equal(response.status(), 200);
        assert.match(response.headers()['cache-control'], /no-store/);
        assert.equal(response.headers()['referrer-policy'], 'no-referrer');
        assert.ok(!(await response.text()).includes('private-rsvp@example.test'));
        const form = guest.locator('#invitation-rsvp');
        await form.getByRole('button', { name: 'Save responses', exact: true }).waitFor();
        assert.equal(await form.getByText('Saved: Pending — not answered', { exact: true }).count(), 4);
        await form.getByLabel('Juan Santos', { exact: true }).selectOption('attending');
        await form.getByLabel('Andrea Santos', { exact: true }).selectOption('declined');
        await guest.route('**/invitation-rsvp/csrf-token', async (route) => {
            await route.fulfill({
                status: 429,
                contentType: 'application/json',
                headers: { 'Retry-After': '17' },
                body: JSON.stringify({
                    message: 'Too many requests. Try again in 17 seconds. Your saved responses have not changed.',
                    retryAfter: 17,
                }),
            });
        });
        await form.getByRole('button', { name: 'Save responses', exact: true }).click();
        await form.getByRole('alert').waitFor();
        assert.match(await form.getByRole('alert').innerText(), /17 seconds/);
        assert.equal(submissionRequests, 0);
        assert.equal(await form.getByLabel('Juan Santos', { exact: true }).inputValue(), 'attending');
        await guest.unroute('**/invitation-rsvp/csrf-token');
        const firstSave = guest.waitForResponse((res) => res.request().method() === 'POST' && new URL(res.url()).pathname.endsWith('/rsvp'));
        await form.locator('form').evaluate((element) => {
            element.requestSubmit();
            element.requestSubmit();
        });
        await form.getByRole('status').waitFor();
        const firstSaved = await firstSave;
        assert.equal(firstSaved.status(), 200);
        assert.equal(firstSaved.headers()['x-ratelimit-remaining'], '19');
        assert.equal(setupRequests, 2);
        assert.equal(submissionRequests, 1);
        await guest.reload();
        await form.getByRole('button', { name: 'Save responses', exact: true }).waitFor();
        assert.equal(await form.getByLabel('Juan Santos', { exact: true }).inputValue(), 'attending');
        assert.equal(await form.getByLabel('Andrea Santos', { exact: true }).inputValue(), 'declined');
        assert.equal(await form.getByLabel('Maria Santos', { exact: true }).inputValue(), 'pending');
        const stale = await visitors.newPage();
        await stale.goto(url);
        await stale.locator('#invitation-rsvp').getByRole('button', { name: 'Save responses', exact: true }).waitFor();
        await form.getByLabel('Juan Santos', { exact: true }).selectOption('declined');
        await form.getByRole('button', { name: 'Save responses', exact: true }).click();
        await form.getByRole('status').waitFor();
        await stale.locator('#invitation-rsvp').getByLabel('Maria Santos', { exact: true }).selectOption('attending');
        await stale.locator('#invitation-rsvp').getByRole('button', { name: 'Save responses', exact: true }).click();
        await stale.getByRole('alert').waitFor();
        assert.match(await stale.getByRole('alert').innerText(), /changed in another window/);
        assert.equal(await stale.getByLabel('Maria Santos', { exact: true }).inputValue(), 'attending');
        await stale.getByRole('button', { name: 'Reload invitation', exact: true }).click();
        await stale.locator('#invitation-rsvp').getByRole('button', { name: 'Save responses', exact: true }).waitFor();
        assert.equal(await stale.getByLabel('Maria Santos', { exact: true }).inputValue(), 'pending');

        const state = await guest.locator('#rsvp-data').textContent().then(JSON.parse);
        const input = { version: state.initial.version, responses: [{ guest: state.initial.guests[1].reference, status: 'attending' }] };
        assert.equal((await visitors.request.post(url + '/rsvp', { data: input })).status(), 419);
        const csrf = await visitors.request.get(origin + '/invitation-rsvp/csrf-token').then((res) => res.json());
        const headers = { 'X-CSRF-TOKEN': csrf.csrfToken };
        // Two requests from the same rendered version: one commits and the other must be rejected.
        const current = await stale.locator('#rsvp-data').textContent().then(JSON.parse);
        const simultaneous = await Promise.all(
            [1, 3].map((index) =>
                visitors.request.post(url + '/rsvp', {
                    headers,
                    data: { version: current.initial.version, responses: [{ guest: current.initial.guests[index].reference, status: 'attending' }] },
                }),
            ),
        );
        assert.deepEqual(simultaneous.map((res) => res.status()).sort(), [200, 422]);

        for (const width of [1440, 1024, 390]) {
            await guest.bringToFront();
            await guest.setViewportSize({ width, height: 1000 });
            await guest.reload();
            await form.getByRole('button', { name: 'Save responses', exact: true }).waitFor();
            await form.scrollIntoViewIfNeeded();
            assert.equal(await guest.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            await form.getByLabel('Juan Santos', { exact: true }).focus();
            assert.ok(await form.getByLabel('Juan Santos', { exact: true }).evaluate((el) => getComputedStyle(el).outlineStyle !== 'none'));
            await guest.screenshot({ path: join(tmpdir(), `rsvp-5a-public-${width}.png`), animations: 'disabled' });
        }
        await page.bringToFront();
        await page.reload();
        for (const theme of ['light', 'dark']) {
            await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
            await page.setViewportSize({ width: 390, height: 1000 });
            await page.getByRole('button', { name: 'Edit guest Juan Santos', exact: true }).click();
            assert.equal(await page.getByRole('button', { name: 'Remove guest', exact: true }).isDisabled(), true);
            await page
                .getByText('This guest has an RSVP response and cannot be removed. Renaming preserves their response.', { exact: true })
                .waitFor();
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            for (let step = 0; step < 7; step++) {
                await page.keyboard.press('Tab');
                assert.ok(await page.getByRole('dialog').evaluate((el) => el.contains(document.activeElement)));
            }
            await page.screenshot({ path: join(tmpdir(), `rsvp-5a-retention-${theme}.png`), animations: 'disabled' });
            await page.keyboard.press('Escape');
            await page.getByRole('dialog').waitFor({ state: 'hidden' });
        }
        await page.getByRole('button', { name: 'Edit guest Juan Santos', exact: true }).click();
        await page.getByLabel('Guest name', { exact: true }).fill('J'.repeat(255));
        await finish('Save changes');
        await guest.reload();
        await form.getByLabel('J'.repeat(255), { exact: true }).waitFor();
        assert.equal(await form.getByLabel('J'.repeat(255), { exact: true }).inputValue(), 'declined');
        assert.equal(await guest.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);

        await page.getByRole('button', { name: 'New invitation group', exact: true }).click();
        await page.getByLabel('Group label', { exact: true }).fill('Independent Family');
        await page.getByLabel('Guest 1 name', { exact: true }).fill('Independent Guest');
        await finish('Create group');
        await linkDialog('Independent Family');
        await page.getByRole('button', { name: 'Generate link', exact: true }).click();
        const otherUrl = await page.getByLabel('Invitation URL', { exact: true }).inputValue();
        await finish('Done');
        const otherHtml = await visitors.request.get(otherUrl).then((res) => res.text());
        const otherState = JSON.parse(otherHtml.match(/<script id="rsvp-data" type="application\/json">(.*?)<\/script>/s)[1]).initial;
        const otherInput = { version: otherState.version, responses: [{ guest: otherState.guests[0].reference, status: 'attending' }] };
        for (let attempt = 0; attempt < 20; attempt++) {
            const savedResponse = await visitors.request.post(otherUrl + '/rsvp', { headers, data: otherInput });
            assert.equal(savedResponse.status(), 200);
            assert.equal(Number(savedResponse.headers()['x-ratelimit-remaining']), 19 - attempt);
        }
        const limited = await visitors.request.post(otherUrl + '/rsvp', { headers, data: otherInput });
        assert.equal(limited.status(), 429);
        const limitedBody = await limited.json();
        assert.equal(limitedBody.retryAfter, Number(limited.headers()['retry-after']));
        assert.ok(limitedBody.retryAfter >= 1 && limitedBody.retryAfter <= 60);
        assert.match(limitedBody.message, new RegExp(limitedBody.retryAfter + ' seconds'));
        const currentHtml = await visitors.request.get(url).then((res) => res.text());
        const currentState = JSON.parse(currentHtml.match(/<script id="rsvp-data" type="application\/json">(.*?)<\/script>/s)[1]).initial;
        const pending = currentState.guests.find((person) => person.status === 'pending');
        assert.ok(pending);
        const independent = await visitors.request.post(url + '/rsvp', {
            headers,
            data: { version: currentState.version, responses: [{ guest: pending.reference, status: 'attending' }] },
        });
        assert.equal(independent.status(), 200);
        await linkDialog();
        await page.getByRole('button', { name: 'Revoke link', exact: true }).click();
        await page.getByRole('button', { name: 'Revoke this link', exact: true }).click();
        assert.equal((await visitors.request.post(url + '/rsvp', { data: input, headers })).status(), 404);
        assert.equal((await visitors.request.get(url)).status(), 404);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

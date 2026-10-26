import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;
test('attendance monitors live responses with filters, bounded group details and responsive layouts', { skip: !enabled }, async () => {
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
        if (request.method() === 'POST' && new URL(request.url()).pathname.endsWith('/responses')) submissionRequests++;
    });
    for (const tab of [page, guest]) {
        tab.setDefaultTimeout(15000);
        tab.on('pageerror', (error) => errors.push(error.message));
    }
    const longName = 'LongUnbrokenGuestName'.repeat(8);
    const members = ['Juan Santos', 'Maria Santos', longName, ...Array.from({ length: 27 }, (_, index) => 'Pending person ' + index)];
    async function finish(label) {
        await page.getByRole('button', { name: label, exact: true }).click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    async function linkDialog(label = 'Santos Family') {
        await page.getByRole('button', { name: 'Actions for ' + label, exact: true }).click();
        await page.getByRole('menuitem', { name: 'RSVP Access', exact: true }).click();
    }
    try {
        await page.goto(origin + '/register');
        await page.getByLabel('Name', { exact: true }).fill('RSVP QA');
        await page.getByLabel('Email address').fill('rsvp-' + randomUUID() + '@example.test');
        const password = randomUUID() + 'Aa1!';
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Create account', exact: true }).click();
        await page.waitForURL('**/onboarding');
        await page.goto(origin + '/weddings/create');
        await page.getByLabel('Partner 1 name').fill('RSVP Published Alex');
        await page.getByLabel('Partner 2 name').fill('RSVP Published Jamie');
        await page.getByLabel(/Wedding date/).fill('2027-05-12');
        await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
        await page.waitForURL('**/onboarding/congratulations');
        await page.getByRole('link', { name: 'Enter your wedding dashboard', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const overview = page.url();
        await page.getByRole('link', { name: 'Invitation', exact: true }).click();
        await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
        await page.getByText('Draft saved', { exact: true }).waitFor();
        await page.goto(overview);
        await page.getByRole('button', { name: 'Publish invitation', exact: true }).click();
        await finish('Publish now');
        await page.getByRole('link', { name: 'Attendance', exact: true }).click();
        await page.getByRole('heading', { name: 'No invited guests yet', exact: true }).waitFor();
        assert.equal(await page.getByTestId('attendance-invited').innerText(), '0');
        await page.getByRole('link', { name: 'Guests', exact: true }).click();
        await page.getByRole('button', { name: 'New invitation group', exact: true }).click();
        await page.getByLabel('Group label', { exact: true }).fill('Santos Family');
        await page.getByLabel('Contact email (optional)').fill('private-rsvp@example.test');
        for (let index = 0; index < members.length; index++) {
            await page.getByLabel('Guest name', { exact: true }).fill(members[index]);
            await page.getByRole('button', { name: 'Add another guest', exact: true }).click();
        }
        await finish('Create group');
        await linkDialog();
        await page.getByRole('button', { name: 'Manage RSVP access for Juan Santos', exact: true }).click();
        await page.getByRole('button', { name: 'Generate link', exact: true }).click();
        const url = await page.getByLabel('Personal invitation URL', { exact: true }).inputValue();
        await page.keyboard.press('Escape');
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        await linkDialog();
        await page.getByRole('button', { name: 'Manage RSVP access for Maria Santos', exact: true }).click();
        await page.getByRole('button', { name: 'Assign a representative', exact: true }).click();
        const representative = page.getByLabel('Representative', { exact: true });
        const juanId = await representative
            .locator('option')
            .evaluateAll((options) => options.find((option) => option.textContent === 'Juan Santos').value);
        await representative.selectOption(juanId);
        await page.getByRole('button', { name: 'Review change', exact: true }).click();
        await page.getByRole('button', { name: 'Confirm representation change', exact: true }).click();
        await page.getByRole('dialog').getByRole('status').waitFor();
        await page.keyboard.press('Escape');
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        await guest.goto(url);
        const form = guest.locator('.invitation-rsvp');
        await form.getByLabel('Juan Santos', { exact: true }).selectOption('attending');
        await form.getByLabel('Maria Santos', { exact: true }).selectOption('declined');
        await form.getByRole('button', { name: 'Save responses', exact: true }).click();
        await form.getByRole('status').waitFor();
        assert.equal(submissionRequests, 1);
        assert.equal(setupRequests, 1);
        await page.goto(overview);
        await page.getByRole('link', { name: 'Attendance', exact: true }).click();
        await page.getByRole('heading', { name: 'Attendance', exact: true }).waitFor();
        const attendanceUrl = new URL(page.url());
        async function totals(invited, attending, declined, pending) {
            for (const [key, value] of Object.entries({ invited, attending, declined, pending })) {
                assert.equal(await page.getByTestId('attendance-' + key).innerText(), String(value));
            }
        }
        async function apply(search = '', status = 'all') {
            await page.getByLabel('Search guest or invitation group').fill(search);
            await page.getByLabel('RSVP status', { exact: true }).selectOption(status);
            await page.waitForFunction(
                ({ search, status }) => {
                    const query = new URL(location.href).searchParams;
                    return (
                        (query.get('search') ?? '') === search &&
                        (query.get('status') ?? 'all') === status &&
                        document.querySelector('section[aria-labelledby="attendance-results-heading"]').getAttribute('aria-busy') === 'false'
                    );
                },
                { search, status },
            );
        }
        await totals(30, 1, 1, 28);
        assert.equal(await page.locator('tbody tr').count(), 25);
        await page.getByRole('navigation', { name: 'Attendance pages' }).getByRole('button', { name: 'Next', exact: true }).click();
        await page.getByText('Page 2 of 2', { exact: false }).waitFor();
        assert.equal(await page.locator('tbody tr').count(), 5);
        await totals(30, 1, 1, 28);
        await apply('Juan');
        assert.equal(await page.locator('tbody tr').count(), 1);
        await totals(30, 1, 1, 28);
        const date = page.locator('tbody time:visible').first();
        assert.ok(Date.parse(await date.getAttribute('datetime')) > 0);
        await apply('Santos Family', 'pending');
        await page.getByText('28 matching guests', { exact: true }).waitFor();
        await apply('Missing person');
        await page.getByRole('heading', { name: 'No guests match these filters', exact: true }).waitFor();
        await apply();
        await page.getByRole('button', { name: 'Invitation groups', exact: true }).click();
        const details = page.locator('details').filter({ hasText: 'Santos Family' });
        await details.locator('summary').waitFor();
        let failed = false;
        await page.route('**/attendance/invitation-groups/*?*', async (route) => {
            if (!failed) {
                failed = true;
                await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
            } else await route.continue();
        });
        await details.locator('summary').focus();
        await page.keyboard.press('Enter');
        await details.getByRole('alert').waitFor();
        await details.getByRole('button', { name: 'Try again', exact: true }).click();
        await details.locator('tbody tr').first().waitFor();
        assert.equal(await details.locator('tbody tr').count(), 25);
        const pages = details.getByRole('navigation', { name: 'Guest pages for Santos Family' });
        await pages.getByRole('button', { name: 'Next', exact: true }).click();
        await details.getByText('26–30 of 30 matching guests', { exact: true }).waitFor();
        assert.equal(await details.locator('tbody tr').count(), 5);
        await pages.getByRole('button', { name: 'Previous', exact: true }).click();
        await details.getByText('1–25 of 30 matching guests', { exact: true }).waitFor();
        for (const width of [1440, 1024, 390]) {
            for (const theme of ['light', 'dark']) {
                await page.bringToFront();
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => {
                    document.documentElement.classList.toggle('dark', value === 'dark');
                }, theme);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `overflow at ${width} ${theme}`);
                await page.keyboard.press('Tab');
                await details.locator('summary').focus();
                assert.ok(await details.locator('summary').evaluate((element) => getComputedStyle(element).outlineStyle !== 'none'));
                await page.screenshot({ path: join(tmpdir(), `rsvp-5b-groups-${width}-${theme}.png`), animations: 'disabled' });
                await page.evaluate(() => scrollTo(0, 0));
                await page.screenshot({ path: join(tmpdir(), `rsvp-5b-header-${width}-${theme}.png`), animations: 'disabled' });
            }
        }
        await page.getByRole('button', { name: 'Individual guests', exact: true }).click();
        await page.waitForFunction(
            () => !document.querySelector('#attendance-search').disabled && new URL(location.href).searchParams.get('view') === 'guests',
        );
        await page.locator('tbody tr').first().waitFor();
        await page.keyboard.press('Tab');
        await page.getByLabel('Search guest or invitation group').focus();
        assert.ok(
            await page.getByLabel('Search guest or invitation group').evaluate((element) => {
                const style = getComputedStyle(element);
                return style.outlineStyle !== 'none' || style.boxShadow !== 'none';
            }),
        );
        await page.screenshot({ path: join(tmpdir(), 'rsvp-5b-guests-390-dark.png'), animations: 'disabled' });
        await guest.bringToFront();
        await form.getByLabel('Juan Santos', { exact: true }).selectOption('declined');
        await form.getByRole('button', { name: 'Save responses', exact: true }).click();
        await form.getByRole('status').waitFor();
        await page.bringToFront();
        await page.getByRole('button', { name: 'Refresh', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('[data-testid="attendance-declined"]').textContent === '2');
        await totals(30, 0, 2, 28);
        const outsider = await visitors.newPage();
        const denied = await outsider.goto(attendanceUrl.href);
        assert.ok(denied.url().endsWith('/login'));
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

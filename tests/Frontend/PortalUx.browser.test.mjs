import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;

test('portal navigation, guest tags, collapsed members and automatic filters preserve history', { skip: !enabled }, async () => {
    const origin = process.env.QA_BASE_URL;
    assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.setDefaultNavigationTimeout(15000);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    async function totals(groups, people) {
        assert.equal(await page.getByTestId('group-total').innerText(), String(groups));
        assert.equal(await page.getByTestId('people-total').innerText(), String(people));
    }
    async function submit(name) {
        await page.getByRole('button', { name, exact: true }).click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    try {
        await page.goto(origin + '/register');
        await page.getByLabel('Name', { exact: true }).fill('Guest QA');
        await page.getByLabel('Email address').fill('guests-' + randomUUID() + '@example.test');
        const password = randomUUID() + 'Aa1!';
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm password').fill(password);
        await page.getByRole('button', { name: 'Create account', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await page.goto(origin + '/weddings/create');
        await page.getByLabel('Partner 1 name').fill('Guest QA Alex');
        await page.getByLabel('Partner 2 name').fill('Guest QA Jamie');
        await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
        await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const overview = page.url();
        const nav = page.getByRole('navigation', { name: 'Wedding workspace', exact: true });
        assert.deepEqual(
            await nav
                .getByRole('link')
                .allTextContents()
                .then((labels) => labels.map((label) => label.trim())),
            ['Overview', 'Invitation', 'Guests', 'Attendance'],
        );
        assert.equal(await nav.getByRole('link', { name: 'Overview', exact: true }).getAttribute('aria-current'), 'page');
        assert.equal(await nav.getByRole('link', { name: 'Overview', exact: true }).getAttribute('href'), overview);
        assert.equal(await page.getByRole('link', { name: 'Customize invitation', exact: true }).count(), 0);
        assert.equal(await page.getByRole('link', { name: 'Preview invitation', exact: true }).count(), 0);
        await page.getByRole('button', { name: 'More', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Edit wedding details', exact: true }).click();
        await page.getByRole('heading', { name: 'Edit wedding details', exact: true }).waitFor();
        await nav.getByRole('link', { name: 'Invitation', exact: true }).click();
        await page.getByRole('heading', { name: 'Make it yours.', exact: true }).waitFor();
        assert.equal(await nav.getByRole('link', { name: 'Invitation', exact: true }).getAttribute('aria-current'), 'page');
        for (const width of [1440, 390]) {
            for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                await page.screenshot({ path: join(tmpdir(), `rsvp-ux-invitation-${width}-${theme}.png`), animations: 'disabled' });
            }
        }
        assert.ok((await page.getByRole('link', { name: /View saved draft/ }).getAttribute('href')).endsWith('/preview'));
        const previewOpened = context.waitForEvent('page');
        await page.getByRole('link', { name: /View saved draft/ }).click();
        const preview = await previewOpened;
        await preview.getByText('Private invitation preview', { exact: true }).waitFor();
        assert.equal(
            await preview
                .getByRole('navigation', { name: 'Wedding workspace', exact: true })
                .getByRole('link', { name: 'Invitation', exact: true })
                .getAttribute('aria-current'),
            'page',
        );
        await preview.close();
        await page.bringToFront();
        await nav.getByRole('link', { name: 'Guests', exact: true }).click();
        await page.waitForURL('**/guests');
        const guestsUrl = page.url();
        await page.getByRole('button', { name: 'New invitation group', exact: true }).click();
        await page.getByLabel('Group label', { exact: true }).fill('Refinement Family');
        await page.getByRole('button', { name: 'Create group', exact: true }).click();
        await page.getByText('Add at least one named guest.', { exact: true }).waitFor();
        const input = page.getByLabel('Guest name', { exact: true });
        const members = [
            'First guest',
            'Repeated name',
            'Repeated name',
            'Fourth guest',
            'Fifth guest',
            'Sixth guest',
            'W'.repeat(250),
            'Hidden eighth guest',
        ];
        for (let index = 0; index < members.length - 1; index++) {
            await input.fill(members[index]);
            if (index % 2) await page.getByRole('button', { name: 'Add another guest', exact: true }).click();
            else await input.press('Enter');
            assert.equal(await page.getByRole('dialog').getByRole('listitem').count(), index + 1);
            assert.equal(await input.inputValue(), '');
        }
        await input.fill('Remove this unsaved name');
        await input.press('Enter');
        await page.getByRole('button', { name: 'Remove unsaved guest 8: Remove this unsaved name', exact: true }).click();
        assert.ok(await input.evaluate((element) => document.activeElement === element));
        await input.fill(members.at(-1));
        for (const width of [1440, 390]) {
            for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                const dialog = page.getByRole('dialog');
                const box = await dialog.boundingBox();
                assert.ok(box.x >= 0 && box.x + box.width <= width && box.height <= 1000);
                for (let index = 0; index < 16; index++) {
                    await page.keyboard.press('Tab');
                    assert.ok(await dialog.evaluate((element) => element.contains(document.activeElement)));
                }
                await page.screenshot({ path: join(tmpdir(), `rsvp-ux-tags-${width}-${theme}.png`), animations: 'disabled' });
            }
        }
        await submit('Create group');
        await totals(1, 8);
        assert.equal(await page.getByRole('button', { name: /^Edit guest / }).count(), 5);
        const more = page.getByRole('button', { name: 'Show 3 more', exact: true });
        assert.equal(await more.getAttribute('aria-expanded'), 'false');
        await more.focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.getByRole('button', { name: /^Edit guest / }).count(), 8);
        await page.getByRole('button', { name: 'Show less', exact: true }).click();
        assert.equal(await page.getByRole('button', { name: /^Edit guest / }).count(), 5);
        const search = page.getByLabel('Search groups or guest names');
        const filterRequests = [];
        page.on('request', (request) => {
            if (request.headers()['x-inertia'] && new URL(request.url()).pathname.endsWith('/guests')) filterRequests.push(request.url());
        });
        await page.clock.install({ time: new Date('2026-10-19T12:00:00Z') });
        await page.clock.pauseAt(new Date('2026-10-19T12:00:01Z'));
        await search.fill('Hidden');
        await page.clock.runFor(200);
        await search.fill('Hidden eighth');
        await page.clock.runFor(349);
        assert.equal(filterRequests.length, 0);
        await page.clock.runFor(1);
        await page.clock.resume();
        await page.waitForURL((url) => url.searchParams.get('search') === 'Hidden eighth');
        assert.equal(filterRequests.length, 1);
        await page.getByRole('button', { name: 'Edit guest Hidden eighth guest', exact: true }).waitFor();
        assert.equal(await page.getByRole('button', { name: /^Edit guest / }).count(), 8);
        assert.equal(await page.getByRole('button', { name: 'Search', exact: true }).count(), 0);
        await search.fill('Refinement Family');
        await page.waitForURL((url) => url.searchParams.get('search') === 'Refinement Family');
        const beforeHistory = filterRequests.length;
        await page.goBack();
        await page.waitForURL((url) => url.searchParams.get('search') === 'Hidden eighth');
        assert.equal(await search.inputValue(), 'Hidden eighth');
        await page.goForward();
        await page.waitForURL((url) => url.searchParams.get('search') === 'Refinement Family');
        assert.equal(await search.inputValue(), 'Refinement Family');
        await page.waitForTimeout(450);
        assert.equal(filterRequests.length, beforeHistory);
        await page.reload();
        assert.equal(await search.inputValue(), 'Refinement Family');
        await page.getByRole('button', { name: 'Clear search', exact: true }).click();
        await page.waitForURL((url) => !url.searchParams.get('search'));
        await search.fill('Cancel this pending query');
        await page.goBack();
        await page.waitForURL((url) => url.searchParams.get('search') === 'Refinement Family');
        await page.waitForTimeout(450);
        assert.equal(await search.inputValue(), 'Refinement Family');
        assert.ok(!new URL(page.url()).searchParams.get('search').includes('Cancel'));
        await page.getByRole('button', { name: 'Clear search', exact: true }).click();
        await page.waitForURL((url) => !url.searchParams.get('search'));
        await page.clock.pauseAt(new Date(await page.evaluate(() => Date.now() + 1000)));
        const beforeEditing = filterRequests.length;
        await search.fill('pending while editing');
        await page.getByRole('button', { name: 'Edit guest First guest', exact: true }).click();
        await page.getByRole('dialog').waitFor();
        await page.clock.runFor(500);
        assert.equal(filterRequests.length, beforeEditing);
        await page.getByLabel('Guest name', { exact: true }).fill('First guest renamed');
        await page.clock.resume();
        await submit('Save changes');
        await page.getByRole('button', { name: 'Edit guest First guest renamed', exact: true }).waitFor();
        await totals(1, 8);
        let delayedResponse = Promise.resolve();
        let failNextSearch = true;
        await page.route('**/guests?*', async (route) => {
            if (new URL(route.request().url()).searchParams.get('search') === 'retry me' && failNextSearch) {
                failNextSearch = false;
                return route.abort('failed');
            }
            if (new URL(route.request().url()).searchParams.get('search') !== 'stale') return route.continue();
            const response = await route.fetch();
            delayedResponse = new Promise((resolve) => setTimeout(resolve, 700)).then(() => route.fulfill({ response }).catch(() => {}));
            await delayedResponse;
        });
        await search.fill('retry me');
        await page.getByRole('alert').filter({ hasText: 'These results could not be loaded' }).waitFor();
        await page.getByRole('button', { name: 'Try again', exact: true }).click();
        await page.getByRole('heading', { name: 'No matching invitations', exact: true }).waitFor();
        await page.getByRole('button', { name: 'Clear search', exact: true }).click();
        await page.waitForURL((url) => !url.searchParams.get('search'));
        const staleRequest = page.waitForRequest((request) => new URL(request.url()).searchParams.get('search') === 'stale');
        await search.fill('stale');
        await staleRequest;
        await search.fill('Hidden eighth');
        await page.waitForURL((url) => url.searchParams.get('search') === 'Hidden eighth');
        await delayedResponse;
        assert.equal(await search.inputValue(), 'Hidden eighth');
        await page.getByRole('button', { name: 'Edit guest Hidden eighth guest', exact: true }).waitFor();
        await page.unroute('**/guests?*');
        await page.getByRole('button', { name: 'Clear search', exact: true }).click();
        await page.waitForURL((url) => !url.searchParams.get('search'));
        const cookie = (await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN');
        const createUrl = guestsUrl.replace(/\/guests$/, '/invitation-groups');
        for (let index = 0; index < 25; index++) {
            const response = await context.request.post(createUrl, {
                headers: { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) },
                data: { label: 'Pagination family ' + index, guests: [{ name: 'Pagination guest ' + index }] },
            });
            assert.equal(response.status(), 200);
        }
        await page.goto(guestsUrl);
        await totals(26, 33);
        await page.getByRole('navigation', { name: 'Guest list pages', exact: true }).getByRole('button', { name: 'Next', exact: true }).click();
        await page.getByText('Page 2 of 2', { exact: true }).waitFor();
        await search.fill('Hidden eighth');
        await page.waitForURL((url) => url.searchParams.get('search') === 'Hidden eighth' && url.searchParams.get('page') === '1');
        await page.getByRole('button', { name: 'Edit guest Hidden eighth guest', exact: true }).waitFor();
        await totals(26, 33);
        await nav.getByRole('link', { name: 'Attendance', exact: true }).click();
        await page.getByTestId('attendance-invited').waitFor();
        assert.equal(await page.getByTestId('attendance-invited').innerText(), '33');
        assert.equal(await page.getByTestId('attendance-pending').innerText(), '33');
        const attendanceRequests = [];
        page.on('request', (request) => {
            if (request.headers()['x-inertia'] && new URL(request.url()).pathname.endsWith('/attendance')) attendanceRequests.push(request.url());
        });
        await page.clock.pauseAt(new Date(await page.evaluate(() => Date.now() + 1000)));
        await page.getByLabel('RSVP status', { exact: true }).selectOption('declined');
        assert.equal(attendanceRequests.length, 1);
        await page.clock.resume();
        await page.waitForURL((url) => url.searchParams.get('status') === 'declined');
        await page.getByRole('heading', { name: 'No guests match these filters', exact: true }).waitFor();
        assert.equal(await page.getByTestId('attendance-invited').innerText(), '33');
        assert.equal(await page.getByTestId('attendance-pending').innerText(), '33');
        assert.equal(await page.getByRole('button', { name: 'Apply filters', exact: true }).count(), 0);
        await page.getByRole('button', { name: 'Clear filters', exact: true }).click();
        await page.waitForURL((url) => url.searchParams.get('status') === 'all');
        await page.getByLabel('Search guest or invitation group').fill('Hidden eighth');
        await page.waitForURL((url) => url.searchParams.get('search') === 'Hidden eighth');
        await page.goBack();
        await page.waitForURL((url) => !url.searchParams.get('search'));
        assert.equal(await page.getByLabel('Search guest or invitation group').inputValue(), '');
        await page.getByRole('navigation', { name: 'Attendance pages', exact: true }).getByRole('button', { name: 'Next', exact: true }).click();
        await page.getByText('Page 2 of 2', { exact: false }).waitFor();
        await page.getByLabel('RSVP status', { exact: true }).selectOption('pending');
        await page.waitForURL((url) => url.searchParams.get('status') === 'pending' && url.searchParams.get('page') === '1');
        for (const width of [1440, 1024, 390]) {
            for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                await page.screenshot({ path: join(tmpdir(), `rsvp-ux-attendance-${width}-${theme}.png`), animations: 'disabled' });
                await nav.getByRole('link', { name: 'Overview', exact: true }).click();
                await page.getByRole('button', { name: 'More', exact: true }).focus();
                await page.keyboard.press('Enter');
                await page.getByRole('menuitem', { name: 'Edit wedding details', exact: true }).waitFor();
                assert.equal(await page.getByRole('menu').evaluate((element) => getComputedStyle(element).animationName), 'none');
                await page.keyboard.press('Escape');
                await page.getByRole('menu').waitFor({ state: 'hidden' });
                await page.waitForFunction(() => document.activeElement?.textContent?.trim() === 'More');
                assert.ok(await page.getByRole('button', { name: 'More', exact: true }).evaluate((element) => document.activeElement === element));
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                await page.screenshot({ path: join(tmpdir(), `rsvp-ux-overview-${width}-${theme}.png`), animations: 'disabled' });
                await nav.getByRole('link', { name: 'Attendance', exact: true }).click();
                await page.getByRole('heading', { name: 'Attendance', exact: true }).waitFor();
            }
        }
        await nav.getByRole('link', { name: 'Guests', exact: true }).click();
        await page.getByRole('button', { name: 'New invitation group', exact: true }).click();
        await page.getByLabel('Group label', { exact: true }).fill('Unsaved capacity check');
        for (let index = 0; index < 100; index++) {
            await page.getByLabel('Guest name', { exact: true }).fill('Capacity guest');
            await page.getByLabel('Guest name', { exact: true }).press('Enter');
        }
        assert.equal(await page.getByRole('dialog').getByRole('listitem').count(), 100);
        assert.ok(await page.getByLabel('Guest name', { exact: true }).isDisabled());
        assert.ok(await page.getByRole('button', { name: 'Add another guest', exact: true }).isDisabled());
        await page.getByRole('button', { name: 'Remove unsaved guest 1: Capacity guest', exact: true }).click();
        assert.equal(await page.getByRole('dialog').getByRole('listitem').count(), 99);
        assert.ok(await page.getByLabel('Guest name', { exact: true }).isEnabled());
        await page.keyboard.press('Escape');
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        await totals(26, 33);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

const enabled = process.env.QA_ISOLATED_SQLITE === '1' && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BASE_URL;

test('guest group CRUD, counts, search, confirmations and responsive keyboard interaction', { skip: !enabled }, async () => {
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
    async function menu(label, action) {
        await page.getByRole('button', { name: 'Actions for ' + label, exact: true }).click();
        const menu = page.getByRole('menu');
        assert.equal(await menu.evaluate((element) => getComputedStyle(element).animationName), 'none');
        await page.getByRole('menuitem', { name: action, exact: true }).focus();
        await page.keyboard.press('Enter');
        await page.getByRole('dialog').waitFor();
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
        await page.getByRole('link', { name: 'Guest Management', exact: true }).click();
        await page.waitForURL('**/guests');
        await totals(0, 0);
        await page.getByRole('heading', { name: 'Start your guest list', exact: true }).waitFor();
        await page.screenshot({ path: join(tmpdir(), 'rsvp-guests-empty.png') });
        const create = page.getByRole('button', { name: 'New invitation group', exact: true });
        await create.click();
        await page
            .getByRole('dialog')
            .locator('form')
            .evaluate((form) => {
                form.noValidate = true;
            });
        await page.getByRole('button', { name: 'Create group', exact: true }).click();
        await page.getByRole('alert').waitFor();
        assert.ok(await page.getByLabel('Group label', { exact: true }).evaluate((el) => document.activeElement === el));
        await totals(0, 0);
        await page.getByLabel('Group label', { exact: true }).fill('Santos Family');
        await page.getByLabel('Contact email (optional)').fill('family@example.test');
        await page.getByLabel('Contact phone (optional)').fill('+63 917 000 0000');
        await page.getByLabel('Guest 1 name', { exact: true }).fill('John Santos');
        await page.getByRole('button', { name: 'Add another guest', exact: true }).click();
        await page.getByLabel('Guest 2 name', { exact: true }).fill('Anna Santos');
        await submit('Create group');
        await totals(1, 2);
        await page.reload();
        await totals(1, 2);
        await menu('Santos Family', 'Add guest');
        await page.getByLabel('Guest name', { exact: true }).fill('Third person');
        await submit('Add guest');
        await totals(1, 3);
        await menu('Santos Family', 'Edit group');
        const longLabel = 'Family ' + 'L'.repeat(220);
        await page.getByLabel('Group label', { exact: true }).fill(longLabel);
        await submit('Save changes');
        const longName = 'W'.repeat(250);
        await page.getByRole('button', { name: 'Edit guest John Santos', exact: true }).click();
        await page.getByLabel('Guest name', { exact: true }).fill(longName);
        await submit('Save changes');
        for (const width of [1440, 1024, 390]) {
            for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 1000 });
                await page.evaluate((value) => document.documentElement.classList.toggle('dark', value === 'dark'), theme);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                await page.screenshot({ path: join(tmpdir(), 'rsvp-guests-list-' + width + '-' + theme + '.png'), animations: 'disabled' });
                await create.click();
                assert.equal(await page.getByLabel('Group label', { exact: true }).inputValue(), '');
                assert.equal(await page.getByLabel('Contact email (optional)').inputValue(), '');
                assert.equal(await page.getByLabel('Guest 1 name', { exact: true }).inputValue(), '');
                const dialog = page.getByRole('dialog');
                await dialog.waitFor();
                await page.getByRole('button', { name: 'Add another guest', exact: true }).click();
                await page.getByRole('button', { name: 'Add another guest', exact: true }).click();
                const box = await dialog.boundingBox();
                assert.ok(box.x >= 0 && box.x + box.width <= width && box.y >= 0 && box.y + box.height <= 1000);
                for (let i = 0; i < 14; i++) {
                    await page.keyboard.press('Tab');
                    assert.ok(await dialog.evaluate((element) => element.contains(document.activeElement)));
                    assert.ok(
                        await page.evaluate(() => {
                            const style = getComputedStyle(document.activeElement);
                            return style.outlineStyle !== 'none' || style.boxShadow !== 'none';
                        }),
                    );
                }
                await page.screenshot({ path: join(tmpdir(), 'rsvp-guests-modal-' + width + '-' + theme + '.png'), animations: 'disabled' });
                await page.keyboard.press('Escape');
                await dialog.waitFor({ state: 'hidden' });
                assert.ok(await create.evaluate((element) => document.activeElement === element));
            }
        }
        await page.getByLabel('Search groups or guest names').fill('Anna Santos');
        await page.getByRole('button', { name: 'Search', exact: true }).click();
        await page.getByText('1 matching groups. Totals above include your entire wedding.', { exact: true }).waitFor();
        await totals(1, 3);
        await page.getByLabel('Search groups or guest names').fill('Missing guest');
        await page.getByRole('button', { name: 'Search', exact: true }).click();
        await page.getByRole('heading', { name: 'No matching invitations', exact: true }).waitFor();
        await totals(1, 3);
        await page.getByRole('button', { name: 'Clear search', exact: true }).click();
        await page.getByRole('button', { name: 'Edit guest Third person', exact: true }).waitFor();
        await page.getByRole('button', { name: 'Edit guest Third person', exact: true }).click();
        await page.getByRole('button', { name: 'Remove guest', exact: true }).click();
        await page.getByRole('button', { name: 'Cancel', exact: true }).click();
        await totals(1, 3);
        await page.getByRole('button', { name: 'Edit guest Third person', exact: true }).click();
        await page.getByRole('button', { name: 'Remove guest', exact: true }).click();
        await submit('Remove guest permanently');
        await totals(1, 2);
        assert.ok(await page.getByRole('heading', { name: 'Guest management', exact: true }).evaluate((el) => document.activeElement === el));
        await page.getByRole('button', { name: 'Edit guest Anna Santos', exact: true }).click();
        await page.getByRole('button', { name: 'Remove guest', exact: true }).click();
        await submit('Remove guest permanently');
        await totals(1, 1);
        await page.getByRole('button', { name: 'Edit guest ' + longName, exact: true }).click();
        assert.ok(await page.getByRole('button', { name: 'Remove guest', exact: true }).isDisabled());
        await page.keyboard.press('Escape');
        await menu(longLabel, 'Delete group');
        await page.getByRole('button', { name: 'Cancel', exact: true }).click();
        await totals(1, 1);
        await menu(longLabel, 'Delete group');
        await submit('Delete group permanently');
        await totals(0, 0);
        assert.ok(await page.getByRole('heading', { name: 'Guest management', exact: true }).evaluate((el) => document.activeElement === el));
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

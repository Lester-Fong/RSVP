import assert from 'node:assert/strict';
import test from 'node:test';
import { createCmsEditorPreview } from './cms-editor-preview.mjs';

const enabled = process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE;

test('owner withdraws and reinstates an invitation while active attendance and RSVP access update', { skip: !enabled, timeout: 180000 }, async () => {
    const preview = await createCmsEditorPreview({ publishing: true, personal: true, withdrawal: true });
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
    const page = await browser.newPage({ viewport: { width: 1365, height: 900 }, reducedMotion: 'reduce' });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    try {
        await page.goto(preview.origin + '/login');
        await page.getByLabel('Email address').fill(preview.owner.email);
        await page.getByLabel('Password', { exact: true }).fill(preview.owner.password);
        await page.getByRole('button', { name: 'Log in', exact: true }).click();
        await page.waitForURL('**/dashboard');
        await page.goto(preview.origin + '/accounts/1/weddings/1/guests');
        await page.waitForFunction(() => document.querySelector('[data-testid="people-total"]')?.textContent?.trim() === '4');
        await page.getByRole('button', { name: 'Withdraw', exact: true }).first().click();
        const dialog = page.getByRole('dialog');
        await dialog.getByText(/personal link and representative permissions are revoked/i).waitFor();
        await dialog.getByText('Nico Santos').waitFor();
        await dialog.getByRole('button', { name: 'Withdraw invitation', exact: true }).click();
        await dialog.waitFor({ state: 'hidden' });
        await page.waitForFunction(() => document.querySelector('[data-testid="people-total"]')?.textContent?.trim() === '3');
        await page.waitForFunction(() => document.querySelector('[data-testid="withdrawn-total"]')?.textContent?.trim() === '1');
        await page.getByRole('button', { name: 'Edit guest Nico Santos', exact: true }).waitFor();
        await page.getByLabel('Invitation status').selectOption('withdrawn');
        await page.getByRole('button', { name: 'Edit guest Maya Santos', exact: true }).waitFor();
        await page.setViewportSize({ width: 390, height: 844 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        const reinstateButton = page.getByRole('button', { name: 'Reinstate', exact: true });
        await reinstateButton.focus();
        await page.keyboard.press('Enter');
        const reinstateDialog = page.getByRole('dialog');
        await reinstateDialog.waitFor();
        await page.getByText(/previous personal link or representative designation will not be restored/i).waitFor();
        assert.ok(await reinstateDialog.evaluate((element) => element.contains(document.activeElement)));
        await reinstateDialog.getByRole('button', { name: 'Reinstate invitation', exact: true }).click();
        await reinstateDialog.waitFor({ state: 'hidden' });
        await page.waitForFunction(() => document.querySelector('[data-testid="people-total"]')?.textContent?.trim() === '4');
        await page.waitForFunction(() => document.querySelector('[data-testid="withdrawn-total"]')?.textContent?.trim() === '0');
        await page.getByRole('link', { name: 'Attendance', exact: true }).click();
        await page.waitForURL('**/attendance');
        await page.waitForFunction(() => document.querySelector('[data-testid="attendance-invited"]')?.textContent?.trim() === '4');
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
        await preview.close();
    }
});

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createCmsEditorPreview } from './cms-editor-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);

async function browser() {
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    return chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
}

async function signIn(page, host) {
    await page.goto(host.origin + '/login');
    await page.getByLabel('Email address').fill(host.owner.email);
    await page.getByLabel('Password', { exact: true }).fill(host.owner.password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
}

test('staging denies editor and published invitation without separate authorization', { skip: !enabled, timeout: 120000 }, async () => {
    const host = await createCmsEditorPreview({ publishing: true, personal: true, serverEnvironment: 'staging' });
    const edge = await browser();
    const context = await edge.newContext();
    const page = await context.newPage();
    try {
        assert.equal((await context.request.get(host.origin + '/w/synthetic-editor-preview')).status(), 404);
        assert.equal((await context.request.get(host.owner.personal.representativeUrl)).status(), 404);
        await signIn(page, host);
        assert.equal((await page.goto(host.previewUrl)).status(), 404);
    } finally {
        await context.close();
        await edge.close();
        await host.close();
    }
});

test(
    'explicit staging authorization permits saved draft and publication through real session and CSRF',
    { skip: !enabled, timeout: 120000 },
    async () => {
        const host = await createCmsEditorPreview({ publishing: true, serverEnvironment: 'staging', stagingAuthorized: true });
        const edge = await browser();
        const context = await edge.newContext();
        const page = await context.newPage();
        const pageErrors = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        try {
            await signIn(page, host);
            await page.goto(host.previewUrl);
            await page.getByRole('heading', { name: 'Make it yours' }).waitFor();
            await page.getByRole('button', { name: 'Our Story', exact: true }).click();
            await page.getByLabel('Story heading', { exact: true }).fill('Staging browser smoke story');
            await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
            await page.getByLabel('Story paragraph 1', { exact: true }).fill('A safely published staging paragraph.');
            await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
            await page.getByText('All changes saved', { exact: false }).waitFor();
            await page.getByRole('button', { name: 'Publish', exact: true }).click();
            await page.getByRole('dialog').getByRole('button', { name: 'Confirm Publish', exact: true }).click();
            await page.getByRole('button', { name: 'Published', exact: true }).waitFor();
            const publicPage = await context.request.get(host.origin + '/w/synthetic-editor-preview');
            assert.equal(publicPage.status(), 200);
            assert.ok((await publicPage.text()).includes('Staging browser smoke story'), 'published story heading should appear');
            assert.deepEqual(pageErrors, []);
        } finally {
            await context.close();
            await edge.close();
            await host.close();
        }
    },
);

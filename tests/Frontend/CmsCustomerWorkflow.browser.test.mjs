import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import { createCmsEditorPreview } from './cms-editor-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let browser, host, owner, visitors, page, guest, cms, overview, publicUrl, artifacts;
const errors = [];
before(async () => {
    if (!enabled) return;
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
beforeEach(async () => {
    if (!enabled) return;
    host = await createCmsEditorPreview({ publishing: true, personal: true });
    assert.notEqual(new URL(host.origin).port, '8000');
    artifacts = join(host.root, 'qa-customer-integration');
    await mkdir(artifacts);
    errors.length = 0;
    owner = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    visitors = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'no-preference', recordVideo: { dir: artifacts } });
    for (const context of [owner, visitors])
        context.on('page', (tab) => {
            tab.setDefaultTimeout(20000);
            tab.on('pageerror', (error) => errors.push(error.message.replace(/[a-f0-9]{64}/g, '[redacted]')));
        });
    page = await owner.newPage();
    guest = await visitors.newPage();
});
afterEach(async () => {
    const video = guest?.video();
    await owner?.close();
    await visitors?.close();
    if (video) await video.saveAs(join(artifacts, 'customer-guest.webm'));
    await host?.close();
    assert.deepEqual(errors, []);
});
after(async () => browser?.close());
const run = (name, work) => test(name, { skip: !enabled, timeout: 180000 }, work);
const section = (name) => page.getByRole('navigation', { name: 'Editor sections' }).getByRole('button', { name, exact: true }).click();
const source = async () => await (await owner.request.get(cms + '/draft')).json();
async function save() {
    await page.getByRole('button', { name: 'Save Draft', exact: true }).click();
    await page.getByText('All changes saved', { exact: false }).waitFor();
}
async function registerWedding() {
    await page.goto(host.origin + '/register');
    await page.getByLabel('Name', { exact: true }).fill('Customer workflow reviewer');
    await page.getByLabel('Email address').fill('customer-' + randomUUID() + '@example.test');
    const password = randomUUID() + 'Aa1!';
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Confirm password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Create account', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await page.getByRole('link', { name: 'Create your first wedding', exact: true }).first().click();
    await page.getByLabel('Partner 1 name').fill('Marina Customer');
    await page.getByLabel('Partner 2 name').fill('Elias Customer');
    await page.getByLabel(/Wedding date/).fill('2028-04-16');
    await page.getByLabel(/Wedding time/).fill('15:30');
    await page.getByLabel('Ceremony venue').fill('Our real customer chapel');
    await page.getByLabel('Reception venue').fill('Our real customer garden');
    await page.getByRole('button', { name: 'Create wedding', exact: true }).click();
    await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
    overview = page.url();
    assert.notEqual(overview, host.previewUrl.replace('/cms-preview', ''));
    await page.goto(host.origin + '/dashboard');
    await page.getByRole('heading', { name: 'Marina Customer & Elias Customer', exact: true }).click();
    await page.getByRole('link', { name: 'Invitation', exact: true }).click();
    await page.waitForURL('**/cms-preview');
    cms = page.url();
    await page.reload();
    await page.getByRole('heading', { name: 'Make it yours', exact: true }).waitFor();
    await page.getByText('Preview content checked', { exact: true }).waitFor();
    const props = await page.locator('#app').evaluate((element) => JSON.parse(element.dataset.page).props);
    publicUrl = props.publication.publicUrl;
    assert.equal(props.wedding.partner_one_name, 'Marina Customer');
    assert.equal(props.wedding.wedding_date, '2028-04-16');
    assert.equal(props.initialDocument.template_key, 'enchanted-editorial');
    assert.equal(props.draftVersion, 0);
    assert.deepEqual(props.initialImages, []);
    assert.equal((await owner.request.get(host.previewUrl)).status(), 403);
    assert.equal((await visitors.request.get(publicUrl)).status(), 404);
}
async function uploadHero(color) {
    await page
        .getByRole('group', { name: 'Main wedding photo', exact: true })
        .getByRole('button', { name: /^(Choose|Change) photo$/ })
        .click();
    const dialog = page.getByRole('dialog');
    await page.waitForFunction(() => {
        const input = document.querySelector('[role=dialog] input[type=file]');
        return input && !input.disabled;
    });
    const png = await page.evaluate((fill) => {
        const canvas = document.createElement('canvas');
        canvas.width = 640;
        canvas.height = 800;
        const brush = canvas.getContext('2d');
        brush.fillStyle = fill;
        brush.fillRect(0, 0, 640, 800);
        return canvas.toDataURL('image/png').split(',')[1];
    }, color);
    const uploaded = page.waitForResponse((response) => response.request().method() === 'POST' && response.url() === cms + '/images');
    await dialog
        .locator('input[type=file]')
        .setInputFiles({ name: 'customer-upload.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
    const response = await uploaded;
    assert.equal(response.status(), 201);
    const { image } = await response.json();
    await dialog.getByRole('button', { name: 'Use photograph 1', exact: true }).click();
    await dialog.waitFor({ state: 'hidden' });
    return image;
}
async function publish() {
    await page.getByRole('button', { name: /^(Publish|Republish)$/, exact: true }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('button', { name: /^Confirm (Publish|Republish)$/ }).click();
    await dialog.waitFor({ state: 'hidden' });
    await page.getByRole('button', { name: 'Published', exact: true }).waitFor();
}

run('real registration and wedding workflow reuses complete CMS, scoped uploads, saved preview, immutable publish and personal RSVP', async () => {
    await registerWedding();
    const initial = await source();
    assert.equal(initial.draft_version, 0);
    assert.equal(initial.document.content.sections.length, 9);
    assert.deepEqual(initial.document.content.sections.find((value) => value.type === 'gallery').data.items, []);
    assert.equal(initial.document.content.experience.enabled, true);
    await page.getByLabel('Invitation message', { exact: true }).fill('A wedding authored by Marina and Elias');
    const first = await uploadHero('#b1ba9a');
    await save();
    const second = await uploadHero('#b59c8a');
    await page.getByRole('group', { name: 'Main wedding photo', exact: true }).getByLabel('Photo frame').selectOption('fit');
    await section('Countdown');
    await page.getByLabel('Countdown heading').fill('Counting down to our day');
    await section('Our Story');
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click();
    await page.getByLabel('Story paragraph 1', { exact: true }).fill('Our own story, saved to our wedding.');
    await page.getByRole('button', { name: 'Add milestone', exact: true }).click();
    await page.getByLabel('Milestone title', { exact: true }).fill('Our first conversation');
    await page.getByLabel('Milestone description', { exact: true }).fill('A moment worth remembering.');
    await page.getByLabel('Milestone date precision').selectOption('year');
    await page.getByLabel('Milestone year').fill('2022');
    await section('Gallery');
    await page.getByRole('button', { name: 'Add gallery photograph', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Use photograph 2', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Add selected photographs (1)', exact: true }).click();
    await page.getByRole('region', { name: 'Gallery editing' }).getByLabel('Show section', { exact: true }).uncheck();
    await section('Entourage');
    await page.getByRole('button', { name: 'Add role group', exact: true }).click();
    await page.getByLabel('Role group title').fill('Principal sponsors');
    await page.getByLabel('Add a name', { exact: true }).fill('Our chosen sponsor');
    await page.getByLabel('Add a name', { exact: true }).press('Enter');
    await section('Guest Guide');
    await page.getByLabel('Show Attire Guide').check();
    await page.getByLabel('Dress code', { exact: true }).fill('Garden formal');
    await save();
    const saved = await source();
    assert.equal(saved.document.content.photo_slots.hero.asset_id, second.id);
    assert.equal(saved.document.content.photo_slots.hero.frame, 'fit');
    const gallery = saved.document.content.sections.find((value) => value.type === 'gallery');
    assert.equal(gallery.enabled, false);
    assert.equal(gallery.data.items[0].image.asset_id, first.id);
    assert.equal((await (await owner.request.get(cms + '/images')).json()).images.length, 2);
    await page.reload();
    await section('Guest Guide');
    assert.equal(await page.getByLabel('Dress code', { exact: true }).inputValue(), 'Garden formal');
    assert.equal(await page.getByLabel('Photobooth opening', { exact: true }).isChecked(), true);
    assert.deepEqual(await source(), saved);
    for (const [device, width] of [
        ['desktop', 1440],
        ['mobile', 390],
    ]) {
        await page.setViewportSize({ width, height: 1000 });
        await section('Countdown');
        assert.equal(await page.getByLabel('Countdown heading').inputValue(), 'Counting down to our day');
        if (width === 1440) {
            await page.frameLocator('iframe[title="Wedding website preview"]').getByText('Counting down to our day', { exact: true }).waitFor();
            await page.frameLocator('iframe[title="Wedding website preview"]').locator('[data-ee-phase="landed"]').waitFor();
        }
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.screenshot({ path: join(artifacts, device + '-customer-editor.png') });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    const savedPage = await owner.newPage();
    assert.equal((await savedPage.goto(cms + '/saved-preview')).status(), 200);
    await savedPage.locator('[data-ee-phase="landed"]').waitFor();
    await savedPage.getByText('A wedding authored by Marina and Elias', { exact: true }).waitFor();
    await savedPage.close();
    await section('Welcome');
    await page.getByLabel('Invitation message', { exact: true }).fill('An unsaved message must remain private');
    assert.equal(await page.getByRole('button', { name: 'Publish', exact: true }).isDisabled(), true);
    assert.equal((await source()).document.content.sections[0].data.message, 'A wedding authored by Marina and Elias');
    await page.getByLabel('Invitation message', { exact: true }).fill('A wedding authored by Marina and Elias');
    await publish();
    await guest.goto(publicUrl);
    await guest.getByRole('button', { name: 'Skip animation', exact: false }).click();
    await guest.locator('[data-ee-phase="landed"]').waitFor();
    await guest.getByText('A wedding authored by Marina and Elias', { exact: true }).waitFor();
    assert.equal(await guest.locator('form, #our-photos, #personal-rsvp-data').count(), 0);
    assert.equal(await guest.locator('.ee-timeline-entry').count(), 1);
    assert.equal(await guest.locator('.ee-hummingbird').count(), 1);
    const hero = await guest.locator('.ee-hero-main img').getAttribute('src');
    await guest.screenshot({ path: join(artifacts, 'mobile-customer-published.png') });
    await guest.setViewportSize({ width: 1440, height: 1000 });
    await guest.screenshot({ path: join(artifacts, 'desktop-customer-published.png') });
    await section('Welcome');
    assert.equal((await source()).document.content.photo_slots.hero.asset_id, second.id);
    await page
        .getByRole('group', { name: 'Main wedding photo', exact: true })
        .getByRole('button', { name: 'Remove from section', exact: true })
        .click();
    await page.getByLabel('Invitation message', { exact: true }).fill('Our explicitly republished invitation');
    await save();
    await guest.reload();
    await guest.getByText('A wedding authored by Marina and Elias', { exact: true }).waitFor();
    assert.equal((await visitors.request.get(new URL(hero, publicUrl).href)).status(), 200);
    await publish();
    await guest.reload();
    await guest.getByText('Our explicitly republished invitation', { exact: true }).waitFor();
    assert.equal((await visitors.request.get(new URL(hero, publicUrl).href)).status(), 404);
    assert.equal((await owner.request.get(cms + '/images/' + second.id)).status(), 200);
    const fallback = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    try {
        const plain = await fallback.newPage();
        await plain.goto(publicUrl);
        await plain.getByText('Our explicitly republished invitation', { exact: true }).waitFor();
        assert.equal(await plain.locator('form').count(), 0);
    } finally {
        await fallback.close();
    }
    await page.goto(overview + '/guests');
    await page.getByRole('button', { name: 'New invitation group', exact: true }).click();
    await page.getByLabel('Group label', { exact: true }).fill('Our personal guests');
    await page.getByLabel('Guest name', { exact: true }).fill('Our invited guest');
    await page.getByLabel('Guest name', { exact: true }).press('Enter');
    await page.getByRole('button', { name: 'Create group', exact: true }).click();
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
    await page.getByRole('button', { name: 'Actions for Our personal guests', exact: true }).click();
    await page.getByRole('menuitem', { name: 'RSVP Access', exact: true }).click();
    await page.getByRole('button', { name: 'Manage RSVP access for Our invited guest', exact: true }).click();
    await page.getByRole('button', { name: 'Generate link', exact: true }).click();
    const personal = await page.getByLabel('Personal invitation URL', { exact: true }).inputValue();
    await guest.evaluate(() => sessionStorage.clear());
    await guest.goto(personal);
    await guest.getByRole('button', { name: 'Skip animation', exact: false }).click();
    await guest.locator('[data-ee-phase="landed"]').waitFor();
    await guest.getByLabel('Our invited guest', { exact: true }).selectOption('attending');
    await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
    await guest.locator('[data-rsvp-confirmation="attending"]').waitFor();
    await guest.screenshot({ path: join(artifacts, 'customer-personal-rsvp.png') });
    await guest.reload();
    assert.equal(await guest.locator('[data-rsvp-confirmation]').count(), 0);
    await guest.goto(publicUrl);
    assert.doesNotMatch(await guest.content(), /Our invited guest|personal-rsvp-data|respondedAt/);
    console.log('Customer workflow screenshots/motion: ' + artifacts);
});

import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import { createCmsEditorPreview } from './cms-editor-preview.mjs';

const enabled = Boolean(process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let browser, host, context, page, personal, artifacts;
const errors = [];
before(async () => {
    if (!enabled) return;
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
beforeEach(async () => {
    if (!enabled) return;
    // Always create a disposable automated target. Never read the retained manual profile.
    host = await createCmsEditorPreview({ publishing: true, personal: true });
    assert.notEqual(new URL(host.origin).port, '8000');
    personal = host.owner.personal.representativeUrl;
    artifacts = join(host.root, 'qa-del05');
    await mkdir(artifacts);
    errors.length = 0;
    context = await browser.newContext({ viewport: { width: 1366, height: 768 }, reducedMotion: 'no-preference', recordVideo: { dir: artifacts } });
    context.on('page', (tab) => {
        tab.setDefaultTimeout(15000);
        tab.on('pageerror', (error) => errors.push(error.message.replace(/[a-f0-9]{64}/g, '[redacted]')));
    });
    page = await context.newPage();
});
afterEach(async () => {
    const video = page?.video();
    await context?.close();
    if (video) await video.saveAs(join(artifacts, 'guest-journey.webm'));
    await host?.close();
    assert.deepEqual(errors, []);
});
after(async () => browser?.close());
const run = (name, fn) => test(name, { skip: !enabled, timeout: 90000 }, fn);
const confirmation = (tab = page) => tab.locator('[data-rsvp-confirmation]');
const submit = (tab = page) => tab.getByRole('button', { name: 'Save responses', exact: true });
async function visit(tab = page) {
    await tab.goto(personal);
    await tab.locator('.ee-booth-skip, [data-ee-phase="landed"]').first().waitFor();
    const skip = tab.getByRole('button', { name: 'Skip animation', exact: false });
    if (await skip.isVisible()) await skip.click();
    await tab.locator('[data-ee-phase="landed"]').waitFor();
    await tab.getByLabel('Maya Santos', { exact: true }).waitFor();
}
async function choose(first, second, tab = page) {
    if (first) await tab.getByLabel('Maya Santos', { exact: true }).selectOption(first);
    if (second) await tab.getByLabel('Nico Santos', { exact: true }).selectOption(second);
}
async function saved(kind, tab = page) {
    await tab.locator(`[data-rsvp-confirmation="${kind}"]`).waitFor();
    const notice = tab.locator('.ee-personal-rsvp [role="status"]');
    assert.equal(await notice.count(), 1);
    await notice.evaluate((element) => {
        if (document.activeElement !== element) throw Error('RSVP confirmation did not receive focus');
    });
}
async function state() {
    const response = await context.request.get(personal, { headers: { Accept: 'application/json' } });
    assert.equal(response.status(), 200);
    const body = await response.json();
    assert.deepEqual(Object.keys(body), ['rsvp']);
    return body.rsvp;
}
async function storyPublication() {
    const owner = await browser.newContext();
    const editor = await owner.newPage();
    try {
        await editor.goto(host.origin + '/login');
        await editor.getByLabel('Email address').fill(host.owner.email);
        await editor.getByLabel('Password', { exact: true }).fill(host.owner.password);
        await editor.getByRole('button', { name: 'Log in', exact: true }).click();
        await editor.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
        const draft = await (await owner.request.get(host.previewUrl + '/draft')).json();
        const story = draft.document.content.sections.find((section) => section.type === 'story');
        story.data.milestones = [
            {
                id: randomUUID(),
                enabled: true,
                title: 'Our first conversation',
                description: 'An ordinary afternoon became a beautiful beginning.',
                when: { precision: 'year', value: 2020 },
            },
            {
                id: randomUUID(),
                enabled: true,
                title: 'Choosing forever',
                description: 'A promise to keep choosing each other.',
                when: { precision: 'year', value: 2025 },
            },
        ];
        const cookie = (await owner.cookies()).find((item) => item.name === 'XSRF-TOKEN');
        const response = await owner.request.put(host.previewUrl + '/draft', {
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) },
            data: { document: draft.document, draft_version: draft.draft_version },
        });
        assert.equal(response.status(), 200);
        await editor.goto(host.previewUrl.replace('/cms-preview', ''));
        await editor.getByRole('button', { name: 'Republish changes', exact: true }).click();
        await editor.getByRole('dialog').getByRole('button', { name: 'Republish', exact: true }).click();
        await editor.getByRole('dialog').waitFor({ state: 'hidden' });
        assert.equal((await owner.request.get(host.previewUrl + '/saved-preview')).status(), 200);
        return story.data.milestones.map((milestone) => 'milestone:' + milestone.id);
    } finally {
        await owner.close();
    }
}

for (const [device, width, height] of [
    ['desktop', 1366, 768],
    ['mobile', 390, 844],
]) {
    run(`DEL05 ${device} complete published Story, guided bird, keyboard RSVP, celebration and saved revisit journey`, async () => {
        await page.setViewportSize({ width, height });
        const markers = await storyPublication();
        await visit();
        assert.equal(await page.locator('.ee-hummingbird').count(), 1);
        await page.evaluate(() => {
            window.__del05Wings = [...document.querySelectorAll('.ee-bird-wing')].map((wing) => wing.getAnimations()[0]);
        });
        if (device === 'mobile') await page.getByRole('button', { name: 'Open wedding menu' }).click();
        await page.locator('.ee-header a[href="#our-story"]').click();
        await page.locator('.ee-timeline-entry').first().scrollIntoViewIfNeeded();
        assert.equal(await page.locator('.ee-timeline-entry').count(), 2);
        assert.deepEqual(
            await page.locator('[data-landing-milestone]').evaluateAll((elements) => elements.map((element) => element.dataset.landingId)),
            markers,
        );
        await page
            .locator('[data-landing-milestone]')
            .first()
            .evaluate((marker) => {
                const nectar = marker.querySelector('[data-nectar-center]').getBoundingClientRect();
                window.scrollTo({ top: nectar.top + scrollY - innerHeight * 0.38, behavior: 'instant' });
            });
        await page.waitForFunction((id) => document.querySelector('.ee-loading-bird').dataset.waypointId === id, markers[0]);
        await page.screenshot({ path: join(artifacts, device + '-story.png') });
        // Mobile header is intentionally above the current scroll position; open it by returning to the top.
        if (device === 'mobile') {
            await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
            await page.getByRole('button', { name: 'Open wedding menu' }).click();
        }
        await page.locator('.ee-header a[href="#rsvp"]').click();
        await choose('attending', 'attending');
        await submit().focus();
        await page.keyboard.press('Enter');
        await saved('attending');
        assert.match(await confirmation().innerText(), /We can't wait to celebrate with you/);
        assert.equal(await confirmation().locator('.ee-rsvp-confirmation-summary').count(), 0);
        const animation = await confirmation().evaluate(
            (element) => element.querySelector('.ee-rsvp-confirmation-botanical').getAnimations()[0]?.animationName,
        );
        assert.equal(animation, 'ee-rsvp-bloom');
        assert.equal(
            await page.evaluate(() =>
                [...document.querySelectorAll('.ee-bird-wing')].every(
                    (wing, index) => wing.getAnimations()[0] === window.__del05Wings[index] && wing.getAnimations()[0].playState === 'running',
                ),
            ),
            true,
        );
        assert.equal(await page.locator('.ee-hummingbird').count(), 1);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await page.waitForTimeout(1100); // Hold the finite botanical motion for the review recording and let focus-induced flight settle.
        const clearance = await page.evaluate(() => {
            const bird = document.querySelector('.ee-loading-bird').getBoundingClientRect();
            return [
                ...document.querySelectorAll('.ee-personal-rsvp button, .ee-personal-rsvp select, .ee-rsvp-confirmation h3, .ee-rsvp-confirmation p'),
            ].every((control) => {
                const box = control.getBoundingClientRect();
                return bird.right <= box.left || bird.left >= box.right || bird.bottom <= box.top || bird.top >= box.bottom;
            });
        });
        assert.equal(clearance, true, 'Settled bird must clear RSVP controls and confirmation text');
        await page.screenshot({ path: join(artifacts, device + '-attending.png') });
        const persisted = await state();
        assert.deepEqual(
            persisted.guests.map((person) => person.status),
            ['attending', 'attending'],
        );
        await page.goto(host.origin + '/w/synthetic-editor-preview');
        await page.goBack();
        await page.getByLabel('Maya Santos', { exact: true }).waitFor();
        assert.equal(
            await confirmation().evaluateAll((elements) =>
                elements.some((element) => element.getAnimations({ subtree: true }).some((animation) => animation.playState === 'running')),
            ),
            false,
        );
        await page.reload();
        await page.getByLabel('Maya Santos', { exact: true }).waitFor();
        assert.equal(await confirmation().count(), 0);
        assert.equal(await page.getByLabel('Nico Santos', { exact: true }).inputValue(), 'attending');
        await submit().click();
        await page.getByRole('status').filter({ hasText: 'No changed responses' }).waitFor();
        assert.equal(await confirmation().count(), 0);
        assert.deepEqual(await state(), persisted);
        console.log('DEL05 ' + device + ' screenshots and motion: ' + artifacts);
    });
}

run('DEL05 partial and mixed saves are neutral; decline is respectful and reduced motion keeps confirmation static', async () => {
    await visit();
    await choose('attending');
    await submit().click();
    await saved('mixed');
    assert.match(await confirmation().innerText(), /Thank you for sharing your plans/);
    assert.equal(await confirmation().locator('.ee-rsvp-confirmation-summary').count(), 0);
    assert.equal(await confirmation().getAttribute('data-rsvp-celebrating'), 'false');
    assert.equal(await confirmation().evaluate((element) => element.getAnimations({ subtree: true }).length), 0);
    await choose(null, 'declined');
    assert.equal(await confirmation().count(), 0);
    await submit().click();
    await saved('mixed');
    assert.match(await confirmation().innerText(), /Thank you for sharing your plans/);
    await page.screenshot({ path: join(artifacts, 'mixed.png') });
    await choose('declined');
    await submit().click();
    await saved('declined');
    assert.match(await confirmation().innerText(), /Thank you for letting us know/);
    assert.equal(await confirmation().evaluate((element) => element.getAnimations({ subtree: true }).length), 0);
    await page.screenshot({ path: join(artifacts, 'declined.png') });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await choose('attending', 'attending');
    await submit().click();
    await saved('attending');
    assert.equal(await confirmation().evaluate((element) => element.getAnimations({ subtree: true }).length), 0);
    assert.equal(await page.locator('.ee-hummingbird').evaluate((element) => element.getAnimations({ subtree: true }).length), 0);
});

run('DEL05 delayed persistence acknowledgement blocks duplicate submit; lost acknowledgement retries do not celebrate again', async () => {
    await visit();
    let release,
        committed,
        requests = 0;
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    const written = new Promise((resolve) => {
        committed = resolve;
    });
    await page.route('**/responses', async (route) => {
        requests++;
        const response = await route.fetch();
        assert.equal((await response.json()).receipt.changed, true);
        committed();
        await gate;
        await route.fulfill({ response });
    });
    await choose('attending', 'attending');
    await submit().click();
    await written;
    assert.equal(await confirmation().count(), 0);
    assert.equal(await page.getByRole('button', { name: 'Saving…', exact: true }).isDisabled(), true);
    await page.locator('form').evaluate((form) => form.requestSubmit());
    assert.equal(requests, 1);
    release();
    await saved('attending');
    await page.unroute('**/responses');
    await choose('declined', 'declined');
    await page.route('**/responses', async (route) => {
        const response = await route.fetch();
        assert.equal(response.status(), 200);
        await route.abort('failed');
    });
    await submit().click();
    await page.getByRole('alert').filter({ hasText: 'could not confirm the save' }).waitFor();
    assert.equal(await confirmation().count(), 0);
    const persisted = await state();
    assert.deepEqual(
        persisted.guests.map((person) => person.status),
        ['declined', 'declined'],
    );
    await page.unroute('**/responses');
    await submit().click();
    await saved('unchanged');
    assert.equal(await confirmation().evaluate((element) => element.getAnimations({ subtree: true }).length), 0);
    assert.deepEqual(await state(), persisted);
    await page.getByRole('button', { name: 'Reload saved choices' }).click();
    await page.getByRole('status').filter({ hasText: 'Saved choices and current access reloaded' }).waitFor();
    assert.equal(await confirmation().count(), 0);
});

run('DEL05 real stale concurrent submissions reject atomically; reload requires reviewing server state and never celebrates', async () => {
    await visit();
    const stale = await context.newPage();
    await visit(stale);
    await choose('attending', 'attending');
    await submit().click();
    await saved('attending');
    const persisted = await state();
    await choose('declined', 'declined', stale);
    await submit(stale).click();
    await stale.getByRole('alert').filter({ hasText: 'another window' }).waitFor();
    assert.equal(await confirmation(stale).count(), 0);
    assert.equal(await submit(stale).isDisabled(), true);
    assert.deepEqual(await state(), persisted);
    await stale.getByRole('button', { name: 'Reload saved choices' }).click();
    await stale.getByRole('status').filter({ hasText: 'current access reloaded' }).waitFor();
    assert.equal(await confirmation(stale).count(), 0);
    assert.equal(await stale.getByLabel('Nico Santos', { exact: true }).inputValue(), 'attending');
});

run('DEL05 rejected, expired, rate-limited, server and malformed acknowledgements never confirm or lose unsaved choices', async () => {
    for (const [status, body, expected] of [
        [409, { kind: 'stale_response' }, 'another window'],
        [419, { kind: 'session' }, 'session expired'],
        [429, { kind: 'rate_limited' }, '7 seconds'],
        [422, { kind: 'validation' }, 'Check your choices'],
        [500, { kind: 'network' }, 'could not confirm'],
        [200, { rsvp: { guests: [] }, receipt: { changed: true } }, 'could not confirm'],
    ]) {
        await visit();
        await page.route('**/responses', (route) =>
            route.fulfill({ status, contentType: 'application/json', headers: { 'Retry-After': '7' }, body: JSON.stringify(body) }),
        );
        await choose('attending');
        await submit().click();
        await page.getByRole('alert').filter({ hasText: expected }).waitFor();
        assert.equal(await confirmation().count(), 0);
        assert.equal(await page.getByLabel('Maya Santos', { exact: true }).inputValue(), 'attending');
        assert.deepEqual(
            (await state()).guests.map((person) => person.status),
            ['pending', 'pending'],
        );
        await page.unroute('**/responses');
    }
});

run('DEL05 saved no-JS state stays private and generic published pages never contain guest data, real forms or confirmation', async () => {
    await visit();
    await choose('attending', 'declined');
    await submit().click();
    await saved('mixed');
    const fallback = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    try {
        const staticPage = await fallback.newPage();
        const response = await staticPage.goto(personal);
        assert.match(response.headers()['cache-control'], /no-store/);
        const html = await staticPage.content();
        assert.match(html, /Maya Santos.*Attending/s);
        assert.match(html, /Nico Santos.*Declining/s);
        assert.match(html, /Enable JavaScript to save or update/);
        assert.doesNotMatch(html, /Lia Santos|Mateo Santos|private-family@example/);
        assert.equal(await staticPage.locator('form, [data-rsvp-confirmation]').count(), 0);
        await staticPage.locator('#rsvp').scrollIntoViewIfNeeded();
        await staticPage.screenshot({ path: join(artifacts, 'no-js-saved.png') });
        await page.goto(host.origin + '/w/synthetic-editor-preview');
        await page.locator('[data-ee-phase="landed"]').waitFor();
        assert.equal(await page.locator('form, [data-rsvp-confirmation]').count(), 0);
        assert.doesNotMatch(await page.content(), /Maya Santos|Nico Santos|Lia Santos|respondedAt|personal-rsvp-data/);
    } finally {
        await fallback.close();
    }
});

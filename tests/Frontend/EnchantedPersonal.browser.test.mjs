import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { after, afterEach, before, beforeEach, test } from 'node:test';

const enabled = Boolean(process.env.QA_CMS_EDITOR_INFO && process.env.QA_PLAYWRIGHT_MODULE && process.env.QA_BROWSER_EXECUTABLE);
let info, browser, owner, visitors, page, guest, overview, accessBase, representativeUrl, independentUrl, lateUrl, artifacts;
const errors = [];
before(async () => {
    if (!enabled) return;
    info = JSON.parse(await readFile(process.env.QA_CMS_EDITOR_INFO, 'utf8'));
    assert.match(info.origin, /^http:\/\/127\.0\.0\.1:\d+$/);
    assert.match(info.root, /rsvp-cms-editor-/);
    assert.equal(info.personal, true);
    assert.equal(info.publishing, true);
    ({ representativeUrl, independentUrl } = info.owner.personal);
    overview = info.previewUrl.replace('/cms-preview', '');
    accessBase = overview + '/invitation-groups/' + info.owner.personal.groupId + '/rsvp-access';
    artifacts = join(info.root, 'qa-del04');
    await mkdir(artifacts, { recursive: true });
    const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
    process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY = '1';
    browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
});
beforeEach(async () => {
    if (!enabled) return;
    errors.length = 0;
    owner = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    visitors = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    for (const context of [owner, visitors])
        context.on('page', (tab) => {
            tab.setDefaultTimeout(15000);
            tab.on('pageerror', (error) => errors.push(error.message.replace(/[a-f0-9]{64}/g, '[redacted]')));
        });
    page = await owner.newPage();
    guest = await visitors.newPage();
    await page.goto(info.origin + '/login');
    await page.getByLabel('Email address').fill(info.owner.email);
    await page.getByLabel('Password', { exact: true }).fill(info.owner.password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(/\/accounts\/\d+\/weddings\/\d+$/);
});
afterEach(async () => {
    if (!enabled) return;
    await owner.close();
    await visitors.close();
    assert.deepEqual(errors, []);
});
after(async () => {
    await browser?.close();
});
const run = (name, fn) => test(name, { skip: !enabled, timeout: 90000 }, fn);
async function visit(tab = guest, url = representativeUrl) {
    await tab.goto(url);
    await tab.locator('[data-ee-phase="landed"]').waitFor();
    await tab.getByRole('heading', { name: 'Your RSVP', exact: true }).waitFor();
}
async function saved(tab = guest) {
    await tab
        .getByRole('status')
        .filter({ hasText: /Your responses have been saved|Saved choices and current access reloaded/ })
        .waitFor();
}
async function save(tab = guest) {
    await tab.getByRole('button', { name: 'Save responses', exact: true }).click();
    await saved(tab);
}
async function state(url = representativeUrl) {
    const response = await visitors.request.get(url, { headers: { Accept: 'application/json' } });
    assert.equal(response.status(), 200);
    return (await response.json()).rsvp;
}
async function ownerPost(url, data) {
    const cookie = (await owner.cookies()).find((item) => item.name === 'XSRF-TOKEN');
    return owner.request.post(url, { headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) }, data });
}
async function editorProps() {
    await page.goto(info.previewUrl);
    return page.locator('#app').evaluate((el) => JSON.parse(el.dataset.page).props);
}
async function publish() {
    await page.goto(overview);
    await page.getByRole('button', { name: /^(Publish invitation|Republish changes)$/ }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /^(Publish now|Republish)$/ })
        .click();
    await page.getByRole('heading', { name: 'Published', exact: true }).waitFor();
}
async function access(name) {
    await page.goto(overview + '/guests');
    await page.getByRole('button', { name: 'Actions for Santos Family', exact: true }).click();
    await page.getByRole('menuitem', { name: 'RSVP Access', exact: true }).click();
    await page.getByRole('button', { name: 'Manage RSVP access for ' + name, exact: true }).click();
}
async function close() {
    await page.keyboard.press('Escape');
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
}
async function changeRepresentative(remove = false) {
    await access('Nico Santos');
    await page.getByRole('button', { name: remove ? 'Change or remove representative' : 'Assign a representative', exact: true }).click();
    const select = page.getByLabel('Representative', { exact: true });
    const value = remove ? '' : await select.locator('option').filter({ hasText: 'Maya Santos' }).getAttribute('value');
    await select.selectOption(value);
    await page.getByRole('button', { name: 'Review change', exact: true }).click();
    await page.getByRole('button', { name: 'Confirm representation change', exact: true }).click();
    await page.getByRole('dialog').getByRole('status').waitFor();
    await close();
}

run('DEL04 personal and generic HTML/JSON separate authorized guest state, metadata and anonymous media', async () => {
    const initial = await visitors.request.get(representativeUrl);
    assert.equal(initial.status(), 200);
    assert.equal(initial.headers()['cache-control'], 'no-store, private');
    assert.equal(initial.headers()['referrer-policy'], 'no-referrer');
    assert.equal(initial.headers()['x-robots-tag'], 'noindex, nofollow, noarchive');
    assert.equal(initial.headers()['set-cookie'], undefined);
    const html = await initial.text();
    assert.doesNotMatch(html, /Lia Santos|Mateo Santos|private-family@example.test/);
    assert.ok(!html.includes(new URL(representativeUrl).pathname.split('/').at(-1)));
    await visit();
    assert.equal(await guest.locator('select').count(), 2);
    await guest.getByText("You're invited, Maya Santos!", { exact: true }).waitFor();
    assert.equal(await guest.title(), 'Wedding invitation');
    assert.deepEqual(await guest.locator('meta[property="og:url"]').getAttribute('content'), info.origin + '/w/synthetic-editor-preview');
    const display = await guest.locator('#enchanted-data').textContent();
    assert.doesNotMatch(display, /Maya Santos|Nico Santos|access_version|respondedAt/);
    const other = await visitors.newPage();
    await visit(other, independentUrl);
    assert.equal(await other.locator('select').count(), 1);
    assert.doesNotMatch(await other.locator('body').innerText(), /Maya Santos|Nico Santos|Mateo Santos/);
    const generic = await visitors.newPage();
    await generic.goto(info.origin + '/w/synthetic-editor-preview');
    assert.equal(await generic.locator('form, select, #personal-rsvp-data').count(), 0);
    assert.doesNotMatch(await generic.content(), /Maya Santos|Nico Santos|Lia Santos|Mateo Santos/);
    await guest.bringToFront();
    for (const width of [1440, 390]) {
        await guest.setViewportSize({ width, height: 1000 });
        await guest.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        assert.ok(await guest.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await guest.screenshot({ path: join(artifacts, 'personal-' + width + '.png'), fullPage: true, animations: 'disabled', timeout: 45000 });
    }
});

run('DEL04 partial responses become successful only after server acknowledgment and survive refresh', async () => {
    await visit();
    assert.equal(await guest.getByLabel('Maya Santos', { exact: true }).inputValue(), 'pending');
    const initial = await state();
    const noCsrf = await visitors.request.post(representativeUrl + '/responses', {
        data: { access_version: initial.access_version, responses: [{ guest: initial.guests[0].reference, version: 0, status: 'attending' }] },
    });
    assert.equal(noCsrf.status(), 419);
    let release, committed;
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    const written = new Promise((resolve) => {
        committed = resolve;
    });
    await guest.route('**/responses', async (route) => {
        const response = await route.fetch();
        committed();
        await gate;
        await route.fulfill({ response });
    });
    await guest.getByLabel('Maya Santos', { exact: true }).selectOption('attending');
    await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
    await written;
    assert.equal(await guest.getByRole('button', { name: 'Saving…', exact: true }).isDisabled(), true);
    assert.equal(await guest.getByText('Your responses have been saved.', { exact: false }).count(), 0);
    release();
    await saved();
    await guest.unroute('**/responses');
    assert.deepEqual(
        (await state()).guests.map((person) => person.status),
        ['attending', 'pending'],
    );
    await guest.reload();
    await guest.getByLabel('Maya Santos', { exact: true }).waitFor();
    assert.equal(await guest.getByLabel('Maya Santos', { exact: true }).inputValue(), 'attending');
    assert.equal(await guest.getByLabel('Nico Santos', { exact: true }).inputValue(), 'pending');
    await guest.getByLabel('Nico Santos', { exact: true }).focus();
    await guest.keyboard.press('ArrowDown');
    await guest.getByRole('button', { name: 'Save responses', exact: true }).focus();
    assert.ok(await guest.getByRole('button', { name: 'Save responses', exact: true }).evaluate((el) => el === document.activeElement));
});

run('DEL04 stale representative submissions cannot partially change dependents or other independent guests', async () => {
    await visit();
    const stale = await visitors.newPage();
    await visit(stale);
    for (const status of ['declined', 'attending']) {
        await guest.getByLabel('Maya Santos', { exact: true }).selectOption(status);
        await save();
    }
    await stale.getByLabel('Maya Santos', { exact: true }).selectOption('declined');
    await stale.getByLabel('Nico Santos', { exact: true }).selectOption('attending');
    await stale.getByRole('button', { name: 'Save responses', exact: true }).click();
    await stale.getByRole('alert').filter({ hasText: 'changed in another window' }).waitFor();
    assert.equal(await stale.getByRole('button', { name: 'Save responses', exact: true }).isDisabled(), true);
    assert.deepEqual(
        (await state()).guests.map((person) => person.status),
        ['attending', 'pending'],
    );
    await stale.getByRole('button', { name: 'Reload saved choices', exact: true }).click();
    await saved(stale);
    assert.equal(await stale.getByLabel('Nico Santos', { exact: true }).inputValue(), 'pending');
    const other = await state(independentUrl),
        current = await state();
    const csrf = await (await visitors.request.get(info.origin + '/invitation-rsvp/csrf-token')).json();
    const forged = await visitors.request.post(representativeUrl + '/responses', {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken },
        data: {
            access_version: current.access_version,
            responses: [
                { guest: current.guests[1].reference, version: current.guests[1].version, status: 'declined' },
                { guest: other.guests[0].reference, version: other.guests[0].version, status: 'attending' },
            ],
        },
    });
    assert.equal(forged.status(), 422);
    assert.equal((await state()).guests[1].status, 'pending');
    const independent = await visitors.newPage();
    await visit(independent, independentUrl);
    await independent.locator('select').selectOption('declined');
    await save(independent);
    assert.equal((await state()).guests.length, 2);
});

run('DEL04 existing owner UI recovers unchanged credentials and generates independent access after publication', async () => {
    await access('Maya Santos');
    await page.getByRole('button', { name: 'Recover link', exact: true }).click();
    await page.getByRole('dialog').getByRole('status').waitFor();
    assert.equal(await page.getByLabel('Personal invitation URL', { exact: true }).inputValue(), representativeUrl);
    await close();
    await access('Mateo Santos');
    await page.getByRole('button', { name: 'Generate link', exact: true }).click();
    await page.getByLabel('Personal invitation URL', { exact: true }).waitFor();
    lateUrl = await page.getByLabel('Personal invitation URL', { exact: true }).inputValue();
    await page.screenshot({ path: join(artifacts, 'owner-access-1440.png'), fullPage: true });
    await close();
    await visit(guest, lateUrl);
    assert.equal(await guest.locator('select').count(), 1);
    assert.doesNotMatch(await guest.locator('body').innerText(), /Maya Santos|Nico Santos|Lia Santos/);
    assert.equal(await page.getByRole('button', { name: /Withdraw invitation/ }).count(), 0);
});

run('DEL04 changed representation denies old access and reload replaces the permitted guest list', async () => {
    await visit();
    await changeRepresentative(true);
    await guest.getByLabel('Nico Santos', { exact: true }).selectOption('declined');
    await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
    await guest.getByRole('alert').filter({ hasText: 'Who this link can answer for has changed' }).waitFor();
    await guest.getByRole('button', { name: 'Reload saved choices', exact: true }).click();
    await saved();
    assert.equal(await guest.locator('select').count(), 1);
    assert.doesNotMatch(await guest.locator('body').innerText(), /Nico Santos/);
    await changeRepresentative(false);
    await guest.getByRole('button', { name: 'Reload saved choices', exact: true }).click();
    await saved();
    assert.equal(await guest.locator('select').count(), 2);
});

run('DEL04 explicit owner rotation and revocation deny stale links and clear the unavailable personal UI', async () => {
    assert.ok(lateUrl);
    await visit(guest, lateUrl);
    await access('Mateo Santos');
    await page.getByRole('button', { name: 'More link actions', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Regenerate link', exact: true }).click();
    await page.getByRole('button', { name: 'Regenerate this link', exact: true }).click();
    await page.getByRole('dialog').getByRole('status').waitFor();
    const rotated = await page.getByLabel('Personal invitation URL', { exact: true }).inputValue();
    await close();
    assert.notEqual(rotated, lateUrl);
    assert.equal((await visitors.request.get(lateUrl)).status(), 404);
    await visit(guest, rotated);
    await access('Mateo Santos');
    await page.getByRole('button', { name: 'More link actions', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Revoke link', exact: true }).click();
    await page.getByRole('button', { name: 'Revoke this link', exact: true }).click();
    await page.getByRole('dialog').getByRole('status').waitFor();
    await close();
    await guest.locator('select').selectOption('attending');
    await guest.getByRole('button', { name: 'Save responses', exact: true }).click();
    await guest.getByRole('alert').filter({ hasText: 'This invitation is unavailable' }).waitFor();
    assert.doesNotMatch(await guest.locator('body').innerText(), /Mateo Santos|Alex|Jamie/);
    assert.equal((await visitors.request.get(rotated, { headers: { Accept: 'application/json' } })).status(), 404);
});

run('DEL04 saved edits remain private until republish and hidden information retains only the authorized RSVP form', async () => {
    const current = await editorProps();
    current.initialDocument.content.sections.find((section) => section.type === 'story').data.paragraphs = ['Saved DEL04 republished story'];
    const savedDraft = await owner.request.put(info.origin + current.endpoints.draft, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': current.csrfToken },
        data: { document: current.initialDocument, draft_version: current.draftVersion },
    });
    assert.equal(savedDraft.status(), 200);
    await visit();
    assert.equal(await guest.getByText('Saved DEL04 republished story', { exact: true }).count(), 0);
    await publish();
    await guest.reload();
    await guest.getByText('Saved DEL04 republished story', { exact: true }).waitFor();
    assert.equal((await state()).guests[0].status, 'attending');
    const latest = await editorProps();
    const section = latest.initialDocument.content.sections.find((section) => section.type === 'rsvp_information');
    section.enabled = false;
    section.data.message = 'Hidden RSVP instructions sentinel';
    assert.equal(
        (
            await owner.request.put(info.origin + latest.endpoints.draft, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': latest.csrfToken },
                data: { document: latest.initialDocument, draft_version: latest.draftVersion },
            })
        ).status(),
        200,
    );
    await publish();
    await guest.reload();
    await guest.locator('#personal-rsvp form').waitFor();
    assert.doesNotMatch(await guest.content(), /Hidden RSVP instructions sentinel/);
    assert.equal(await guest.locator('[data-landing-id="section:personal-rsvp"]').count(), 1);
    const generic = await visitors.newPage();
    await generic.goto(info.origin + '/w/synthetic-editor-preview');
    assert.equal(await generic.locator('#rsvp, #personal-rsvp, form').count(), 0);
});

run('DEL04 no-JS choices, reduced-motion loader, mobile keyboard menu and unpublication keep safe availability', async () => {
    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    try {
        const fallback = await noJs.newPage();
        await fallback.goto(representativeUrl);
        await fallback.getByRole('heading', { name: 'Your RSVP', exact: true }).waitFor();
        await fallback.getByText('Enable JavaScript to save or update your responses.', { exact: false }).waitFor();
        assert.equal(await fallback.locator('form, select').count(), 0);
        assert.doesNotMatch(await fallback.locator('body').innerText(), /Lia Santos|Mateo Santos|Hidden RSVP instructions sentinel/);
        await fallback.screenshot({ path: join(artifacts, 'personal-no-js-390.png'), fullPage: true });
    } finally {
        await noJs.close();
    }
    await visit();
    assert.equal(await guest.locator('.ee-hummingbird').evaluate((el) => getComputedStyle(el).animationName), 'none');
    await guest.getByRole('button', { name: 'Open wedding menu', exact: true }).focus();
    await guest.keyboard.press('Enter');
    await guest.getByRole('navigation', { name: 'Wedding sections' }).getByRole('link', { name: 'RSVP', exact: true }).click();
    assert.ok(guest.url().endsWith('#personal-rsvp'));
    assert.equal(await guest.locator('#personal-rsvp').evaluate((el) => el === document.activeElement), true);
    await page.goto(overview);
    await page.getByRole('button', { name: 'Website actions', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Unpublish invitation', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Unpublish invitation', exact: true }).click();
    await page.getByRole('heading', { name: 'Draft', exact: true }).waitFor();
    assert.equal((await visitors.request.get(representativeUrl)).status(), 404);
    assert.equal((await visitors.request.get(info.origin + '/w/synthetic-editor-preview')).status(), 404);
    await guest.getByRole('button', { name: 'Reload saved choices', exact: true }).click();
    await guest.getByRole('alert').filter({ hasText: 'This invitation is unavailable' }).waitFor();
    assert.doesNotMatch(await guest.locator('body').innerText(), /Maya Santos|Nico Santos/);
    await publish();
    await visit();
    assert.equal((await state()).guests[0].status, 'attending');
});

run('DEL04 per-credential throttling remains enforced without blocking a different authorized link', async () => {
    const access = await owner.request.get(accessBase, { headers: { Accept: 'application/json' } });
    const member = (await access.json()).group.members.find((item) => item.guest.name === 'Mateo Santos');
    const freshLink = await ownerPost(accessBase + '/guests/' + member.guest.id + '/credential/regenerate', {
        expectedVersion: member.link_version,
        expectedAccessVersion: member.access_version,
    });
    assert.equal(freshLink.status(), 200);
    const limitedUrl = (await freshLink.json()).url;
    const current = await state(limitedUrl),
        csrf = await (await visitors.request.get(info.origin + '/invitation-rsvp/csrf-token')).json();
    const options = {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken },
        data: {
            access_version: current.access_version,
            responses: [{ guest: current.guests[0].reference, version: current.guests[0].version, status: 'declined' }],
        },
    };
    for (let index = 0; index < 20; index++) assert.equal((await visitors.request.post(limitedUrl + '/responses', options)).status(), 200);
    const limited = await visitors.request.post(limitedUrl + '/responses', options);
    assert.equal(limited.status(), 429);
    assert.ok(Number(limited.headers()['retry-after']) > 0);
    assert.equal((await visitors.request.get(representativeUrl, { headers: { Accept: 'application/json' } })).status(), 200);
});

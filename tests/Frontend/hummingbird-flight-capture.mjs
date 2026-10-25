import { mkdir, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';

// Read-only owner review captures. Reuses installed browser tooling and never edits CMS/RSVP data.
const { chromium } = await import(process.env.QA_PLAYWRIGHT_MODULE);
const folder = resolve(process.env.QA_ENCHANTED_ARTIFACTS);
await mkdir(folder, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.QA_BROWSER_EXECUTABLE, headless: true });
const phase = process.env.QA_CAPTURE_PHASE || 'after';
const evidence = [];
try {
    for (const [device, width, height] of [
        ['desktop', 1366, 768],
        ['mobile', 390, 844],
    ]) {
        for (const [scene, url] of [
            ['published', process.env.QA_PUBLISHED_URL],
            ['story', process.env.QA_STORY_URL],
        ]) {
            const context = await browser.newContext({
                viewport: { width, height },
                reducedMotion: 'no-preference',
                recordVideo: { dir: folder, size: { width, height } },
            });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.goto(url);
            await page.locator('[data-ee-phase="landed"]').waitFor();
            await page.evaluate(() => {
                window.__flightSamples = [];
                window.__recordFlight = true;
                const sample = () => {
                    if (!window.__recordFlight) return;
                    const bird = document.querySelector('.ee-loading-bird');
                    const box = bird.getBoundingClientRect();
                    const matrix = new DOMMatrix(getComputedStyle(bird).transform);
                    window.__flightSamples.push({
                        time: performance.now(),
                        scroll: scrollY,
                        x: box.x,
                        y: box.y,
                        scale: Math.hypot(matrix.a, matrix.b),
                        waypoint: bird.dataset.waypointId,
                        state: bird.dataset.flightState,
                    });
                    requestAnimationFrame(sample);
                };
                requestAnimationFrame(sample);
            });
            await page.waitForTimeout(1700);
            await page.screenshot({ path: join(folder, `${phase}-${scene}-${device}-hero.png`) });
            const destinations =
                scene === 'story'
                    ? ['[data-landing-milestone]', '[data-landing-milestone]:last-of-type']
                    : ['[data-landing-id="section:our-story"]', '[data-landing-id="section:closing"]'];
            for (const selector of destinations) {
                const marker = page.locator(selector).first();
                if (!(await marker.count())) continue;
                const goal = await marker.evaluate((element) => element.getBoundingClientRect().top + scrollY - innerHeight * 0.38);
                await page.evaluate(async (goal) => {
                    const start = scrollY,
                        began = performance.now();
                    await new Promise((done) => {
                        const scroll = (now) => {
                            const p = Math.min(1, (now - began) / 2000);
                            scrollTo({ top: start + (goal - start) * (p * p * (3 - 2 * p)), behavior: 'instant' });
                            if (p < 1) requestAnimationFrame(scroll);
                            else done();
                        };
                        requestAnimationFrame(scroll);
                    });
                }, goal);
                await page.waitForTimeout(1600);
            }
            await page.screenshot({ path: join(folder, `${phase}-${scene}-${device}-stop.png`) });
            await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
            await page.waitForTimeout(1300);
            const samples = await page.evaluate(() => {
                window.__recordFlight = false;
                return window.__flightSamples;
            });
            const video = page.video();
            await context.close();
            await video.saveAs(join(folder, `${phase}-${scene}-${device}.webm`));
            evidence.push({ phase, scene, device, width, height, errors, samples });
        }
    }
} finally {
    await browser.close();
}
await writeFile(join(folder, `${phase}-samples.json`), JSON.stringify(evidence, null, 2));
console.log(
    JSON.stringify(
        evidence.map(({ samples, ...item }) => ({ ...item, frames: samples.length, peakScale: Math.max(...samples.map((sample) => sample.scale)) })),
        null,
        2,
    ),
);

import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readFile, realpath, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { retainedPreviewEnvironment } from './cms-editor-preview.mjs';

async function retained(run) {
    const root = await realpath(await mkdtemp(join(tmpdir(), 'rsvp-cms-editor-')));
    try {
        await mkdir(join(root, 'ini'));
        await mkdir(join(root, 'storage'));
        await writeFile(join(root, 'ini/testing.ini'), '');
        await writeFile(join(root, 'database.sqlite'), 'retained database sentinel');
        const info = {
            root,
            origin: 'http://127.0.0.1:54900',
            publishing: true,
            personal: true,
            owner: { path: '/accounts/1/weddings/1/cms-preview' },
        };
        const infoFile = join(root, 'preview.json');
        await writeFile(infoFile, JSON.stringify(info));
        await run({ root, info, infoFile });
    } finally {
        await rm(root, { recursive: true, force: true });
    }
}

test('resume always uses port 8000 and isolates inherited MySQL, storage, cache and live assets without changing retained bytes', async () => {
    await retained(async ({ root, infoFile }) => {
        const original = await readFile(join(root, 'database.sqlite'), 'utf8');
        const result = await retainedPreviewEnvironment(
            infoFile,
            { liveAssets: true },
            {
                DB_CONNECTION: 'mysql',
                DB_DATABASE: 'valuable',
                DB_URL: 'mysql://private',
                DATABASE_URL: 'mysql://private',
                APP_CONFIG_CACHE: 'bootstrap/cache/config.php',
                LARAVEL_STORAGE_PATH: 'storage',
                SESSION_PATH: 'bad',
            },
        );
        assert.equal(result.origin, 'http://127.0.0.1:8000');
        assert.equal(result.env.DB_CONNECTION, 'sqlite');
        assert.equal(result.env.DB_DATABASE, join(root, 'database.sqlite'));
        assert.equal(result.env.DB_URL, '');
        assert.equal(result.env.DATABASE_URL, '');
        assert.equal(result.env.APP_CONFIG_CACHE, join(root, 'config.php'));
        assert.equal(result.env.LARAVEL_STORAGE_PATH, join(root, 'storage'));
        assert.equal(result.env.SESSION_PATH, '/');
        assert.equal(result.env.CMS_PREVIEW_LIVE_ASSETS, '1');
        assert.equal(result.retainedKey, false);
        assert.equal(await readFile(join(root, 'database.sqlite'), 'utf8'), original);
    });
});

test('missing retained database fails instead of initializing a replacement', async () => {
    await retained(async ({ root, infoFile }) => {
        await rm(join(root, 'database.sqlite'));
        await assert.rejects(retainedPreviewEnvironment(infoFile), { code: 'ENOENT' });
        await assert.rejects(readFile(join(root, 'database.sqlite')), { code: 'ENOENT' });
    });
});

test('manifest outside the isolated directory and nonloopback origins are rejected', async () => {
    await retained(async ({ root, infoFile, info }) => {
        const alternate = join(root, 'alternate.json');
        await writeFile(alternate, JSON.stringify(info));
        await assert.rejects(retainedPreviewEnvironment(alternate), /manifest must belong/);
        await writeFile(infoFile, JSON.stringify({ ...info, origin: 'https://example.test' }));
        await assert.rejects(retainedPreviewEnvironment(infoFile), /loopback/);
    });
});

test('resuming preserves a retained encryption key and refuses a malformed replacement', async () => {
    await retained(async ({ root, infoFile }) => {
        const appKey = 'base64:' + Buffer.alloc(32, 7).toString('base64');
        const runtime = join(root, 'runtime.json');
        await writeFile(runtime, JSON.stringify({ appKey, sessionCookie: 'cms_editor_existing' }));
        const first = await retainedPreviewEnvironment(infoFile, {}, { APP_KEY: 'wrong' });
        const second = await retainedPreviewEnvironment(infoFile);
        assert.equal(first.env.APP_KEY, appKey);
        assert.equal(second.env.APP_KEY, appKey);
        assert.equal(first.retainedKey, true);
        assert.equal(first.env.CMS_PREVIEW_LIVE_ASSETS, '0');
        await writeFile(runtime, JSON.stringify({ appKey: 'broken', sessionCookie: 'cms_editor_existing' }));
        await assert.rejects(retainedPreviewEnvironment(infoFile), /Refusing to replace its key/);
        assert.match(await readFile(runtime, 'utf8'), /broken/);
    });
});

test('personal preview cannot enable credentials without its existing publishing capability', async () => {
    await retained(async ({ infoFile, info }) => {
        await writeFile(infoFile, JSON.stringify({ ...info, publishing: false }));
        await assert.rejects(retainedPreviewEnvironment(infoFile), /capabilities/);
    });
});

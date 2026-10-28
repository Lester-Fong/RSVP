import { execFileSync, spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, mkdtemp, readFile, realpath, stat, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

function stopOwnedProcess(child) {
    if (!child?.pid || child.exitCode !== null) return;
    if (process.platform === 'win32') {
        try {
            execFileSync('taskkill.exe', ['/PID', String(child.pid), '/T', '/F'], { windowsHide: true, stdio: 'ignore' });
            return;
        } catch {
            /* A concurrent child exit is harmless; retain the direct-child fallback. */
        }
    }
    child.kill();
}

/** Opt-in loopback fixture: actual Laravel sessions/CSRF/uploads and disposable SQLite, never .env editing. */
export async function createCmsEditorPreview({
    publishing = false,
    personal = false,
    withdrawal = false,
    serverEnvironment = 'local',
    stagingAuthorized = false,
} = {}) {
    if (personal && !publishing) throw Error('Personal Enchanted QA requires an explicitly publication-enabled disposable target.');
    if (withdrawal && (!personal || !publishing)) throw Error('Invitation withdrawal QA requires the isolated personal-RSVP publication fixture.');
    if (!['local', 'staging'].includes(serverEnvironment)) throw Error('Disposable CMS browser host supports only local or staging runtime.');
    const repository = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
    const root = await mkdtemp(join(tmpdir(), 'rsvp-cms-editor-'));
    const database = join(root, 'database.sqlite'),
        storage = join(root, 'storage'),
        scan = join(root, 'ini');
    for (const dir of ['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'])
        await mkdir(join(storage, dir), { recursive: true });
    await mkdir(scan);
    await writeFile(database, '');
    await writeFile(
        join(scan, 'testing.ini'),
        [
            'extension=' + resolve('C:/php/ext/php_pdo_sqlite.dll').replaceAll('\\', '/'),
            'extension=' + resolve('C:/php/ext/php_sqlite3.dll').replaceAll('\\', '/'),
            'extension=' + resolve('C:/php/ext/php_exif.dll').replaceAll('\\', '/'),
            'memory_limit=256M',
            'upload_max_filesize=5M',
            'post_max_size=8M',
            'log_errors=On',
            'error_log=' + JSON.stringify(join(root, 'php-errors.log').replaceAll('\\', '/')),
        ].join('\n'),
    );
    const port = await new Promise((done, reject) => {
        const probe = createServer();
        probe.once('error', reject);
        probe.listen(0, '127.0.0.1', () => {
            const value = probe.address().port;
            probe.close((error) => (error ? reject(error) : done(value)));
        });
    });
    const origin = 'http://127.0.0.1:' + port,
        password = randomBytes(20).toString('hex') + 'Aa1!';
    const env = {
        ...process.env,
        PHP_CLI_SERVER_WORKERS: '1',
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        APP_KEY: 'base64:' + randomBytes(32).toString('base64'),
        APP_URL: origin,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        DB_URL: '',
        DATABASE_URL: '',
        CACHE_STORE: 'file',
        SESSION_DRIVER: 'file',
        SESSION_COOKIE: 'cms_editor_' + randomBytes(6).toString('hex'),
        SESSION_PATH: '/',
        SESSION_DOMAIN: '',
        SESSION_SECURE_COOKIE: 'false',
        MAIL_MAILER: 'array',
        QUEUE_CONNECTION: 'sync',
        PHP_INI_SCAN_DIR: scan,
        LARAVEL_STORAGE_PATH: storage,
        APP_CONFIG_CACHE: join(root, 'config.php'),
        APP_ROUTES_CACHE: join(root, 'routes.php'),
        APP_EVENTS_CACHE: join(root, 'events.php'),
        CMS_EDITOR_PREVIEW_ENABLED: 'true',
        CMS_V2_PUBLISHING_ENABLED: publishing ? 'true' : 'false',
        CMS_PREVIEW_PERSONAL: personal ? '1' : '0',
        CMS_PREVIEW_DISPOSABLE_ROOT: root,
        CMS_PREVIEW_PASSWORD: password,
        RSVP_MODE: 'personal',
        RSVP_WITHDRAWAL_ENABLED: withdrawal ? 'true' : 'false',
    };
    const php = (args) => execFileSync('php', args, { cwd: repository, env, encoding: 'utf8', windowsHide: true });
    await writeFile(join(root, 'runtime.json'), JSON.stringify({ appKey: env.APP_KEY, sessionCookie: env.SESSION_COOKIE }), { mode: 0o600 });
    const verified = JSON.parse(php(['tests/Fixtures/cms-editor-seed.php', '--verify']));
    if (verified.driver !== 'sqlite' || !verified.isolated) throw Error('Disposable database verification failed.');
    php(['artisan', 'migrate', '--force', '--no-interaction']);
    const owner = { ...JSON.parse(php(['tests/Fixtures/cms-editor-seed.php'])), password };
    const child = spawn(
        'php',
        ['-S', '127.0.0.1:' + port, '-t', join(repository, 'public'), join(repository, 'tests/Fixtures/cms-editor-router.php')],
        {
            cwd: repository,
            env: {
                ...env,
                APP_ENV: serverEnvironment,
                CMS_V2_STAGING_AUTHORIZED: serverEnvironment === 'staging' && stagingAuthorized ? 'true' : 'false',
            },
            stdio: ['ignore', 'pipe', 'pipe'],
            windowsHide: true,
        },
    );
    const log = [];
    child.stdout.on('data', (v) => log.push(v.toString()));
    child.stderr.on('data', (v) => log.push(v.toString()));
    const close = async () => {
        child.kill();
        await writeFile(join(root, 'http.log'), log.join(''));
    };
    try {
        let ready = false,
            last = 'no response';
        for (let i = 0; i < 40; i++) {
            if (child.exitCode !== null) throw Error('Isolated PHP host exited.');
            try {
                const response = await fetch(origin + '/up');
                if (response.ok) {
                    ready = true;
                    break;
                }
                last = response.status + ' ' + (await response.text()).slice(0, 700);
                if (response.status === 503) break;
            } catch {}
            await new Promise((r) => setTimeout(r, 100));
        }
        if (!ready) throw Error('Isolated PHP host did not become ready: ' + last + ' (see ' + join(root, 'http.log') + ').');
    } catch (error) {
        await close();
        throw error;
    }
    const info = { origin, root, owner, previewUrl: origin + owner.path, publishing, personal };
    await writeFile(join(root, 'preview.json'), JSON.stringify(info, null, 2));
    return { ...info, close };
}

/** Resume retained synthetic data; this path never creates a database, migrates or seeds. */
export async function retainedPreviewEnvironment(infoFile, { liveAssets = false } = {}, inherited = process.env) {
    const info = JSON.parse(await readFile(infoFile, 'utf8'));
    const root = await realpath(info.root);
    const temporaryRoot = await realpath(tmpdir());
    if (dirname(root) !== temporaryRoot || !/^rsvp-cms-editor-[a-z\d]+$/i.test(root.split(/[\\/]/).at(-1)))
        throw Error('Only an existing isolated CMS preview directory may be resumed.');
    if ((await realpath(infoFile)) !== join(root, 'preview.json'))
        throw Error('The retained preview manifest must belong to its isolated directory.');
    for (const relative of ['database.sqlite', 'storage', 'ini/testing.ini']) {
        const path = await realpath(join(root, relative));
        if (!path.startsWith(root + (process.platform === 'win32' ? '\\' : '/')))
            throw Error('Retained preview paths must stay inside their isolated directory.');
    }
    if (!(await stat(join(root, 'database.sqlite'))).isFile()) throw Error('The retained SQLite database is missing. No database will be created.');
    const previousOrigin = new URL(info.origin);
    if (previousOrigin.protocol !== 'http:' || previousOrigin.hostname !== '127.0.0.1' || previousOrigin.username || previousOrigin.password)
        throw Error('The retained preview must be a loopback HTTP environment.');
    if (typeof info.publishing !== 'boolean' || typeof info.personal !== 'boolean' || (info.personal && !info.publishing))
        throw Error('Invalid retained preview capabilities.');
    let runtime;
    try {
        runtime = JSON.parse(await readFile(join(root, 'runtime.json'), 'utf8'));
        if (!/^base64:[a-z\d+/]{43}=$/i.test(runtime.appKey) || !/^cms_editor_[a-z\d_]+$/i.test(runtime.sessionCookie))
            throw Error('Invalid retained preview runtime. Refusing to replace its key.');
    } catch (error) {
        if (error.code !== 'ENOENT') throw error;
    }
    const origin = 'http://127.0.0.1:8000';
    const env = {
        ...inherited,
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        APP_URL: origin,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: join(root, 'database.sqlite'),
        DB_URL: '',
        DATABASE_URL: '',
        CACHE_STORE: 'file',
        SESSION_DRIVER: 'file',
        SESSION_COOKIE: runtime?.sessionCookie || 'cms_editor_retained_manual',
        SESSION_PATH: '/',
        SESSION_DOMAIN: '',
        SESSION_SECURE_COOKIE: 'false',
        MAIL_MAILER: 'array',
        QUEUE_CONNECTION: 'sync',
        PHP_CLI_SERVER_WORKERS: '1',
        PHP_INI_SCAN_DIR: join(root, 'ini'),
        LARAVEL_STORAGE_PATH: join(root, 'storage'),
        APP_CONFIG_CACHE: join(root, 'config.php'),
        APP_ROUTES_CACHE: join(root, 'routes.php'),
        APP_EVENTS_CACHE: join(root, 'events.php'),
        CMS_EDITOR_PREVIEW_ENABLED: 'true',
        CMS_V2_PUBLISHING_ENABLED: info.publishing ? 'true' : 'false',
        CMS_PREVIEW_PERSONAL: info.personal ? '1' : '0',
        CMS_PREVIEW_DISPOSABLE_ROOT: root,
        RSVP_MODE: 'personal',
        CMS_PREVIEW_LIVE_ASSETS: liveAssets ? '1' : '0',
        ...(runtime ? { APP_KEY: runtime.appKey } : {}),
    };
    return { info, root, origin, env, retainedKey: Boolean(runtime) };
}

export async function resumeCmsEditorPreview(infoFile, options) {
    const repository = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
    const { info, root, origin, env, retainedKey } = await retainedPreviewEnvironment(infoFile, options);
    // Verify the actual resolved Laravel config before starting HTTP; --verify exits before all seed/database work.
    const verified = JSON.parse(
        execFileSync('php', ['tests/Fixtures/cms-editor-seed.php', '--verify'], {
            cwd: repository,
            env,
            encoding: 'utf8',
            windowsHide: true,
        }),
    );
    if (verified.driver !== 'sqlite' || !verified.isolated) throw Error('Retained preview isolation failed.');
    const probe = createServer();
    await new Promise((done, reject) => {
        probe.once('error', reject);
        probe.listen(8000, '127.0.0.1', () => probe.close(done));
    });
    const child = spawn('php', ['-S', '127.0.0.1:8000', '-t', join(repository, 'public'), join(repository, 'tests/Fixtures/cms-editor-router.php')], {
        cwd: repository,
        env,
        stdio: ['ignore', 'pipe', 'pipe'],
        windowsHide: true,
    });
    const log = [];
    child.stdout.on('data', (value) => log.push(value.toString()));
    child.stderr.on('data', (value) => log.push(value.toString()));
    let spawnError;
    const closed = new Promise((done) => child.once('exit', (code, signal) => done({ code, signal })));
    child.on('error', (error) => {
        spawnError = error;
    });
    let closing;
    const close = () =>
        (closing ??= (async () => {
            stopOwnedProcess(child);
            await writeFile(join(root, 'resumed-http.log'), log.join(''));
        })());
    try {
        let ready = false;
        for (let attempt = 0; attempt < 50; attempt++) {
            if (spawnError) throw spawnError;
            if (child.exitCode !== null) throw Error('Retained preview HTTP server exited.');
            try {
                if ((await fetch(origin + '/up')).ok) {
                    ready = true;
                    break;
                }
            } catch {}
            await new Promise((done) => setTimeout(done, 100));
        }
        if (!ready) throw Error('Port-8000 preview did not become ready.');
        const owner = { ...info.owner };
        if (owner.personal)
            owner.personal = Object.fromEntries(
                Object.entries(owner.personal).map(([key, value]) => [
                    key,
                    typeof value === 'string' && key.endsWith('Url') ? origin + new URL(value, info.origin).pathname : value,
                ]),
            );
        const active = { ...info, origin, root, owner, previewUrl: origin + owner.path, retainedKey, liveAssets: options?.liveAssets === true };
        await writeFile(join(root, 'active-preview.json'), JSON.stringify(active, null, 2), { mode: 0o600 });
        return { ...active, close, closed };
    } catch (error) {
        await close();
        throw error;
    }
}

/** One browser origin for owner testing; manage only Vite processes created by this launcher. */
async function startManualPreview() {
    const repository = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
    const profileFile = join(repository, 'storage/app/private/dev-preview/manual-preview.json');
    const selectedIndex = process.argv.indexOf('--preview-info');
    const infoFile = selectedIndex >= 0 ? process.argv[selectedIndex + 1] : JSON.parse(await readFile(profileFile, 'utf8')).infoFile;
    const builtAssets = process.argv.includes('--built');
    await retainedPreviewEnvironment(infoFile);
    const probe = createServer();
    await new Promise((done, reject) => {
        probe.once('error', () =>
            reject(Error('Port 8000 is occupied. Stop its existing server first; no automatic port fallback or process termination.')),
        );
        probe.listen(8000, '127.0.0.1', () => probe.close(done));
    });
    let vite;
    let host;
    const stop = async () => {
        await host?.close();
        stopOwnedProcess(vite);
    };
    try {
        if (!builtAssets) {
            let hot;
            try {
                hot = (await readFile(join(repository, 'public/hot'), 'utf8')).trim();
            } catch (error) {
                if (error.code !== 'ENOENT') throw error;
            }
            const validHot = hot && /^http:\/\/(?:127\.0\.0\.1|localhost|\[::1\]):5173\/?$/.test(hot);
            let running = false;
            if (validHot) {
                try {
                    running = (await fetch(hot + '/@vite/client', { signal: AbortSignal.timeout(2000) })).ok;
                } catch {}
            }
            if (!running) {
                vite = spawn(
                    process.execPath,
                    [join(repository, 'node_modules/vite/bin/vite.js'), '--host', '127.0.0.1', '--port', '5173', '--strictPort'],
                    {
                        cwd: repository,
                        env: { ...process.env, APP_URL: 'http://127.0.0.1:8000' },
                        stdio: ['ignore', 'pipe', 'pipe'],
                        windowsHide: true,
                    },
                );
                const log = [];
                let viteError;
                vite.on('error', (error) => {
                    viteError = error;
                });
                vite.once('exit', () => {
                    if (host) void host.close();
                });
                vite.stdout.on('data', (value) => log.push(value.toString()));
                vite.stderr.on('data', (value) => log.push(value.toString()));
                for (let attempt = 0; attempt < 60; attempt++) {
                    if (viteError) throw viteError;
                    if (vite.exitCode !== null) throw Error('Vite exited: ' + log.join('').slice(-1500));
                    try {
                        if ((await fetch('http://127.0.0.1:5173/@vite/client', { signal: AbortSignal.timeout(1000) })).ok) {
                            running = true;
                            break;
                        }
                    } catch {}
                    await new Promise((done) => setTimeout(done, 100));
                }
                if (!running) throw Error('Vite did not become ready; no fallback port or dependency install was attempted.');
            }
        } else {
            await stat(join(repository, 'public/build/manifest.json'));
        }
        host = await resumeCmsEditorPreview(infoFile, { liveAssets: !builtAssets });
        await mkdir(dirname(profileFile), { recursive: true });
        await writeFile(profileFile, JSON.stringify({ infoFile: resolve(infoFile) }, null, 2));
        console.log(
            JSON.stringify(
                {
                    login: host.origin + '/login',
                    cms: host.previewUrl,
                    published: host.origin + '/w/synthetic-editor-preview',
                    credentialsFile: join(host.root, 'active-preview.json'),
                    liveSource: !builtAssets,
                    retainedEncryptionKey: host.retainedKey,
                    note: 'Existing synthetic SQLite only; no migration, seed or .env change. Keep this terminal open. Ctrl+C stops owned services. Legacy encrypted credential recovery is unverified without runtime.json.',
                },
                null,
                2,
            ),
        );
        for (const signal of ['SIGINT', 'SIGTERM'])
            process.once(signal, async () => {
                await stop();
                process.exit(0);
            });
        const result = await host.closed;
        await stop();
        if (result.code) throw Error('The manual PHP server exited with code ' + result.code + '.');
    } catch (error) {
        await stop();
        throw error;
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    if (process.argv.includes('--manual')) {
        await startManualPreview();
    } else {
        const checkIndex = process.argv.indexOf('--check-info');
        if (checkIndex >= 0) {
            await retainedPreviewEnvironment(process.argv[checkIndex + 1]);
            console.log('Retained preview paths validated; no server or database changes.');
            process.exit(0);
        }
        const resumeIndex = process.argv.indexOf('--resume');
        if (resumeIndex < 0 || !process.argv[resumeIndex + 1])
            throw Error(
                'Manual QA must resume an existing preview on port 8000. Use .\\dev.cmd. Automated tests may call createCmsEditorPreview explicitly.',
            );
        const host = await resumeCmsEditorPreview(process.argv[resumeIndex + 1], { liveAssets: process.argv.includes('--live') });
        console.log(
            JSON.stringify({
                previewUrl: host.previewUrl,
                publishedUrl: host.origin + '/w/synthetic-editor-preview',
                credentialsFile: join(host.root, 'active-preview.json'),
                retainedEncryptionKey: host.retainedKey,
                note: 'Retained SQLite data; no migration, seed or .env change. Legacy manifests without runtime.json cannot prove encrypted credential recovery. Ctrl+C stops this host.',
            }),
        );
        for (const signal of ['SIGINT', 'SIGTERM'])
            process.once(signal, async () => {
                await host.close();
                process.exit(0);
            });
    }
}

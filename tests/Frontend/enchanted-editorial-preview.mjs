import vue from '@vitejs/plugin-vue';
import { renderToString } from '@vue/server-renderer';
import { createReadStream } from 'node:fs';
import { mkdtemp, readFile, stat } from 'node:fs/promises';
import { createServer as createTcpServer } from 'node:net';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createServer } from 'vite';
import { createSSRApp } from 'vue';

const privatePhotos = resolve('storage/app/private/dev-preview/enchanted-editorial');
const allowedPhotos = new Map(
    [
        'hero.jpg',
        'couple-portrait.jpg',
        'couple-closeup.jpg',
        'romantic-photo.jpg',
        'story-photo.jpg',
        'gallery-01.jpg',
        'gallery-02.jpg',
        'gallery-03.jpg',
        'gallery-04.jpg',
        'gallery-05.jpg',
        'gallery-06.jpg',
    ].map((file) => [`/__enchanted-assets/${file}`, join(privatePhotos, file)]),
);
const privacyHeaders = {
    'Cache-Control': 'private, no-store',
    'Referrer-Policy': 'no-referrer',
    'X-Robots-Tag': 'noindex, nofollow, noarchive',
    'X-Content-Type-Options': 'nosniff',
    'Cross-Origin-Resource-Policy': 'same-origin',
};

/** Test-only loopback host. No Laravel routes, credentials, database or production SSR infrastructure. */
export async function createEnchantedEditorialServer(port = 0) {
    // Vite treats zero as its default 5173. Reserve an OS-selected loopback port for independent test hosts.
    const previewPort =
        port ||
        (await new Promise((resolvePort, reject) => {
            const probe = createTcpServer();
            probe.once('error', reject);
            probe.listen(0, '127.0.0.1', () => {
                const selected = probe.address().port;
                probe.close((error) => (error ? reject(error) : resolvePort(selected)));
            });
        }));
    const fixtureRoot = resolve('tests/Frontend/fixtures');
    const server = await createServer({
        configFile: false,
        root: fixtureRoot,
        publicDir: false,
        cacheDir: await mkdtemp(join(tmpdir(), 'rsvp-enchanted-vite-')),
        plugins: [
            vue(),
            {
                name: 'enchanted-local-trial',
                configureServer(vite) {
                    vite.middlewares.use(async (request, response, next) => {
                        const pathname = new URL(request.url, 'http://127.0.0.1').pathname;
                        if (pathname === '/__enchanted-style.css' || pathname === '/__enchanted-sections.css') {
                            const stylesheet = pathname === '/__enchanted-style.css' ? 'enchanted-editorial.css' : 'enchanted-editorial-sections.css';
                            request.url = '/@fs/' + resolve('resources/css', stylesheet).replaceAll('\\', '/');
                            return next();
                        }
                        if (pathname.startsWith('/__enchanted-assets/')) {
                            for (const [key, value] of Object.entries(privacyHeaders)) response.setHeader(key, value);
                            const file = allowedPhotos.get(pathname);
                            if (!file || !['GET', 'HEAD'].includes(request.method)) {
                                response.statusCode = 404;
                                return response.end();
                            }
                            try {
                                const info = await stat(file);
                                response.setHeader('Content-Type', 'image/jpeg');
                                response.setHeader('Content-Length', info.size);
                                if (request.method === 'HEAD') return response.end();
                                return createReadStream(file)
                                    .on('error', () => response.destroy())
                                    .pipe(response);
                            } catch {
                                response.statusCode = 404;
                                return response.end();
                            }
                        }
                        if (pathname !== '/enchanted-editorial.html') return next();
                        try {
                            const params = new URL(request.url, 'http://127.0.0.1').searchParams;
                            const { default: Fixture } = await vite.ssrLoadModule(resolve(fixtureRoot, 'EnchantedEditorialFixture.vue'));
                            const markup = await renderToString(
                                createSSRApp(Fixture, { variant: params.get('case'), canvas: params.get('canvas') === '1' }),
                            );
                            const template = await readFile(join(fixtureRoot, 'enchanted-editorial.html'), 'utf8');
                            const html = await vite.transformIndexHtml(request.url, template.replace('<!--ssr-content-->', markup));
                            response.setHeader('Content-Type', 'text/html; charset=utf-8');
                            for (const [key, value] of Object.entries(privacyHeaders)) response.setHeader(key, value);
                            return response.end(html);
                        } catch (error) {
                            vite.ssrFixStacktrace(error);
                            return next(error);
                        }
                    });
                },
            },
        ],
        resolve: { alias: { '@': resolve('resources/js') } },
        css: { postcss: { plugins: [] } },
        server: {
            host: '127.0.0.1',
            port: previewPort,
            strictPort: true,
            cors: false,
            fs: { allow: [resolve('resources'), fixtureRoot, resolve('node_modules')] },
            headers: privacyHeaders,
        },
    });
    try {
        await server.listen();
    } catch (error) {
        await server.close();
        throw error;
    }
    return server;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    const server = await createEnchantedEditorialServer(5196);
    console.log(`Local Enchanted trial: http://127.0.0.1:${server.httpServer.address().port}/enchanted-editorial.html`);
    for (const signal of ['SIGINT', 'SIGTERM'])
        process.on(signal, async () => {
            await server.close();
            process.exit(0);
        });
}

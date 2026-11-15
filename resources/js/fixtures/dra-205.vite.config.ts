import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig, type Plugin } from 'vite-plus';
import base from '../../../vite.config';

const root = resolve(import.meta.dirname, '../../..');
const output = '/private/tmp/dra205-real-errors';

function errorPageFixture(): Plugin {
    return {
        name: 'dra-205-real-laravel-error-pages',
        configureServer(server) {
            server.middlewares.use(
                '/resources/visual/dra-205-error.html',
                (request, response) => {
                    try {
                        const query = new URL(
                            request.url ?? '',
                            'http://fixture.test',
                        ).searchParams;
                        const status = Number(query.get('status'));
                        if (
                            ![401, 402, 403, 404, 419, 429, 500, 503].includes(
                                status,
                            )
                        ) {
                            response.writeHead(400).end('Unknown error status');
                            return;
                        }
                        const manifest = JSON.parse(
                            readFileSync(`${output}/manifest.json`, 'utf8'),
                        ) as {
                            head: string;
                            template: string;
                        };
                        const head = execFileSync(
                            'git',
                            ['rev-parse', 'HEAD'],
                            { cwd: root, encoding: 'utf8' },
                        ).trim();
                        const template = createHash('sha256')
                            .update(
                                readFileSync(
                                    resolve(
                                        root,
                                        'resources/views/errors/minimal.blade.php',
                                    ),
                                ),
                            )
                            .digest('hex');
                        if (
                            manifest.head !== head ||
                            manifest.template !== template
                        ) {
                            response
                                .writeHead(409)
                                .end(
                                    'Regenerate error pages for this committed HEAD',
                                );
                            return;
                        }
                        const html = readFileSync(
                            `${output}/${status}.html`,
                            'utf8',
                        );
                        const instrumentation = `<script>(function(){const q=new URLSearchParams(location.search);const mode=q.get('mode');const dark=mode==='dark'||(mode==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('dark',dark);document.documentElement.style.fontSize=q.get('text')==='200'?'32px':'16px';document.body.dataset.visualReady='${status}';})();</script>`;
                        response.setHeader(
                            'Content-Type',
                            'text/html; charset=utf-8',
                        );
                        response.end(
                            html.replace(
                                '</body>',
                                `${instrumentation}</body>`,
                            ),
                        );
                    } catch (error) {
                        response.writeHead(500).end(String(error));
                    }
                },
            );
        },
    };
}

export default defineConfig({
    ...base,
    plugins: [
        errorPageFixture(),
        ...(Array.isArray(base.plugins) ? base.plugins : [base.plugins]),
    ],
});

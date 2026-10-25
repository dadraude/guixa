import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtemp, writeFile, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
const runner = fileURLToPath(
    new URL('../../scripts/visual/run.mjs', import.meta.url),
);

test('CLI captures settled UI, verifies coverage, and rejects changed or missing evidence', async () => {
    const cwd = await mkdtemp(join(tmpdir(), 'visual-runner-test-'));
    const server = createServer(async (_req, res) => {
        res.setHeader('Content-Type', 'text/html');
        res.end(await readFile(join(cwd, 'index.html')));
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const cli = (command) =>
        new Promise((resolve, reject) => {
            const process = spawn(
                globalThis.process.execPath,
                [runner, command, 'visual.json', 'artifacts', 'HEAD'],
                { cwd },
            );
            let output = '';
            process.stdout.on('data', (d) => (output += d));
            process.stderr.on('data', (d) => (output += d));
            process.on('error', reject);
            process.on('exit', (code) => resolve({ code, output }));
        });
    try {
        await writeFile(
            join(cwd, 'index.html'),
            `<!doctype html><style>body{margin:16px}main{padding:16px;border:1px solid}button{font:inherit;white-space:normal}button:focus-visible{outline:3px solid blue}@keyframes enter{from{opacity:0}to{opacity:1}}main{animation:enter .15s}</style><main data-state="open"><h1>Wallet</h1><button id="save">Save</button></main><script>const q=new URLSearchParams(location.search);document.documentElement.style.fontSize=(16*Number(q.get('text'))/100)+'px';document.documentElement.classList.toggle('dark',q.get('mode')==='dark'||q.get('mode')==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);</script>`,
        );
        const manifest = {
            baseUrl: `http://127.0.0.1:${server.address().port}`,
            matrix: {
                widths: [375],
                modes: ['light', 'system-dark'],
                text: [100, 200],
            },
            views: [
                {
                    id: 'wallet',
                    path: '/',
                    root: 'main',
                    panels: 'main',
                    fonts: ['16px Arial'],
                    systemFonts: true,
                    focus: ['#save'],
                    states: [{ id: 'populated' }],
                },
            ],
        };
        await writeFile(join(cwd, 'visual.json'), JSON.stringify(manifest));
        execFileSync('git', ['init', '-q'], { cwd });
        execFileSync('git', ['add', 'index.html', 'visual.json'], { cwd });
        execFileSync(
            'git',
            [
                '-c',
                'user.name=Workflow Test',
                '-c',
                'user.email=workflow@example.invalid',
                '-c',
                'commit.gpgsign=false',
                'commit',
                '-qm',
                'Fixture',
            ],
            { cwd },
        );
        const run = await cli('run');
        assert.equal(run.code, 0, run.output);
        const report = JSON.parse(
            await readFile(join(cwd, 'artifacts/report.json')),
        );
        assert.equal(report.cases.length, 4);
        assert.equal((await cli('verify')).code, 0);
        const gallery = await readFile(
            join(cwd, 'artifacts/index.html'),
            'utf8',
        );
        assert.ok(gallery.includes(report.cases[0].screenshot));
        await writeFile(join(cwd, 'index.html'), '<main>Changed</main>');
        assert.match((await cli('verify')).output, /Uncommitted/);
        execFileSync('git', ['restore', 'index.html'], { cwd });
        const screenshot = join(cwd, 'artifacts', report.cases[0].screenshot);
        await writeFile(screenshot, 'corrupted');
        assert.match((await cli('verify')).output, /Changed screenshot/);
        await rm(screenshot);
        assert.match((await cli('verify')).output, /Missing screenshot/);
        // A failed fresh capture must invalidate the previous success, even if Chrome cannot start.
        const oldBinary = process.env.CHROME_BIN;
        process.env.CHROME_BIN = '/nonexistent/chrome';
        try {
            assert.notEqual((await cli('run')).code, 0);
        } finally {
            process.env.CHROME_BIN = oldBinary;
        }
        assert.match((await cli('verify')).output, /Missing or failed case/);
    } finally {
        await new Promise((resolve) => server.close(resolve));
        await rm(cwd, { recursive: true, force: true });
    }
});

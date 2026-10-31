import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createServer } from 'node:http';
import { spawn, execFileSync } from 'node:child_process';
import {
    mkdtemp,
    writeFile,
    readFile,
    rm,
    readdir,
    symlink,
    mkdir,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
const runner = fileURLToPath(
    new URL('../../scripts/visual/run.mjs', import.meta.url),
);

// The Node test runner owns completion and failure reporting; register the whole suite synchronously.
void test('CLI captures settled UI, verifies coverage, and rejects changed or missing evidence', async () => {
    const cwd = await mkdtemp(join(tmpdir(), 'visual-runner-test-'));
    const server = createServer(async (_req, res) => {
        res.setHeader('Content-Type', 'text/html');
        res.end(await readFile(join(cwd, 'index.html')));
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    let base;
    const cliArgs = (args) =>
        new Promise((resolve, reject) => {
            const process = spawn(
                globalThis.process.execPath,
                [runner, ...args],
                { cwd },
            );
            let output = '';
            process.stdout.on('data', (d) => (output += d));
            process.stderr.on('data', (d) => (output += d));
            process.on('error', reject);
            process.on('exit', (code) => resolve({ code, output }));
        });
    const cli = (command, ...flags) =>
        cliArgs([command, 'visual.json', 'artifacts', base, ...flags]);
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
            renderInputs: {
                review: 'Fixture closure reviewed for this isolated test',
                fixtures: ['index.html'],
                source: [],
                styles: [],
                assets: [],
                configuration: ['visual.json'],
                dependencies: [],
                data: [],
                notApplicable: {
                    source: 'Inline fixture',
                    styles: 'Inline fixture',
                    assets: 'System fonts',
                    dependencies: 'Dependency-free fixture',
                    data: 'Inline deterministic data',
                },
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
                    states: [
                        {
                            id: 'populated',
                            cases: [
                                { width: 375, mode: 'light', text: 100 },
                                { width: 375, mode: 'system-dark', text: 200 },
                            ],
                        },
                    ],
                },
            ],
        };
        await writeFile(join(cwd, 'visual.json'), JSON.stringify(manifest));
        await writeFile(join(cwd, '.gitignore'), 'artifacts/\n');
        execFileSync('git', ['init', '-q'], { cwd });
        execFileSync(
            'git',
            ['add', '.gitignore', 'index.html', 'visual.json'],
            { cwd },
        );
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
        base = execFileSync('git', ['rev-parse', 'HEAD'], {
            cwd,
            encoding: 'utf8',
        }).trim();
        const ready = await cli('preflight');
        assert.equal(ready.code, 0, ready.output);
        assert.match(ready.output, /fixture ready/i);
        await assert.rejects(readdir(join(cwd, 'artifacts')), /ENOENT/);
        const outside = join(tmpdir(), 'visual-runner-outside-' + process.pid);
        const unsafe = await cliArgs([
            'preflight',
            'visual.json',
            outside,
            base,
        ]);
        assert.notEqual(unsafe.code, 0);
        assert.match(unsafe.output, /inside the task repository/);
        await assert.rejects(readdir(outside), /ENOENT/);
        const sourceOutput = await cliArgs([
            'run',
            'visual.json',
            'index.html',
            base,
        ]);
        assert.notEqual(sourceOutput.code, 0);
        assert.match(sourceOutput.output, /tracked source/);
        await symlink(outside, join(cwd, 'linked-output'));
        const linked = await cliArgs([
            'preflight',
            'visual.json',
            'linked-output',
            base,
        ]);
        assert.notEqual(linked.code, 0);
        assert.match(linked.output, /symlink/);
        await writeFile(join(cwd, 'untracked.json'), JSON.stringify(manifest));
        const untracked = await cliArgs([
            'preflight',
            'untracked.json',
            'artifacts',
            base,
        ]);
        assert.notEqual(untracked.code, 0);
        assert.match(untracked.output, /tracked file/);
        const invalidBase = await cliArgs([
            'preflight',
            'visual.json',
            'artifacts',
            'HEAD',
        ]);
        assert.notEqual(invalidBase.code, 0);
        assert.match(invalidBase.output, /40-character commit SHA/);
        await writeFile(
            join(cwd, 'visual.json'),
            JSON.stringify({
                ...manifest,
                views: [{ ...manifest.views[0], root: '#unready' }],
            }),
        );
        const unready = await cli('preflight');
        assert.notEqual(unready.code, 0);
        assert.match(unready.output, /Timed out/);
        await writeFile(join(cwd, 'visual.json'), JSON.stringify(manifest));
        await assert.rejects(readdir(join(cwd, 'artifacts')), /ENOENT/);
        await mkdir(join(cwd, 'artifacts'));
        await symlink(
            join(cwd, 'index.html'),
            join(cwd, 'artifacts/report.json'),
        );
        const fixtureBefore = await readFile(join(cwd, 'index.html'), 'utf8');
        const linkedArtifact = await cli('run');
        assert.notEqual(linkedArtifact.code, 0);
        assert.match(linkedArtifact.output, /artifact must not be a symlink/);
        assert.equal(
            await readFile(join(cwd, 'index.html'), 'utf8'),
            fixtureBefore,
        );
        await rm(join(cwd, 'artifacts'), { recursive: true });
        const preflightBinary = process.env.CHROME_BIN;
        process.env.CHROME_BIN = '/nonexistent/chrome';
        try {
            const missingBrowser = await cli('preflight');
            assert.notEqual(missingBrowser.code, 0);
            assert.match(missingBrowser.output, /CHROME_BIN/);
        } finally {
            process.env.CHROME_BIN = preflightBinary;
        }
        await assert.rejects(readdir(join(cwd, 'artifacts')), /ENOENT/);
        const measured = await cli('run');
        assert.equal(measured.code, 0, measured.output);
        assert.equal((await cli('verify')).code, 0);
        const outputs = await readdir(join(cwd, 'artifacts'));
        assert.ok(
            !outputs.some(
                (path) => path.endsWith('.png') || path === 'index.html',
            ),
        );
        const original = await readFile(
            join(cwd, 'artifacts/report.json'),
            'utf8',
        );
        process.env.CHROME_BIN = '/nonexistent/chrome';
        try {
            assert.match((await cli('preflight')).output, /CHROME_BIN/);
        } finally {
            process.env.CHROME_BIN = preflightBinary;
        }
        assert.equal(
            await readFile(join(cwd, 'artifacts/report.json'), 'utf8'),
            original,
        );
        await writeFile(
            join(cwd, 'artifacts/report.json'),
            original.replace('"status": "passed"', '"status": "forged"'),
        );
        assert.match(
            (await cli('verify')).output,
            /Changed measurement report/,
        );
        await writeFile(join(cwd, 'artifacts/report.json'), original);
        await writeFile(join(cwd, 'notes.md'), 'Evidence notes');
        execFileSync('git', ['add', 'notes.md'], { cwd });
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
                'Notes only',
            ],
            { cwd },
        );
        assert.equal((await cli('verify')).code, 0);
        const proof = JSON.parse(
            await readFile(join(cwd, 'artifacts/verification.json')),
        );
        assert.notEqual(proof.origin.head, proof.candidate.head);
        assert.equal(
            await readFile(join(cwd, 'artifacts/report.json'), 'utf8'),
            original,
        );
        const fixtureSource = await readFile(join(cwd, 'index.html'), 'utf8');
        await writeFile(
            join(cwd, 'index.html'),
            fixtureSource + '<!-- render input changed -->',
        );
        execFileSync('git', ['add', 'index.html'], { cwd });
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
                'Changed rendering input',
            ],
            { cwd },
        );
        assert.match((await cli('verify')).output, /Stale/);
        const caseId = 'wallet--populated--375--light--100';
        const run = await cli('run', '--capture=' + caseId);
        assert.equal(run.code, 0, run.output);
        const report = JSON.parse(
            await readFile(join(cwd, 'artifacts/report.json')),
        );
        assert.equal(report.cases.length, 2);
        assert.equal((await cli('verify')).code, 0);
        const gallery = await readFile(
            join(cwd, 'artifacts/index.html'),
            'utf8',
        );
        assert.ok(gallery.includes(report.cases[0].screenshot));
        assert.equal(
            (await readdir(join(cwd, 'artifacts'))).filter((name) =>
                name.endsWith('.png'),
            ).length,
            1,
        );
        await writeFile(
            join(cwd, 'artifacts/unrelated.png'),
            'User-owned artifact',
        );
        const textOnly = await cli('run');
        assert.equal(textOnly.code, 0, textOnly.output);
        assert.deepEqual(
            (await readdir(join(cwd, 'artifacts'))).filter((name) =>
                name.endsWith('.png'),
            ),
            ['unrelated.png'],
        );
        assert.ok(
            !(await readdir(join(cwd, 'artifacts'))).includes('index.html'),
        );
        await rm(join(cwd, 'artifacts/unrelated.png'));
        assert.equal((await cli('run', '--capture=' + caseId)).code, 0);
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

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFile, writeFile, mkdir, readdir, rm } from 'node:fs/promises';
import { dirname, resolve, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expandMatrix, checkCoverage } from './coverage.mjs';
import { openChrome, waitFor } from './chrome.mjs';
import { measureLayout, measureFocus } from './measure.mjs';
import { measureAssertions } from './assertions.mjs';
import { renderingInputs, digest } from './evidence.mjs';

const hash = (data) => createHash('sha256').update(data).digest('hex');
const git = (...args) => execFileSync('git', args, { encoding: 'utf8' }).trim();
const escapeHtml = (text) =>
    String(text).replace(
        /[&<>"']/g,
        (char) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[char],
    );

async function identity(manifestPath, raw, base) {
    git('ls-files', '--error-unmatch', manifestPath);
    const directory = dirname(fileURLToPath(import.meta.url));
    const sources = await Promise.all(
        (await readdir(directory))
            .filter((name) => name.endsWith('.mjs'))
            .sort()
            .map((name) => readFile(resolve(directory, name))),
    );
    return {
        head: git('rev-parse', 'HEAD'),
        base: git('rev-parse', '--verify', `${base}^{commit}`),
        manifestHash: hash(raw),
        harnessHash: hash(Buffer.concat(sources)),
        dirty: !!git('status', '--porcelain', '--untracked-files=no'),
        renderInputs: renderingInputs(JSON.parse(raw).renderInputs),
    };
}

async function checkArtifacts(manifest, report, candidate, output) {
    const errors = checkCoverage(manifest, report, candidate);
    if (report.schemaVersion === 2) {
        try {
            const raw = await readFile(resolve(output, 'report.json'));
            if (
                (
                    await readFile(resolve(output, 'report.sha256'), 'utf8')
                ).trim() !== digest(raw)
            )
                errors.push('Changed measurement report');
        } catch {
            errors.push('Missing report integrity evidence');
        }
    }
    if (candidate.dirty || report.dirty)
        errors.push('Uncommitted tracked changes invalidate review evidence');
    for (const item of report.cases ?? []) {
        if (!/^[a-z0-9-]+\.png$/.test(item.screenshot ?? '')) continue;
        try {
            if (
                hash(await readFile(resolve(output, item.screenshot))) !==
                item.sha256
            )
                errors.push(`Changed screenshot: ${item.id}`);
        } catch {
            errors.push(`Missing screenshot: ${item.id}`);
        }
    }
    return errors;
}

async function runCase(browser, manifest, item, output, captureIds) {
    const { view, state, width, mode, text, id } = item;
    const url = new URL(view.path, manifest.baseUrl);
    for (const [key, value] of Object.entries({
        ...view.query,
        ...state.query,
    }))
        url.searchParams.set(key, value);
    url.searchParams.set('mode', mode.startsWith('system-') ? 'system' : mode);
    url.searchParams.set('text', text);
    const dark = mode.endsWith('dark');
    await browser.send('Emulation.setDeviceMetricsOverride', {
        width,
        height: manifest.height ?? 900,
        deviceScaleFactor: 1,
        mobile: false,
    });
    await browser.send('Emulation.setEmulatedMedia', {
        features: [
            { name: 'prefers-color-scheme', value: dark ? 'dark' : 'light' },
            {
                name: 'prefers-reduced-motion',
                value: state.reducedMotion ? 'reduce' : 'no-preference',
            },
        ],
    });
    await browser.send('Page.navigate', { url: 'about:blank' });
    await waitFor(
        browser,
        'location.href === "about:blank" && document.readyState === "complete"',
        'blank document',
    );
    const navigation = await browser.send('Page.navigate', { url: url.href });
    if (navigation.errorText) throw new Error(navigation.errorText);
    await waitFor(
        browser,
        `location.href === ${JSON.stringify(url.href)} && document.readyState === 'complete' && !!document.querySelector(${JSON.stringify(state.ready ?? view.root)})`,
        id,
    );
    const fonts = await browser.evaluate(`(async () => {
        await document.fonts.ready;
        return await Promise.all(${JSON.stringify(view.fonts)}.map(async css => {
            const faces = await document.fonts.load(css);
            return { css, available: document.fonts.check(css), loaded: faces.filter(face => face.status === 'loaded').length };
        }));
    })()`);
    if (
        fonts.some(
            (font) =>
                !font.available || (!view.systemFonts && font.loaded === 0),
        )
    )
        throw new Error('Required fonts not loaded: ' + JSON.stringify(fonts));
    await browser.evaluate(
        'new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))',
    );
    const settled = state.settled ?? [view.root];
    for (const selector of settled) {
        await waitFor(
            browser,
            `(() => {
            const nodes = [...document.querySelectorAll(${JSON.stringify(selector)})];
            return nodes.length > 0 && nodes.every(node => node.getBoundingClientRect().width > 0 && getComputedStyle(node).opacity === '1' && getComputedStyle(node).visibility === 'visible' && (!node.hasAttribute('data-state') || node.dataset.state === 'open') && node.getAnimations({subtree:true}).every(a => a.playState === 'finished'));
        })()`,
            `settled capture: ${selector}`,
        );
    }
    const observed = await browser.evaluate(`({
        text: Math.round(parseFloat(getComputedStyle(document.documentElement).fontSize) / ${manifest.rootFontPx ?? 16} * 100),
        dark: !!document.querySelector(${JSON.stringify(manifest.darkSelector ?? 'html.dark')}),
        reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches
    })`);
    const measurements = await browser.evaluate(
        `(${measureLayout.toString()})(${JSON.stringify(view)})`,
    );
    const assertions = await browser.evaluate(
        '(' +
            measureAssertions.toString() +
            ')(' +
            JSON.stringify(view.assertions ?? []) +
            ')',
    );
    const failures = [
        ...measurements.failures,
        ...assertions.failures,
        ...assertions.unverified,
    ];
    if (measurements.viewport !== width) failures.push('Wrong viewport');
    if (observed.text !== text)
        failures.push('Fixture did not apply text enlargement');
    if (observed.dark !== dark)
        failures.push('Fixture did not apply appearance');
    if (observed.reducedMotion !== !!state.reducedMotion)
        failures.push('Wrong motion preference');
    const captureEvidence = {};
    if (captureIds.includes(id)) {
        const screenshot = id + '.png';
        const capture = await browser.send('Page.captureScreenshot', {
            format: 'png',
            captureBeyondViewport: true,
            clip: {
                x: 0,
                y: 0,
                width,
                height: await browser.evaluate(
                    'Math.max(innerHeight, document.documentElement.scrollHeight)',
                ),
                scale: 1,
            },
        });
        const bytes = Buffer.from(capture.data, 'base64');
        await writeFile(resolve(output, screenshot), bytes);
        Object.assign(captureEvidence, { screenshot, sha256: hash(bytes) });
    }
    const focused = new Set();
    const focusMeasurements = [];
    await browser.evaluate(
        'document.activeElement?.blur(); window.scrollTo(0,0)',
    );
    for (
        let step = 0;
        focused.size < view.focus.length && step < (manifest.maxTabs ?? 100);
        step++
    ) {
        await browser.send('Input.dispatchKeyEvent', {
            type: 'keyDown',
            key: 'Tab',
            code: 'Tab',
            windowsVirtualKeyCode: 9,
        });
        await browser.send('Input.dispatchKeyEvent', {
            type: 'keyUp',
            key: 'Tab',
            code: 'Tab',
            windowsVirtualKeyCode: 9,
        });
        await browser.evaluate(
            'new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))',
        );
        const focus = await browser.evaluate(
            `(${measureFocus.toString()})(${JSON.stringify(view.focus)})`,
        );
        if (focus.matched.length) focusMeasurements.push(focus);
        for (const match of focus.matched) focused.add(match);
        failures.push(
            ...focus.failures.map(
                (failure) => `${focus.matched.join(', ')}: ${failure}`,
            ),
        );
    }
    for (const selector of view.focus)
        if (!focused.has(selector))
            failures.push(`Keyboard did not reach ${selector}`);
    return {
        id,
        url: url.href,
        status: failures.length ? 'failed' : 'passed',
        ...captureEvidence,
        assertions,
        fonts,
        measurements: {
            ...measurements,
            text: observed.text,
            mode: observed.dark === dark ? mode : 'incorrect',
        },
        focused: [...focused],
        focusMeasurements,
        failures: [...new Set(failures)],
    };
}

async function main() {
    const [command, manifestName, outputName, base, ...flags] =
        process.argv.slice(2);
    if (
        flags.some(
            (flag) => flag !== '--capture' && !flag.startsWith('--capture='),
        ) ||
        (command !== 'run' && flags.length)
    )
        throw new Error('Unknown or misplaced capture option');
    if (
        !['plan', 'run', 'verify'].includes(command) ||
        !manifestName ||
        (command !== 'plan' && (!outputName || !base))
    )
        throw new Error(
            'Usage: node run.mjs plan <manifest.json> | run|verify <manifest.json> <output-directory> <fixed-base-sha> [--capture[=<case-id>]]',
        );
    const manifestPath = resolve(manifestName);
    const raw = await readFile(manifestPath);
    const manifest = JSON.parse(raw);
    const cases = expandMatrix(manifest);
    if (command === 'plan') {
        for (const view of manifest.views) {
            const count = cases.filter(
                (item) => item.view.id === view.id,
            ).length;
            console.log(`${view.id}: ${count} cases`);
        }
        console.log(`Total: ${cases.length} cases`);
        return;
    }
    const output = resolve(outputName);
    const candidate = await identity(manifestPath, raw, base);
    if (command === 'verify') {
        const errors = await checkArtifacts(
            manifest,
            JSON.parse(await readFile(resolve(output, 'report.json'))),
            candidate,
            output,
        );
        if (errors.length) throw new Error(errors.join('\n'));
        const report = JSON.parse(
            await readFile(resolve(output, 'report.json')),
        );
        await writeFile(
            resolve(output, 'verification.json'),
            JSON.stringify(
                {
                    verifiedAt: new Date().toISOString(),
                    origin: { head: report.head, base: report.base },
                    candidate: { head: candidate.head, base: candidate.base },
                    renderInputsHash: candidate.renderInputs?.hash ?? null,
                    reportHash: digest(
                        await readFile(resolve(output, 'report.json')),
                    ),
                },
                null,
                2,
            ),
        );
        console.log(
            'Visual coverage passed: ' +
                cases.length +
                ' cases at ' +
                candidate.head,
        );
        return;
    }
    const captureIds = flags.includes('--capture')
        ? cases.map((item) => item.id)
        : flags.map((flag) => flag.slice('--capture='.length));
    if (captureIds.some((id) => !cases.some((item) => item.id === id)))
        throw new Error('Unknown capture case');
    await mkdir(output, { recursive: true });
    // Only remove artifacts owned by the previous run, never arbitrary user images.
    if (
        output === git('rev-parse', '--show-toplevel') ||
        git('ls-files', '--', output)
    )
        throw new Error('Evidence output must not contain tracked source');
    let previous;
    try {
        previous = JSON.parse(await readFile(resolve(output, 'report.json')));
    } catch {}
    for (const item of previous?.cases ?? [])
        if (/^[a-z0-9-]+\.png$/.test(item.screenshot ?? ''))
            await rm(resolve(output, item.screenshot), { force: true });
    for (const name of ['index.html', 'verification.json'])
        await rm(resolve(output, name), { force: true });
    // Invalidate an earlier success before launching a browser or overwriting any captures.
    const report = {
        ...candidate,
        schemaVersion: 2,
        captureIds,
        generatedAt: new Date().toISOString(),
        cases: [],
    };
    await writeFile(
        resolve(output, 'report.json'),
        JSON.stringify(report, null, 2),
    );
    const saveReport = async () => {
        const bytes = JSON.stringify(report, null, 2);
        await writeFile(resolve(output, 'report.json'), bytes);
        await writeFile(resolve(output, 'report.sha256'), digest(bytes));
    };
    await saveReport();
    const browser = await openChrome();
    try {
        for (const item of cases) {
            let record;
            try {
                record = await runCase(
                    browser,
                    manifest,
                    item,
                    output,
                    captureIds,
                );
            } catch (error) {
                record = {
                    id: item.id,
                    status: 'failed',
                    failures: [error.message],
                };
            }
            report.cases.push(record);
            console.log(
                `${record.status}: ${item.id}${record.failures?.length ? ': ' + record.failures.join('; ') : ''}`,
            );
            await saveReport();
        }
    } finally {
        await browser.close();
    }
    if (captureIds.length) {
        const cards = report.cases
            .map(
                (item) =>
                    `<figure><figcaption>${escapeHtml(item.id)} — ${escapeHtml(item.status)}</figcaption>${item.screenshot ? `<a href="${item.screenshot}"><img loading="lazy" src="${item.screenshot}"></a>` : ''}<pre>${escapeHtml((item.failures ?? []).join('\n'))}</pre></figure>`,
            )
            .join('\n');
        await writeFile(
            resolve(output, 'index.html'),
            `<!doctype html><meta charset="utf-8"><title>Visual coverage</title><style>body{font:14px system-ui;margin:24px}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}figure{margin:0;min-width:0}img{max-width:100%;max-height:600px;object-fit:contain;object-position:top}pre{white-space:pre-wrap}</style><h1>${escapeHtml(basename(manifestName))}</h1><p>Candidate ${escapeHtml(candidate.head)}</p><main>${cards}</main>`,
        );
    }
    report.completedAt = new Date().toISOString();
    await saveReport();
    const after = await identity(
        manifestPath,
        await readFile(manifestPath),
        base,
    );
    const errors = await checkArtifacts(manifest, report, after, output);
    if (errors.length) throw new Error(errors.join('\n'));
    console.log(
        'Visual coverage passed: ' +
            cases.length +
            ' cases; text report: ' +
            output +
            '/report.json',
    );
}
main().catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
});

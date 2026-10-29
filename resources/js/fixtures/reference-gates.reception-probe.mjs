import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import {
    openChrome,
    waitFor,
} from '../../../skills/agent-workflow/scripts/visual/chrome.mjs';
import { measureLayout } from '../../../skills/agent-workflow/scripts/visual/measure.mjs';

const manifest = JSON.parse(
    readFileSync(new URL('./reference-gates.visual.json', import.meta.url)),
);
const view = manifest.views.find((item) => item.id === 'manage-reception-show');
const stateNames = new Set([
    'default',
    'long-content',
    'manual-allocation',
    'received-scope-choice',
    'received-overwrite',
    'adjusted-summary',
    'unordered-summary',
    'extra-household',
    'unbalanced-summary',
]);
const browser = await openChrome();
const results = [];
try {
    for (const state of view.states.filter((item) => stateNames.has(item.id))) {
        for (const selected of state.cases) {
            const url = new URL(view.path, manifest.baseUrl);
            for (const [key, value] of Object.entries({
                ...view.query,
                ...state.query,
            })) {
                url.searchParams.set(key, value);
            }
            url.searchParams.set('mode', selected.mode);
            url.searchParams.set('text', selected.text);
            await browser.send('Emulation.setDeviceMetricsOverride', {
                width: selected.width,
                height: 900,
                deviceScaleFactor: 1,
                mobile: false,
            });
            await browser.send('Emulation.setEmulatedMedia', {
                features: [
                    { name: 'prefers-color-scheme', value: selected.mode },
                ],
            });
            await browser.send('Page.navigate', { url: url.href });
            await waitFor(
                browser,
                `!!document.querySelector(${JSON.stringify(state.ready ?? '#main-content [data-test^="product-"] dl')})`,
                `${state.id}/${selected.width}/${selected.mode}/${selected.text}`,
            );
            await browser.evaluate(
                'document.fonts.ready.then(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))))',
            );
            const observed = await browser.evaluate(`(() => {
                const box = (rect) => ({ left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, width: rect.width, height: rect.height });
                const textRects = (element) => {
                    const range = document.createRange();
                    range.selectNodeContents(element);
                    return [...range.getClientRects()].filter(rect => rect.width > 0 && rect.height > 0).map(box);
                };
                const intersection = (first, second) =>
                    Math.max(0, Math.min(first.right, second.right) - Math.max(first.left, second.left)) *
                    Math.max(0, Math.min(first.bottom, second.bottom) - Math.max(first.top, second.top));
                const rows = [...document.querySelectorAll('#main-content [data-test^="product-"] dl')].map((dl) => {
                    const metrics = [...dl.children].map((group) => {
                        const label = group.querySelector('dt');
                        const value = group.querySelector('dd');
                        return {
                            label: label.textContent.trim(),
                            value: value.textContent.trim(),
                            group: box(group.getBoundingClientRect()),
                            labelRects: textRects(label),
                            valueRects: textRects(value),
                            valueTest: value.dataset.test,
                        };
                    });
                    const collisions = [];
                    const escapes = [];
                    for (let a = 0; a < metrics.length; a++) {
                        for (const [kind, rects] of [['label', metrics[a].labelRects], ['value', metrics[a].valueRects]]) {
                            for (const rect of rects) {
                                if (rect.left < metrics[a].group.left - 0.5 || rect.right > metrics[a].group.right + 0.5)
                                    escapes.push({ metric: a, kind, rect, group: metrics[a].group });
                            }
                        }
                        for (let b = a + 1; b < metrics.length; b++) {
                            for (const [ak, ars] of [['label', metrics[a].labelRects], ['value', metrics[a].valueRects]])
                                for (const [bk, brs] of [['label', metrics[b].labelRects], ['value', metrics[b].valueRects]])
                                    for (const ar of ars) for (const br of brs) {
                                        const area = intersection(ar, br);
                                        if (area > 0.5) collisions.push({ first: a + ':' + ak, second: b + ':' + bk, area });
                                    }
                        }
                    }
                    return { gridColumns: getComputedStyle(dl).gridTemplateColumns, metrics, collisions, escapes };
                });
                return {
                    rows,
                    rootFontPx: parseFloat(getComputedStyle(document.documentElement).fontSize),
                    horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
                };
            })()`);
            const checkedView = { ...view, ...state.checks };
            const layout = await browser.evaluate(
                `(${measureLayout.toString()})(${JSON.stringify(checkedView)})`,
            );
            observed.layout = layout;
            const row = observed.rows[0];
            const failures = [];
            if (!row) failures.push('missing product summary');
            if (
                state.id === 'adjusted-summary' &&
                !row?.metrics.some((metric) => metric.valueTest === 'delivered')
            ) {
                failures.push('missing fourth Delivered metric');
            }
            if (observed.rootFontPx !== (16 * selected.text) / 100)
                failures.push('wrong text scale');
            if (observed.horizontalOverflow)
                failures.push('horizontal document overflow');
            failures.push(...layout.failures);
            for (const [index, item] of observed.rows.entries()) {
                if (item.collisions.length)
                    failures.push(`row ${index} text intersections`);
                if (item.escapes.length)
                    failures.push(`row ${index} text escapes`);
            }
            results.push({
                state: state.id,
                ...selected,
                url: url.href,
                ...observed,
                failures,
            });
            process.stdout.write(
                `${state.id}/${selected.width}/${selected.mode}/${selected.text}: ${failures.length ? failures.join(', ') : 'pass'}\n`,
            );
        }
    }
} finally {
    await browser.close();
}
const report = {
    head: execFileSync('git', ['rev-parse', 'HEAD'], {
        encoding: 'utf8',
    }).trim(),
    base: '1a9a41adcafa8b2f23ed162d7b3a52a5ac541ac0',
    dirty: Boolean(
        execFileSync('git', ['status', '--porcelain', '--untracked-files=no'], {
            encoding: 'utf8',
        }).trim(),
    ),
    summary: {
        cases: results.length,
        failed: results.filter((item) => item.failures.length).length,
    },
    results,
};
const output = process.argv[2];
if (output) writeFileSync(output, JSON.stringify(report, null, 2) + '\n');
process.stdout.write(JSON.stringify(report.summary) + '\n');
if (report.summary.failed) process.exitCode = 1;

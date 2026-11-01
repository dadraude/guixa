// Recovery-code acceptance beyond the shared layout harness: exposed controls and code list.
import { writeFile } from 'node:fs/promises';
import {
    openChrome,
    waitFor,
} from '../../skills/agent-workflow/scripts/visual/chrome.mjs';

const output =
    process.argv[2] ?? '/private/tmp/dra200-recovery-acceptance.json';
const browser = await openChrome();
const results = [];
try {
    for (const width of [375, 1024, 1280]) {
        for (const mode of ['light', 'dark']) {
            const text = width === 1280 ? 100 : 200;
            await browser.send('Emulation.setDeviceMetricsOverride', {
                width,
                height: 900,
                deviceScaleFactor: 1,
                mobile: false,
            });
            await browser.send('Emulation.setEmulatedMedia', {
                features: [{ name: 'prefers-color-scheme', value: mode }],
            });
            const url = new URL(
                'http://127.0.0.1:5186/.vitest/dra200-visual/index.html',
            );
            for (const [key, value] of Object.entries({
                scenario: 'settings-security',
                role: 'manager',
                twofactor: 1,
                recoveryCodes: 1,
                mode,
                text,
            }))
                url.searchParams.set(key, String(value));
            await browser.send('Page.navigate', { url: url.href });
            await waitFor(
                browser,
                "document.documentElement.dataset.visualReady === 'true'",
                'security fixture',
            );
            const data = await browser.evaluate(`(() => {
                const failures = [];
                const grid = document.querySelector('[data-visual-recovery-codes]');
                const toggle = document.querySelector('[data-visual-recovery-toggle]');
                const regenerate = document.querySelector('[data-visual-recovery-regenerate]');
                const expectedCodes = ['aaaaa-bbbbb', 'ccccc-ddddd', 'eeeee-fffff', 'ggggg-hhhhh'];
                if (!grid || grid.children.length !== expectedCodes.length || !expectedCodes.every(code => grid.textContent.includes(code)))
                    failures.push('recovery codes not expanded and loaded');
                for (const [name, el] of [['hide', toggle], ['regenerate', regenerate]]) {
                    if (!el) { failures.push(name + ' control absent'); continue; }
                    const r = el.getBoundingClientRect();
                    if (r.width < 43.5 || r.height < 43.5) failures.push(name + ' target below 44px');
                }
                if (toggle && !toggle.textContent.includes('Hide recovery codes')) failures.push('toggle is not Hide recovery codes');
                if (regenerate && !regenerate.textContent.includes('Regenerate codes')) failures.push('regenerate label absent');
                if (document.documentElement.scrollWidth > innerWidth + 1) failures.push('horizontal overflow');
                const actionRow = toggle?.parentElement;
                const direction = actionRow ? getComputedStyle(actionRow).flexDirection : null;
                const toggleRect = toggle?.getBoundingClientRect();
                const regenerateRect = regenerate?.getBoundingClientRect();
                const cardContentRect = grid?.closest('[data-slot="card-content"]')?.getBoundingClientRect();
                if (regenerateRect && cardContentRect && regenerateRect.right > cardContentRect.right + 1)
                    failures.push('regenerate action escapes recovery card content');
                const isStacked = !!(toggleRect && regenerateRect && regenerateRect.top >= toggleRect.bottom);
                const actionGap = toggleRect && regenerateRect
                    ? isStacked
                        ? regenerateRect.top - toggleRect.bottom
                        : regenerateRect.left - toggleRect.right
                    : 0;
                if (${width} === 375 && direction !== 'column') failures.push('phone action row did not stack');
                if (${width} === 1280 && isStacked) failures.push('wide action row unexpectedly wrapped');
                if (actionGap < 8) failures.push('action targets overlap or have less than 8px separation');
                for (const [name, el] of [['hide', toggle], ['regenerate', regenerate]]) {
                    if (el && el.scrollWidth > el.clientWidth + 1) failures.push(name + ' label is clipped');
                }
                return { failures, direction, isStacked, actionGap, codeCount: grid?.children.length ?? 0, toggleHeight: toggleRect?.height ?? 0, regenerateHeight: regenerateRect?.height ?? 0, cardContentRight: cardContentRect?.right ?? 0, regenerateRight: regenerateRect?.right ?? 0 };
            })()`);
            results.push({ width, mode, text, ...data });
        }
    }
} finally {
    await browser.close();
}
await writeFile(output, JSON.stringify(results, null, 2) + '\n');
for (const item of results)
    console.log(
        `${item.width}/${item.mode}: ${item.failures.length ? item.failures.join('; ') : 'passed'}`,
    );
process.exit(results.some((item) => item.failures.length) ? 1 : 0);

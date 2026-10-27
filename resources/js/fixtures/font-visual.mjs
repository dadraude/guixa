import { readFile } from 'node:fs/promises';
import {
    openChrome,
    waitFor,
} from '../../../skills/agent-workflow/scripts/visual/chrome.mjs';

const manifestPath = process.argv[2];
if (!manifestPath) throw new Error('Pass a visual manifest path');
const fallback = process.argv.includes('--fallback');

const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const dimensions = fallback
    ? [
          { width: 375, mode: 'dark', text: 200 },
          { width: 1280, mode: 'light', text: 100 },
      ]
    : [
          { width: 375, mode: 'light', text: 100 },
          { width: 375, mode: 'dark', text: 100 },
          { width: 1024, mode: 'light', text: 200 },
          { width: 1024, mode: 'dark', text: 200 },
          { width: 1280, mode: 'light', text: 100 },
          { width: 1280, mode: 'dark', text: 100 },
      ];

const browser = await openChrome();
let checked = 0;
const failures = [];

try {
    for (const view of manifest.views) {
        if (['welcome-unchanged', 'wallet-boundary'].includes(view.id))
            continue;
        const state = fallback
            ? view.states.find((candidate) => candidate.id === 'font-fallback')
            : (view.states.find((candidate) => candidate.id === 'populated') ??
              view.states[0]);
        if (!state) continue;

        for (const { width, mode, text } of dimensions) {
            await browser.send('Emulation.setDeviceMetricsOverride', {
                width,
                height: 900,
                deviceScaleFactor: 1,
                mobile: false,
            });
            const url = new URL(view.path, manifest.baseUrl);
            for (const [key, value] of Object.entries({
                ...view.query,
                ...state.query,
                mode,
                text,
            })) {
                url.searchParams.set(key, value);
            }
            await browser.send('Page.navigate', { url: url.href });
            const caseName = `${view.id}/${state.id} ${width}px ${mode} ${text}%`;
            try {
                await waitFor(
                    browser,
                    `document.readyState === 'complete' && document.fonts.status === 'loaded' && !!document.querySelector(${JSON.stringify(view.root)})`,
                    caseName,
                );
                const result = await browser.evaluate(`(async () => {
                    const theme = document.querySelector('.pilot-theme');
                    const heading = document.querySelector('.pilot-theme h1, .pilot-theme h2');
                    const faces = ${fallback ? '[]' : 'await document.fonts.load(\'400 16px "Nunito Variable"\')'};
                    return {
                        theme: theme && getComputedStyle(theme).fontFamily,
                        heading: heading && getComputedStyle(heading).fontFamily,
                        loaded: faces.filter((face) => face.status === 'loaded').length,
                        scrollWidth: document.documentElement.scrollWidth,
                        viewportWidth: document.documentElement.clientWidth,
                    };
                })()`);
                const issues = [];
                if (
                    !result.theme ||
                    result.theme.includes('Nunito Variable') === fallback
                )
                    issues.push(`body font: ${result.theme}`);
                if (
                    !result.heading ||
                    result.heading.includes('Nunito Variable') === fallback
                )
                    issues.push(`heading font: ${result.heading}`);
                if (!fallback && result.loaded === 0)
                    issues.push('Nunito face did not load');
                if (result.scrollWidth > result.viewportWidth + 1)
                    issues.push(`horizontal overflow: ${result.scrollWidth}px`);
                if (issues.length)
                    failures.push(`${caseName}: ${issues.join('; ')}`);
                checked++;
            } catch (error) {
                failures.push(`${caseName}: ${error.message}`);
            }
        }
    }
} finally {
    await browser.close().catch((error) => {
        console.warn(`Chrome profile cleanup: ${error.message}`);
    });
}

for (const failure of failures) console.error(failure);
console.log(`${checked} rendered cases checked; ${failures.length} failures`);
if (failures.length) process.exitCode = 1;

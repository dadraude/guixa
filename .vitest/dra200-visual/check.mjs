// Focused acceptance probes outside the shared screenshot harness.
import { writeFile } from 'node:fs/promises';
import {
    openChrome,
    waitFor,
} from '../../skills/agent-workflow/scripts/visual/chrome.mjs';

const output = process.argv[2] ?? '/private/tmp/dra200-acceptance.json';
const base = 'http://127.0.0.1:5186/.vitest/dra200-visual/index.html';
const views = [
    ...['settings-profile', 'settings-security', 'settings-appearance'].flatMap(
        (scenario) =>
            ['member', 'producer', 'manager'].map((role) => ({
                scenario,
                role,
            })),
    ),
    ...[
        'login',
        'forgot-password',
        'reset-password',
        'confirm-password',
        'two-factor-challenge',
        'verify-email',
        'accept-invitation',
    ].map((scenario) => ({ scenario, role: 'guest' })),
];
const cases = [
    ...['light', 'dark'].map((mode) => ({ width: 375, text: 100, mode })),
    ...['light', 'dark'].map((mode) => ({ width: 1024, text: 200, mode })),
    ...['light', 'dark'].map((mode) => ({ width: 1280, text: 100, mode })),
];
const browser = await openChrome();
const results = [];
try {
    for (const view of views)
        for (const item of cases) {
            await browser.send('Emulation.setDeviceMetricsOverride', {
                width: item.width,
                height: 900,
                deviceScaleFactor: 1,
                mobile: false,
            });
            await browser.send('Emulation.setEmulatedMedia', {
                features: [{ name: 'prefers-color-scheme', value: item.mode }],
            });
            const url = new URL(base);
            for (const [key, value] of Object.entries({ ...view, ...item }))
                url.searchParams.set(key, String(value));
            await browser.send('Page.navigate', { url: url.href });
            await waitFor(
                browser,
                "document.documentElement.dataset.visualReady === 'true'",
                `${view.scenario}/${view.role}`,
            );
            await browser.evaluate('document.fonts.ready');
            const measured = await browser.evaluate(`(() => {
            const failures = [];
            const visible = (el) => { const r = el.getBoundingClientRect(), s = getComputedStyle(el); return r.width > 1 && r.height > 1 && s.visibility !== 'hidden' && s.display !== 'none' && !el.closest('.sr-only,[aria-hidden="true"]'); };
            const controls = [...document.querySelectorAll('.pilot-theme button,.pilot-theme a[href],.pilot-theme input:not([type="hidden"]),.pilot-theme select')].filter(visible);
            for (const el of controls) {
                if (el.matches('[role="checkbox"],input[type="checkbox"],[data-slot="input-otp"]')) continue;
                const r = el.getBoundingClientRect();
                if (r.width < 43.5 || r.height < 43.5) failures.push('small target: ' + el.tagName + ' ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' ' + (el.textContent || el.id).trim().slice(0,25));
            }
            for (const el of document.querySelectorAll('.pilot-theme label:has([role="checkbox"],input[type="checkbox"])')) {
                if (!visible(el)) continue;
                const r = el.getBoundingClientRect();
                if (r.width < 43.5 || r.height < 43.5) failures.push('small checkbox label');
            }
            if (document.documentElement.scrollWidth > innerWidth + 1) failures.push('horizontal overflow');
            const rect = (s) => document.querySelector(s)?.getBoundingClientRect();
            const heading = rect('.pilot-settings-inner > header');
            const nav = rect('.pilot-settings-nav');
            const main = rect('.pilot-settings-main');
            const panels = [...document.querySelectorAll('.pilot-settings-main .pilot-panel')].filter(visible);
            if (heading && nav && main) {
                if (Math.abs(heading.x - nav.x) > 1) failures.push('settings heading/nav left edge');
                if (innerWidth <= 1024 && Math.abs(nav.x - main.x) > 1) failures.push('settings nav/content left edge');
                if (panels.some((panel) => Math.abs(panel.getBoundingClientRect().x - main.x) > 1)) failures.push('settings panel/content left edge');
                const expected = innerWidth === 375 ? 16 : innerWidth === 1280 ? 24 : 48;
                for (const el of [document.querySelector('.pilot-settings-nav'), ...panels]) if (Math.abs(parseFloat(getComputedStyle(el).paddingLeft) - expected) > 1) failures.push('settings panel padding ' + getComputedStyle(el).paddingLeft);
            }
            return { failures: [...new Set(failures)], controls: controls.length, panels: panels.length, scrollWidth: document.documentElement.scrollWidth };
        })()`);
            results.push({ ...view, ...item, ...measured });
        }
} finally {
    await browser.close();
}
await writeFile(output, JSON.stringify(results, null, 2) + '\n');
const failed = results.filter((item) => item.failures.length);
for (const item of failed.slice(0, 12))
    console.log(
        'FAIL',
        item.scenario,
        item.role,
        item.width,
        item.mode,
        item.failures,
    );
console.log(
    `${results.length - failed.length}/${results.length} acceptance cases passed`,
);
process.exit(failed.length ? 1 : 0);

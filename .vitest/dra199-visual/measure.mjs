import { readFile } from 'node:fs/promises';
import { openChrome, waitFor } from '../../skills/agent-workflow/scripts/visual/chrome.mjs';
import { measureLayout } from '../../skills/agent-workflow/scripts/visual/measure.mjs';

const manifest = JSON.parse(
    await readFile(new URL('../dra192-visual/visual.json', import.meta.url), 'utf8'),
);
const views = manifest.views.filter((view) => view.id !== 'wallet-boundary');
const populatedCases = [
    { width: 375, mode: 'light', text: 100 },
    { width: 375, mode: 'dark', text: 100 },
    { width: 1024, mode: 'light', text: 200 },
    { width: 1024, mode: 'dark', text: 200 },
    { width: 1280, mode: 'light', text: 100 },
    { width: 1280, mode: 'dark', text: 100 },
];
const presentationOnlyStates = new Set([
    'populated',
    'blank',
    'system-appearance',
    'reduced-motion',
    'font-fallback',
]);

const cases = views.flatMap((view) => [
    ...populatedCases.map((dimensions) => ({
        view,
        state:
            view.states.find((state) => state.id === 'populated') ??
            view.states[0],
        ...dimensions,
    })),
    ...view.states
        .filter((state) => !presentationOnlyStates.has(state.id))
        .map((state) => ({
            view,
            state,
            width: 375,
            mode: 'dark',
            text: 200,
        })),
]);

const browser = await openChrome();
let failed = 0;
try {
    for (const item of cases) {
        const { view, state, width, mode, text } = item;
        await browser.send('Emulation.setDeviceMetricsOverride', {
            width,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false,
        });
        await browser.send('Emulation.setEmulatedMedia', {
            features: [{ name: 'prefers-color-scheme', value: mode }],
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
        await waitFor(
            browser,
            `location.href === ${JSON.stringify(url.href)} && document.readyState === 'complete' && !!document.querySelector(${JSON.stringify(state.ready ?? view.root)})`,
            `${view.id}/${state.id}`,
        );

        const layout = await browser.evaluate(
            `(${measureLayout.toString()})(${JSON.stringify({
                root: view.root,
                panels: state.checks?.panels ?? view.panels,
                content: (state.checks?.content ?? view.content)?.replace(
                    'select,',
                    '',
                ),
            })})`,
        );
        const geometry = await browser.evaluate(`(() => {
            const page = document.querySelector('.pilot-layout-page');
            const pageStyle = getComputedStyle(page);
            const panel = page.querySelector(':scope > form, :scope > section, :scope > ul');
            const visible = (node) =>
                node.getClientRects().length > 0 &&
                getComputedStyle(node).visibility !== 'hidden' &&
                getComputedStyle(node).opacity !== '0';
            const controls = [
                ...page.querySelectorAll('button,a,input,textarea'),
                ...document.querySelectorAll('[data-slot="dialog-content"] button, [data-slot="sheet-content"] button'),
            ].filter(visible);
            const small = controls.filter((node) => {
                if (node.type === 'hidden') return false;
                if (node.type === 'checkbox' || node.type === 'radio') {
                    const label = node.closest('label') ||
                        document.querySelector('label[for="' + node.id + '"]');
                    if (label?.getBoundingClientRect().height >= 43) return false;
                }
                const box = node.getBoundingClientRect();
                return box.width < 43 || box.height < 43;
            });
            const left = page.getBoundingClientRect().left +
                parseFloat(pageStyle.paddingLeft);
            const misaligned = [...page.children].filter(visible).filter((node) =>
                Math.abs(node.getBoundingClientRect().left - left) > 1
            );
            return {
                pagePadding: parseFloat(pageStyle.paddingLeft),
                pageGap: parseFloat(pageStyle.rowGap),
                panelPadding: panel && parseFloat(getComputedStyle(panel).paddingLeft),
                small: small.length,
                misaligned: misaligned.length,
            };
        })()`);

        const scale = text / 100;
        const expectedPadding = (width >= 768 ? 24 : 16) * scale;
        const expectedGap = 24 * scale;
        const expectedPanelPadding = (width >= 768 ? 24 : 16) * scale;
        const issues = [...layout.failures];
        if (geometry.pagePadding !== expectedPadding)
            issues.push('Page padding differs from layout rule');
        if (geometry.pageGap !== expectedGap)
            issues.push('Page section gap differs from layout rule');
        if (
            geometry.panelPadding !== null &&
            geometry.panelPadding !== expectedPanelPadding
        )
            issues.push('Panel padding differs from layout rule');
        if (geometry.small) issues.push(`${geometry.small} small controls`);
        if (geometry.misaligned)
            issues.push(`${geometry.misaligned} misaligned page children`);
        if (issues.length) {
            failed++;
            console.error(
                `${view.id}/${state.id} ${width}px ${mode} ${text}%: ${issues.join('; ')}`,
            );
        }
    }
} finally {
    await browser.close();
}
console.log(`${cases.length} text-only layout cases; ${failed} failed`);
process.exitCode = failed ? 1 : 0;

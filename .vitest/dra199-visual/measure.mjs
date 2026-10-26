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
const cyclePreviewCase = { width: 729, mode: 'light', text: 100 };
const managerTablePreviewCases = [680, 729, 1024].map((width) => ({
    width,
    mode: 'light',
    text: 100,
}));
const presentationOnlyStates = new Set([
    'populated',
    'blank',
    'system-appearance',
    'reduced-motion',
    'font-fallback',
]);

const allCases = views.flatMap((view) => [
    ...(view.id === 'stock'
        ? [320, 375].map((width) => ({
              view,
              state: view.states.find((state) => state.id === 'many-products'),
              width,
              mode: 'light',
              text: 100,
          }))
        : []),
    ...(['stock', 'return-items'].includes(view.id)
        ? managerTablePreviewCases.map((dimensions) => ({
              view,
              state: view.states.find((state) => state.id === 'populated'),
              ...dimensions,
          }))
        : []),
    ...(view.id === 'cycles'
        ? [
              {
                  view,
                  state: view.states.find((state) => state.id === 'populated'),
                  ...cyclePreviewCase,
              },
          ]
        : []),
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
const cases = process.argv.includes('--mobile-stock-only')
    ? allCases.filter(
          (item) =>
              item.view.id === 'stock' &&
              ['many-products', 'long-content', 'inactive'].includes(item.state.id) &&
              (item.width === 320 || item.width === 375),
      )
    : process.argv.includes('--cycle-only')
    ? allCases.filter(
          (item) =>
              item.view.id === 'cycles' &&
              item.state.id === 'populated' &&
              (item.width === 729 || item.width === 1280) &&
              item.mode === 'light',
      )
    : process.argv.includes('--tables-only')
      ? allCases.filter(
            (item) =>
                ['stock', 'return-items'].includes(item.view.id) &&
                item.state.id === 'populated' &&
                item.mode === 'light' &&
                item.text === 100 &&
                managerTablePreviewCases.some(
                    (dimensions) => dimensions.width === item.width,
                ),
        )
    : allCases;

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
            const listInsets = [...page.querySelectorAll('ul.pilot-list')]
                .filter(visible)
                .filter((list) => {
                    const firstContent = list.querySelector(':scope > li > *');
                    if (!firstContent) return false;
                    const expected = list.getBoundingClientRect().left +
                        parseFloat(getComputedStyle(list).paddingLeft);
                    return Math.abs(firstContent.getBoundingClientRect().left - expected) > 1;
                });
            return {
                pagePadding: parseFloat(pageStyle.paddingLeft),
                pageGap: parseFloat(pageStyle.rowGap),
                panelPadding: panel && parseFloat(getComputedStyle(panel).paddingLeft),
                small: small.length,
                misaligned: misaligned.length,
                listInsets: listInsets.length,
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
        if (geometry.listInsets)
            issues.push(`${geometry.listInsets} list content insets differ from their panels`);
        if (view.id === 'stock' && state.id === 'many-products') {
            const mobile = await browser.evaluate(`(() => {
                const rows = [...document.querySelectorAll('.pilot-stock-mobile-list > li')];
                const summaries = rows.map((row) => row.querySelector('summary'));
                const first = rows[0].querySelector('details');
                const result = {
                    count: rows.length,
                    closed: rows.every((row) => !row.querySelector('details').open),
                    height: summaries[0].getBoundingClientRect().height,
                    visible: summaries.filter((summary) =>
                        summary.getBoundingClientRect().bottom <= innerHeight
                    ).length,
                };
                first.open = true;
                result.actions = [...first.querySelectorAll('a, button')].filter((node) =>
                    node.getBoundingClientRect().height >= 44
                ).length;
                return result;
            })()`);
            if (mobile.count !== 20 || !mobile.closed)
                issues.push(`Stock list count or closed state: ${mobile.count}, ${mobile.closed}`);
            if (
                text === 100 &&
                (mobile.height > (width < 375 ? 160 : 80) ||
                    mobile.visible < (width < 375 ? 3 : 4))
            )
                issues.push(`Stock phone density: ${mobile.height}px row, ${mobile.visible} visible`);
            if (mobile.actions !== 3)
                issues.push(`Expanded stock actions: ${mobile.actions}`);
            const expanded = await browser.evaluate(
                `(${measureLayout.toString()})(${JSON.stringify({
                    root: view.root,
                    panels: view.panels,
                    content: view.content,
                })})`,
            );
            issues.push(...expanded.failures);
        }
        if (
            view.id === 'cycles' &&
            state.id === 'populated' &&
            (width === 729 || width === 1280)
        ) {
            const cycle = await browser.evaluate(`(() => {
                const rect = (selector) => document.querySelector(selector)?.getBoundingClientRect();
                const orders = rect('[data-test^="summary-"]');
                const cancel = rect('[data-test^="cancel-"]');
                const controls = [
                    rect('[data-test^="cutoff-date-"]'),
                    rect('[data-test^="cutoff-time-"]'),
                    rect('[data-test^="set-cutoff-"]'),
                    rect('[data-test^="close-now-"]'),
                    rect('[data-test^="clear-cutoff-"]'),
                ];
                const openPanel = rect('.pilot-manager-page > section');
                const openHeading = rect('.pilot-manager-page > section h2');
                const cyclePanel = rect('.pilot-manager-page > ul.pilot-list');
                const cycleHeading = rect('[data-test^="cycle-"] > div:first-child > span:first-child');
                const cycleHeader = rect('[data-test^="cycle-"] > div:first-child');
                return {
                    actionGap: orders && cancel ? cancel.left - orders.right : null,
                    bottomSpread: controls.every(Boolean)
                        ? Math.max(...controls.map((box) => box.bottom)) -
                          Math.min(...controls.map((box) => box.bottom))
                        : null,
                    insetDifference: openPanel && openHeading && cyclePanel && cycleHeading
                        ? (cycleHeading.left - cyclePanel.left) -
                          (openHeading.left - openPanel.left)
                        : null,
                    topInsetDifference: openPanel && openHeading && cyclePanel && cycleHeader
                        ? (cycleHeader.top - cyclePanel.top) -
                          (openHeading.top - openPanel.top)
                        : null,
                };
            })()`);
            if (cycle.actionGap === null || cycle.actionGap > 16)
                issues.push(`Cycle header action gap: ${cycle.actionGap}px`);
            if (cycle.bottomSpread === null || cycle.bottomSpread > 2)
                issues.push(`Cycle cutoff bottom spread: ${cycle.bottomSpread}px`);
            if (cycle.insetDifference === null || Math.abs(cycle.insetDifference) > 1)
                issues.push(`Cycle card horizontal inset difference: ${cycle.insetDifference}px`);
            if (cycle.topInsetDifference === null || Math.abs(cycle.topInsetDifference) > 1)
                issues.push(`Cycle card top inset difference: ${cycle.topInsetDifference}px`);
        }
        if (
            ['stock', 'return-items'].includes(view.id) &&
            state.id === 'populated' &&
            mode === 'light' &&
            text === 100 &&
            managerTablePreviewCases.some(
                (dimensions) => dimensions.width === width,
            )
        ) {
            const row = await browser.evaluate(`(() => {
                const first = document.querySelector('.pilot-manager-table tbody tr');
                const cells = [...first.querySelectorAll('td')];
                return {
                    height: first.getBoundingClientRect().height,
                    columns: new Set(cells.map((cell) =>
                        Math.round(cell.getBoundingClientRect().left)
                    )).size,
                };
            })()`);
            if (row.columns < 2)
                issues.push(`Manager table card has ${row.columns} column`);
            const maxHeight =
                view.id === 'stock'
                    ? width < 700
                        ? 300
                        : 270
                    : width < 700
                      ? 230
                      : 190;
            if (row.height > maxHeight)
                issues.push(`Manager table card height: ${row.height}px`);
        }
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

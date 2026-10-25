import { spawn } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const chrome = spawn(
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    [
        '--headless=new',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--remote-debugging-port=0',
        '--user-data-dir=/private/tmp/dra191-visual-' + process.pid,
        'about:blank',
    ],
);
let stderr = '';
const endpoint = await new Promise((resolve, reject) => {
    chrome.stderr.on('data', (chunk) => {
        stderr += chunk;
        const match = stderr.match(/DevTools listening on (ws:\/\/[^\s]+)/);
        if (match) resolve(match[1]);
    });
    chrome.on('exit', (code) =>
        reject(new Error('Chrome exit ' + code + ' ' + stderr.slice(-1000))),
    );
    setTimeout(
        () => reject(new Error('No Chrome endpoint ' + stderr.slice(-1000))),
        10000,
    ).unref();
});

try {
    const port = new URL(endpoint).port;
    const targets = await (
        await fetch('http://127.0.0.1:' + port + '/json/list')
    ).json();
    const ws = new WebSocket(
        targets.find((target) => target.type === 'page').webSocketDebuggerUrl,
    );
    await new Promise((resolve) =>
        ws.addEventListener('open', resolve, { once: true }),
    );
    let id = 0;
    const pending = new Map();
    ws.addEventListener('message', (event) => {
        const message = JSON.parse(event.data);
        if (!message.id) return;
        const promise = pending.get(message.id);
        pending.delete(message.id);
        if (message.error) promise.reject(message.error);
        else promise.resolve(message.result);
    });
    const send = (method, params = {}) =>
        new Promise((resolve, reject) => {
            const number = ++id;
            pending.set(number, { resolve, reject });
            ws.send(JSON.stringify({ id: number, method, params }));
        });
    const evaluate = async (expression) =>
        (
            await send('Runtime.evaluate', {
                expression,
                awaitPromise: true,
                returnByValue: true,
            })
        ).result.value;
    await send('Page.enable');

    const cases = [];
    for (const scenario of [
        'dashboard',
        'wallet-member',
        'wallet-manager',
        'cutoff',
        'summary',
        'products',
        'create',
        'edit',
    ]) {
        for (const mode of ['light', 'dark']) {
            for (const width of [375, 1280])
                cases.push({ scenario, mode, width });
        }
    }
    cases.push(
        { scenario: 'wallet-member', mode: 'light', width: 375, empty: 1 },
        {
            scenario: 'wallet-manager',
            mode: 'dark',
            width: 375,
            error: 1,
            processing: 1,
        },
        {
            scenario: 'cutoff',
            mode: 'light',
            width: 375,
            error: 1,
            processing: 1,
        },
        { scenario: 'summary', mode: 'dark', width: 375, sent: 1 },
        { scenario: 'summary', mode: 'light', width: 375, empty: 1 },
        { scenario: 'products', mode: 'light', width: 375, empty: 1 },
        {
            scenario: 'create',
            mode: 'dark',
            width: 375,
            error: 1,
            processing: 1,
        },
        { scenario: 'wallet-member', mode: 'system', width: 375, text: 200 },
        { scenario: 'products', mode: 'light', width: 375, fallback: 1 },
    );
    for (const scenario of [
        'dashboard',
        'wallet-member',
        'wallet-manager',
        'cutoff',
        'summary',
        'products',
        'create',
        'edit',
    ]) {
        for (const mode of ['light', 'dark']) {
            cases.push({ scenario, mode, width: 375, text: 200 });
        }
        cases.push({ scenario, mode: 'light', width: 1280, text: 200 });
    }
    for (const scenario of ['wallet-member', 'wallet-manager']) {
        for (const width of [1280]) {
            for (const mode of ['light', 'dark']) {
                if (width === 1280 && mode === 'light') continue;
                cases.push({ scenario, mode, width, text: 200 });
            }
        }
    }
    for (const scenario of ['cutoff', 'create', 'edit']) {
        for (const width of [1280]) {
            for (const mode of ['light', 'dark']) {
                if (width === 1280 && mode === 'light') continue;
                cases.push({ scenario, mode, width, text: 200 });
            }
        }
    }
    for (const scenario of [
        'dashboard',
        'wallet-member',
        'wallet-manager',
        'cutoff',
        'summary',
        'products',
        'create',
        'edit',
    ]) {
        for (const mode of ['light', 'dark']) {
            cases.push({ scenario, mode, width: 1024, text: 200 });
        }
    }
    cases.push(
        { scenario: 'summary', mode: 'light', width: 375, long: 1 },
        { scenario: 'summary', mode: 'dark', width: 375, long: 1, sent: 1 },
    );
    for (const item of cases.filter((item) => {
        if (process.argv.includes('--dashboard-only'))
            return item.scenario === 'dashboard';
        if (process.argv.includes('--focus-only'))
            return (
                item.scenario === 'cutoff' &&
                item.mode === 'light' &&
                item.width === 375
            );
        if (process.argv.includes('--errors-only')) return item.error === 1;
        if (process.argv.includes('--review-only'))
            return item.text === 200 || item.long === 1;
        if (process.argv.includes('--wallet-review'))
            return (
                item.scenario.startsWith('wallet-') &&
                item.text === 200 &&
                item.width >= 1024
            );
        if (process.argv.includes('--summary-review'))
            return (
                item.scenario === 'summary' &&
                item.width === 1024 &&
                item.text === 200
            );
        return true;
    })) {
        await send('Emulation.setDeviceMetricsOverride', {
            width: item.width,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false,
        });
        const params = new URLSearchParams(item);
        const url =
            'http://127.0.0.1:5180/.vitest/dra191-visual/index.html?' + params;
        await send('Page.navigate', { url });
        let ready = false;
        for (let attempt = 0; attempt < 80; attempt++) {
            ready = await evaluate(
                `document.documentElement.dataset.fixtureQuery === ${JSON.stringify('?' + params)} && Boolean(document.querySelector('.pilot-page'))`,
            );
            if (ready) break;
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        if (!ready) throw new Error('Page did not render: ' + url);
        await evaluate('document.fonts.ready');
        const measured = await evaluate(`(() => {
            const main = document.querySelector('.pilot-page');
            const theme = document.querySelector('.pilot-theme');
            const controls = [...document.querySelectorAll('.pilot-page button, .pilot-page input:not([type=hidden]):not([type=checkbox]), .pilot-page a')];
            const checkboxLabel = document.querySelector('.pilot-page label[for=variable]');
            const error = document.querySelector('.pilot-page .text-red-600');
            return {
                width: innerWidth, scrollWidth: document.documentElement.scrollWidth,
                themed: !!theme, page: !!main, title: main?.querySelector('h2')?.textContent?.trim(),
                minControlHeight: controls.length ? Math.min(...controls.map(control => control.getBoundingClientRect().height)) : null,
                checkboxLabelHeight: checkboxLabel?.getBoundingClientRect().height ?? null,
                errorColor: error ? getComputedStyle(error).color : null,
                longName: (() => {
                    const name = document.querySelector('[data-test=product-bread] > span');
                    if (!name) return null;
                    const range = document.createRange();
                    range.selectNodeContents(name);
                    const textRight = range.getBoundingClientRect().right;
                    const panelRight = name.closest('.pilot-panel').getBoundingClientRect().right;
                    return { textRight, panelRight, clipped: textRight > panelRight - 1 };
                })(),
                summaryRows: [...document.querySelectorAll('.pilot-page li[data-test^=product-]')].map(row => {
                    const name = row.querySelector('span');
                    const form = row.querySelector('form');
                    const button = form?.querySelector('button');
                    const nameBox = name.getBoundingClientRect();
                    const rowBox = row.getBoundingClientRect();
                    const range = document.createRange();
                    range.selectNodeContents(name);
                    const nameText = range.getBoundingClientRect();
                    const formBox = form?.getBoundingClientRect();
                    const buttonBox = button?.getBoundingClientRect();
                    const buttonText = document.createRange();
                    if (button) buttonText.selectNodeContents(button);
                    const buttonTextBox = button ? buttonText.getBoundingClientRect() : null;
                    return { name: name.textContent.trim(), nameWidth: nameBox.width, nameLeft: nameBox.left, nameRight: nameBox.right, nameTextLeft: nameText.left, nameTextRight: nameText.right, rowLeft: rowBox.left, rowRight: rowBox.right, formLeft: formBox?.left ?? null, formRight: formBox?.right ?? null, buttonLeft: buttonBox?.left ?? null, buttonRight: buttonBox?.right ?? null, buttonTextLeft: buttonTextBox?.left ?? null, buttonTextRight: buttonTextBox?.right ?? null };
                }),
                escapedPanels: [...document.querySelectorAll('.pilot-page .pilot-panel')].map(panel => ({ left: panel.getBoundingClientRect().left, right: panel.getBoundingClientRect().right })).filter(rect => rect.left < -1 || rect.right > innerWidth + 1),
                escapedHeadings: [...document.querySelectorAll('.pilot-page h1, .pilot-page h2')].flatMap(heading => {
                    const box = heading.getBoundingClientRect();
                    const range = document.createRange();
                    range.selectNodeContents(heading);
                    const text = range.getBoundingClientRect();
                    if (box.width > 0 && text.left >= box.left - 1 && text.right <= box.right + 1 && text.left >= -1 && text.right <= innerWidth + 1) return [];
                    return [{ text: heading.textContent.trim().slice(0, 50), boxLeft: box.left, boxRight: box.right, textLeft: text.left, textRight: text.right }];
                }),
                escapedCells: [...document.querySelectorAll('.pilot-page .pilot-data-table tbody td')].map(cell => ({ left: cell.getBoundingClientRect().left, right: cell.getBoundingClientRect().right })).filter(rect => rect.left < -1 || rect.right > innerWidth + 1),
                productRowDisplay: (() => {
                    const row = document.querySelector('.pilot-product-table tbody tr');
                    return row ? getComputedStyle(row).display : null;
                })(),
                walletTable: (() => {
                    const table = document.querySelector('.pilot-page .pilot-data-table:not(.pilot-product-table)');
                    if (!table) return null;
                    const panel = table.closest('.pilot-panel');
                    const tableRight = table.getBoundingClientRect().right;
                    const panelRight = panel.getBoundingClientRect().right;
                    const contentRight = panelRight - parseFloat(getComputedStyle(panel).paddingRight);
                    const range = document.createRange();
                    let maxCellTextRight = 0;
                    for (const cell of table.querySelectorAll('tbody td')) {
                        range.selectNodeContents(cell);
                        maxCellTextRight = Math.max(maxCellTextRight, range.getBoundingClientRect().right);
                    }
                    const escapedAncestors = [];
                    for (let ancestor = table.parentElement; ancestor; ancestor = ancestor.parentElement) {
                        const overflow = getComputedStyle(ancestor).overflowX;
                        if (!['hidden', 'clip', 'auto', 'scroll'].includes(overflow)) continue;
                        const right = ancestor.getBoundingClientRect().right;
                        if (tableRight > right + 1 || maxCellTextRight > right + 1) escapedAncestors.push({ tag: ancestor.tagName, right, overflow });
                    }
                    return { tableRight, panelRight, contentRight, maxCellTextRight, escapedAncestors, rowDisplay: getComputedStyle(table.querySelector('tbody tr')).display };
                })(),
                managerForms: (() => {
                    const panels = [...document.querySelectorAll('.pilot-page [data-test=record-top-up], .pilot-page [data-test=record-correction]')].map(button => button.closest('.pilot-panel'));
                    if (!panels.length) return null;
                    const measurements = panels.map(panel => {
                        const panelRect = panel.getBoundingClientRect();
                        const style = getComputedStyle(panel);
                        const contentLeft = panelRect.left + parseFloat(style.paddingLeft);
                        const contentRight = panelRect.right - parseFloat(style.paddingRight);
                        const elements = [...panel.querySelectorAll('h2, label, input, button')].map(element => {
                            const box = element.getBoundingClientRect();
                            const textRange = document.createRange();
                            textRange.selectNodeContents(element);
                            const text = textRange.getBoundingClientRect();
                            return { tag: element.tagName, content: element.textContent.trim().slice(0, 50), boxLeft: box.left, boxRight: box.right, textLeft: text.left, textRight: text.right, clipped: box.left < contentLeft - 1 || box.right > contentRight + 1 || (text.width > 0 && (text.left < contentLeft - 1 || text.right > contentRight + 1)) };
                        });
                        return { panelLeft: panelRect.left, panelRight: panelRect.right, contentLeft, contentRight, elements };
                    });
                    return { columns: getComputedStyle(panels[0].parentElement).gridTemplateColumns, panels: measurements, clipped: measurements.flatMap(panel => panel.elements.filter(element => element.clipped)) };
                })(),
                clippedFormControls: [...document.querySelectorAll('.pilot-page .pilot-panel form, .pilot-page form.pilot-panel')].flatMap(form => {
                    const panel = form.closest('.pilot-panel');
                    const bounds = panel.getBoundingClientRect();
                    const style = getComputedStyle(panel);
                    const contentLeft = bounds.left + parseFloat(style.paddingLeft);
                    const contentRight = bounds.right - parseFloat(style.paddingRight);
                    return [...form.querySelectorAll('label, input, button')].filter(element => element.getClientRects().length > 0).flatMap(element => {
                        const box = element.getBoundingClientRect();
                        const range = document.createRange();
                        range.selectNodeContents(element);
                        const text = range.getBoundingClientRect();
                        if (box.left >= contentLeft - 1 && box.right <= contentRight + 1 && (text.width === 0 || (text.left >= contentLeft - 1 && text.right <= contentRight + 1))) return [];
                        return [{ tag: element.tagName, text: element.textContent.trim().slice(0, 40), contentLeft, contentRight, boxLeft: box.left, boxRight: box.right, textLeft: text.left, textRight: text.right }];
                    });
                }),
                background: theme ? getComputedStyle(theme).backgroundColor : null,
                errors: [...document.querySelectorAll('body vite-error-overlay')].map(node => node.textContent),
            };
        })()`);
        const suffix = Object.entries(item)
            .filter(([key]) => !['scenario', 'mode', 'width'].includes(key))
            .map(([key, value]) => '-' + key + value)
            .join('');
        const name = `${item.scenario}-${item.mode}-${item.width}${suffix}`;
        const shot = await send('Page.captureScreenshot', {
            format: 'png',
            captureBeyondViewport: false,
        });
        await writeFile(
            fileURLToPath(new URL(`./${name}.png`, import.meta.url)),
            Buffer.from(shot.data, 'base64'),
        );
        console.log(JSON.stringify({ name, ...measured }));
        if (measured.escapedPanels.length) {
            throw new Error(
                'Panel clips at viewport edge: ' +
                    name +
                    ' ' +
                    JSON.stringify(measured.escapedPanels),
            );
        }
        if (measured.escapedHeadings.length) {
            throw new Error(
                'Heading text clips: ' +
                    name +
                    ' ' +
                    JSON.stringify(measured.escapedHeadings),
            );
        }
        if (measured.escapedCells.length) {
            throw new Error(
                'Table cells clip at viewport edge: ' +
                    name +
                    ' ' +
                    JSON.stringify(measured.escapedCells),
            );
        }
        if (
            measured.walletTable &&
            (measured.walletTable.tableRight >
                measured.walletTable.contentRight + 1 ||
                measured.walletTable.maxCellTextRight >
                    measured.walletTable.contentRight + 1 ||
                measured.walletTable.escapedAncestors.length)
        ) {
            throw new Error(
                'Wallet table clips in its panel: ' +
                    name +
                    ' ' +
                    JSON.stringify(measured.walletTable),
            );
        }
        if (measured.walletTable && item.width >= 1024) {
            const expected = item.text === 200 ? 'block' : 'table-row';
            if (measured.walletTable.rowDisplay !== expected) {
                throw new Error(
                    'Wrong desktop wallet row layout: ' +
                        name +
                        ' ' +
                        measured.walletTable.rowDisplay,
                );
            }
        }
        if (measured.managerForms?.clipped.length) {
            throw new Error(
                'Manager wallet controls clip: ' +
                    name +
                    ' ' +
                    JSON.stringify(measured.managerForms),
            );
        }
        if (measured.clippedFormControls.length) {
            throw new Error(
                'Form controls clip: ' +
                    name +
                    ' ' +
                    JSON.stringify(measured.clippedFormControls),
            );
        }
        if (
            item.scenario === 'summary' &&
            item.width >= 1024 &&
            item.text === 200 &&
            !item.empty
        ) {
            const bad = measured.summaryRows.filter(
                (row) =>
                    row.nameWidth < 160 ||
                    row.nameTextLeft < row.nameLeft - 1 ||
                    row.nameTextRight > row.nameRight + 1 ||
                    (row.formLeft !== null &&
                        (row.formLeft < row.rowLeft - 1 ||
                            row.formRight > row.rowRight + 1 ||
                            row.buttonLeft < row.rowLeft - 1 ||
                            row.buttonRight > row.rowRight + 1 ||
                            row.buttonTextLeft < row.buttonLeft - 1 ||
                            row.buttonTextRight > row.buttonRight + 1)),
            );
            if (bad.length)
                throw new Error(
                    'Summary rows collapse or clip: ' +
                        name +
                        ' ' +
                        JSON.stringify(bad),
                );
        }
        if (measured.managerForms && item.width === 1280) {
            const count = measured.managerForms.columns
                .trim()
                .split(/\s+/).length;
            const expected = item.text === 200 ? 1 : 2;
            if (count !== expected)
                throw new Error(
                    'Wrong wallet form columns: ' +
                        name +
                        ' ' +
                        measured.managerForms.columns,
                );
        }
        if (
            item.scenario === 'products' &&
            item.width === 1280 &&
            !item.empty
        ) {
            const expected = item.text === 200 ? 'block' : 'table-row';
            if (measured.productRowDisplay !== expected) {
                throw new Error(
                    'Wrong desktop product row layout: ' +
                        name +
                        ' ' +
                        measured.productRowDisplay,
                );
            }
        }
        if (item.long === 1 && measured.longName?.clipped) {
            throw new Error('Long product name clips: ' + name);
        }
        if (item.text === 200) {
            const targetSelector =
                '.pilot-page a[href], .pilot-page button:not([disabled]), .pilot-page input:not([type=hidden]):not([disabled])';
            const targets = await evaluate(`(() => {
                const pageTargets = [...document.querySelectorAll(${JSON.stringify(targetSelector)})].filter(element => element.getClientRects().length > 0);
                return { context: pageTargets.length ? 'page' : 'shell', count: pageTargets.length || 1 };
            })()`);
            let first = null;
            let last = null;
            for (let tab = 0; tab < 120; tab++) {
                await send('Input.dispatchKeyEvent', {
                    type: 'keyDown',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                await send('Input.dispatchKeyEvent', {
                    type: 'keyUp',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                const state = await evaluate(`(() => {
                    const pageTargets = [...document.querySelectorAll(${JSON.stringify(targetSelector)})].filter(element => element.getClientRects().length > 0);
                    const targets = pageTargets.length ? pageTargets : [...document.querySelectorAll('.pilot-theme button:not([disabled])')].filter(element => element.getClientRects().length > 0).slice(0, 1);
                    const index = targets.indexOf(document.activeElement);
                    if (index < 0) return null;
                    const element = document.activeElement;
                    const style = getComputedStyle(element);
                    const rect = element.getBoundingClientRect();
                    const escapedAncestors = [];
                    for (let ancestor = element.parentElement; ancestor; ancestor = ancestor.parentElement) {
                        const overflow = getComputedStyle(ancestor).overflowX;
                        if (!['hidden', 'clip', 'auto', 'scroll'].includes(overflow)) continue;
                        const bounds = ancestor.getBoundingClientRect();
                        if (rect.left < bounds.left - 1 || rect.right > bounds.right + 1) escapedAncestors.push({ tag: ancestor.tagName, left: bounds.left, right: bounds.right, overflow });
                    }
                    return { index, tag: element.tagName, id: element.id, text: element.textContent.trim().slice(0, 40), focusVisible: element.matches(':focus-visible'), outlineWidth: style.outlineWidth, rectLeft: rect.left, rectRight: rect.right, rectTop: rect.top, rectBottom: rect.bottom, escapedAncestors, scrollWidth: document.documentElement.scrollWidth };
                })()`);
                if (!state) continue;
                if (
                    state.rectLeft < -1 ||
                    state.rectRight > item.width + 1 ||
                    state.escapedAncestors.length
                ) {
                    throw new Error(
                        'Keyboard target clips: ' +
                            name +
                            ' ' +
                            JSON.stringify(state),
                    );
                }
                if (state.index === 0 && !first) first = state;
                if (state.index !== targets.count - 1) continue;
                last = state;
                const focusedShot = await send('Page.captureScreenshot', {
                    format: 'png',
                    captureBeyondViewport: false,
                });
                await writeFile(
                    fileURLToPath(
                        new URL(`./${name}-focus-last.png`, import.meta.url),
                    ),
                    Buffer.from(focusedShot.data, 'base64'),
                );
                break;
            }
            if (
                !first ||
                !last ||
                !first.focusVisible ||
                !last.focusVisible ||
                parseFloat(first.outlineWidth) < 2 ||
                parseFloat(last.outlineWidth) < 2 ||
                last.rectLeft < -1 ||
                last.rectRight > item.width + 1 ||
                last.escapedAncestors.length ||
                last.scrollWidth > item.width
            ) {
                throw new Error(
                    'Keyboard focus failed: ' +
                        name +
                        ' ' +
                        JSON.stringify({ targets, first, last }),
                );
            }
            console.log(
                JSON.stringify({
                    name: name + '-keyboard',
                    context: targets.context,
                    count: targets.count,
                    first,
                    last,
                }),
            );
        }
        if (process.argv.includes('--focus-only')) {
            let focusFound = false;
            for (let tab = 0; tab < 20; tab++) {
                await send('Input.dispatchKeyEvent', {
                    type: 'keyDown',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                await send('Input.dispatchKeyEvent', {
                    type: 'keyUp',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                const activeId = await evaluate('document.activeElement.id');
                if (activeId !== 'days_before') continue;
                const focus = await evaluate(`(() => {
                    const input = document.activeElement;
                    const style = getComputedStyle(input);
                    return { id: input.id, focusVisible: input.matches(':focus-visible'), outlineWidth: style.outlineWidth, outlineColor: style.outlineColor };
                })()`);
                const focusedShot = await send('Page.captureScreenshot', {
                    format: 'png',
                    captureBeyondViewport: false,
                });
                await writeFile(
                    fileURLToPath(
                        new URL(
                            './cutoff-light-375-focus.png',
                            import.meta.url,
                        ),
                    ),
                    Buffer.from(focusedShot.data, 'base64'),
                );
                console.log(
                    JSON.stringify({
                        name: 'cutoff-light-375-focus',
                        ...focus,
                    }),
                );
                focusFound = true;
                break;
            }
            if (!focusFound)
                throw new Error('Keyboard Tab did not reach cutoff days field');
        }
    }
    ws.close();
} finally {
    chrome.kill();
}

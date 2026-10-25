import { spawn } from 'node:child_process';
import { writeFile } from 'node:fs/promises';

const chrome = spawn(
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    [
        '--headless=new',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--remote-debugging-port=0',
        '--user-data-dir=/private/tmp/dra192-visual-' + process.pid,
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
        reject(new Error('Chrome exit ' + code + ' ' + stderr.slice(-500))),
    );
    setTimeout(
        () => reject(new Error('No Chrome endpoint ' + stderr.slice(-500))),
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

    const views = [
        'accounts',
        'households',
        'household',
        'producers',
        'cycles',
        'cycle',
        'settings',
        'stock',
        'stock-create',
        'stock-edit',
        'movements',
        'returns',
        'return-create',
        'return-edit',
    ];
    const cases = views.flatMap((scenario) =>
        ['light', 'dark'].flatMap((mode) =>
            [375, 1280].map((width) => ({ scenario, mode, width })),
        ),
    );
    cases.push(
        ...views.flatMap((scenario) =>
            ['light', 'dark'].flatMap((mode) =>
                [375, 1024, 1280].map((width) => ({
                    scenario,
                    mode,
                    width,
                    text: 200,
                })),
            ),
        ),
        { scenario: 'accounts', mode: 'light', width: 375, empty: 1 },
        { scenario: 'households', mode: 'dark', width: 375, empty: 1 },
        { scenario: 'household', mode: 'dark', width: 375, inactive: 1 },
        { scenario: 'producers', mode: 'light', width: 375, empty: 1 },
        { scenario: 'cycles', mode: 'dark', width: 375, cancelled: 1 },
        { scenario: 'cycles', mode: 'dark', width: 375, dialog: 1 },
        { scenario: 'cycles', mode: 'light', width: 375, dialog: 1 },
        { scenario: 'cycles', mode: 'dark', width: 375, dialog: 1, hover: 1 },
        { scenario: 'cycles', mode: 'light', width: 375, dialog: 1, hover: 1 },
        ...['light', 'dark'].flatMap((mode) =>
            [undefined, 1].map((hover) => ({
                scenario: 'cycles',
                mode,
                width: 375,
                text: 200,
                dialog: 1,
                ...(hover ? { hover } : {}),
            })),
        ),
        { scenario: 'cycle', mode: 'dark', width: 375, cancelled: 1 },
        { scenario: 'stock', mode: 'light', width: 375, empty: 1 },
        { scenario: 'returns', mode: 'dark', width: 375, empty: 1 },
        {
            scenario: 'stock-create',
            mode: 'dark',
            width: 375,
            error: 1,
            processing: 1,
        },
        {
            scenario: 'movements',
            mode: 'light',
            width: 375,
            error: 1,
            processing: 1,
        },
        {
            scenario: 'return-create',
            mode: 'dark',
            width: 375,
            error: 1,
            processing: 1,
        },
        { scenario: 'settings', mode: 'light', width: 375, empty: 1 },
        { scenario: 'stock', mode: 'light', width: 375, long: 1 },
        { scenario: 'returns', mode: 'dark', width: 375, long: 1 },
        ...['stock', 'returns'].flatMap((scenario) =>
            ['light', 'dark'].map((mode) => ({
                scenario,
                mode,
                width: 1280,
                long: 1,
            })),
        ),
        { scenario: 'households', mode: 'light', width: 375, fallback: 1 },
        { scenario: 'wallet', mode: 'system', width: 375, text: 200 },
        { scenario: 'accounts', mode: 'light', width: 375, reduced: 1 },
        ...['light', 'dark'].map((mode) => ({
            scenario: 'cycles',
            mode,
            width: 375,
            text: 200,
            filled: 1,
        })),
        ...['light', 'dark'].map((mode) => ({
            scenario: 'movements',
            mode,
            width: 375,
            text: 200,
            selected: 1,
        })),
    );

    const findings = [];
    for (const item of cases) {
        if (
            process.argv.includes('--smoke') &&
            !(
                item.scenario === 'accounts' &&
                item.mode === 'light' &&
                item.width === 375 &&
                !item.text
            )
        )
            continue;
        if (
            process.argv.includes('--failed-only') &&
            !(
                ['accounts', 'household', 'cycles'].includes(item.scenario) &&
                item.width === 375 &&
                item.text === 200
            )
        )
            continue;
        if (
            process.argv.includes('--focus-only') &&
            !(item.width === 375 && item.text === 200)
        )
            continue;
        if (process.argv.includes('--dialog-only') && item.dialog !== 1)
            continue;
        if (
            process.argv.includes('--narrow-desktop') &&
            !(item.width === 1024 && item.text === 200)
        )
            continue;
        if (
            process.argv.includes('--long-desktop') &&
            !(item.width === 1280 && item.long === 1)
        )
            continue;
        if (
            process.argv.includes('--table-sweep') &&
            !(
                ['stock', 'returns'].includes(item.scenario) &&
                [375, 1280].includes(item.width)
            )
        )
            continue;
        if (
            process.argv.includes('--review-fixes') &&
            !(item.filled || item.selected)
        )
            continue;
        await send('Emulation.setDeviceMetricsOverride', {
            width: item.width,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false,
        });
        await send('Emulation.setEmulatedMedia', {
            features: [
                {
                    name: 'prefers-reduced-motion',
                    value: item.reduced ? 'reduce' : 'no-preference',
                },
            ],
        });
        const params = new URLSearchParams(item);
        const url =
            'http://127.0.0.1:5181/.vitest/dra192-visual/index.html?' + params;
        await send('Page.navigate', { url });
        let ready = false;
        for (let attempt = 0; attempt < 100; attempt++) {
            ready = await evaluate(
                `document.documentElement.dataset.fixtureQuery === ${JSON.stringify('?' + params)} && Boolean(document.querySelector('.pilot-page'))`,
            );
            if (ready) break;
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        if (!ready) throw new Error('Page did not render: ' + url);
        await evaluate('document.fonts.ready');
        if (item.filled) {
            await evaluate(`(() => {
                const legacy = document.querySelector('input[name="cutoff"][type="datetime-local"]');
                if (legacy) legacy.value = '2026-11-06T20:00';
                for (const [kind, value] of [['date', '2026-11-06'], ['time', '20:00']]) {
                    const input = document.querySelector('[data-test="cutoff-' + kind + '-cycle-grower"]');
                    if (!input) continue;
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                (document.querySelector('[data-test="cutoff-date-cycle-grower"]') ?? legacy)?.scrollIntoView({ block: 'center' });
            })()`);
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        if (item.selected) {
            await evaluate(`(() => {
                const select = document.querySelector('#adjustment-cycle');
                select.value = 'cycle';
                select.dispatchEvent(new Event('change', { bubbles: true }));
                select.scrollIntoView({ block: 'center' });
            })()`);
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        if (item.dialog) {
            await evaluate(
                "if (!document.querySelector('[data-slot=dialog-content]')) document.querySelector('[data-test=cancel-cycle]')?.click()",
            );
            let dialogReady = false;
            for (let attempt = 0; attempt < 40; attempt++) {
                dialogReady = await evaluate(
                    "Boolean(document.querySelector('[data-slot=dialog-content]'))",
                );
                if (dialogReady) break;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
            if (!dialogReady)
                throw new Error('Cancellation dialog did not open: ' + url);
            await new Promise((resolve) => setTimeout(resolve, 350));
            if (item.hover) {
                const center = await evaluate(`(() => {
                    const button = document.querySelector('[data-test="confirm-cancel-cycle"]');
                    const box = button.getBoundingClientRect();
                    return { x: box.left + box.width / 2, y: box.top + box.height / 2 };
                })()`);
                await send('Input.dispatchMouseEvent', {
                    type: 'mouseMoved',
                    x: center.x,
                    y: center.y,
                });
                await new Promise((resolve) => setTimeout(resolve, 250));
            }
        }
        const measured = await evaluate(`(() => {
            const page = document.querySelector('.pilot-page');
            const controls = [...page.querySelectorAll('a, button, input:not([type=hidden]):not([type=checkbox]), select')].filter(el => el.getClientRects().length);
            const escaped = [...page.querySelectorAll('h2, h3, p, li, form, input, select, button, a, td')].filter(el => {
                if (!el.getClientRects().length) return false;
                const box = el.getBoundingClientRect();
                return box.left < -1 || box.right > innerWidth + 1;
            }).slice(0, 8).map(el => ({ tag: el.tagName, text: el.textContent.trim().slice(0, 35), right: el.getBoundingClientRect().right }));
            const table = page.querySelector('.pilot-manager-table');
            return {
                title: page.querySelector('h2')?.textContent?.trim(),
                theme: !!document.querySelector('.pilot-theme'),
                scrollWidth: document.documentElement.scrollWidth,
                width: innerWidth,
                minControlHeight: controls.length ? Math.min(...controls.map(el => el.getBoundingClientRect().height)) : null,
                minControlWidth: controls.length ? Math.min(...controls.map(el => el.getBoundingClientRect().width)) : null,
                escaped,
                tableDisplay: table?.querySelector('tbody tr') ? getComputedStyle(table.querySelector('tbody tr')).display : null,
                error: !!page.querySelector('.text-red-600'),
                disabled: !!page.querySelector('button:disabled'),
                walletLink: page.querySelector('a[href*="/wallet"]')?.getAttribute('href') ?? null,
                reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
                dialog: (() => {
                    const dialog = document.querySelector('[data-slot=dialog-content]');
                    const button = dialog?.querySelector('[data-test^="confirm-cancel-"]');
                    const pixel = colors => {
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        for (const layer of colors) {
                            context.fillStyle = layer.color ?? layer;
                            context.globalAlpha = layer.opacity ?? 1;
                            context.fillRect(0, 0, 1, 1);
                        }
                        return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
                    };
                    const luminance = rgb => rgb.map(value => {
                        const channel = value / 255;
                        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
                    }).reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);
                    const foreground = button ? pixel([getComputedStyle(button).color]) : null;
                    const background = button ? pixel([
                        getComputedStyle(dialog).backgroundColor,
                        getComputedStyle(button).backgroundColor,
                    ]) : null;
                    const box = button?.getBoundingClientRect();
                    const hit = box ? document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2) : null;
                    const levels = foreground && background ? [luminance(foreground), luminance(background)].sort((a, b) => b - a) : null;
                    const title = dialog?.querySelector('[data-slot="dialog-title"]');
                    const close = dialog?.querySelector(':scope > [data-slot="dialog-close"]');
                    const geometry = element => {
                        const rect = element?.getBoundingClientRect();
                        return rect ? { left: rect.left, right: rect.right, top: rect.top,
                            bottom: rect.bottom, width: rect.width, height: rect.height } : null;
                    };
                    const overlaps = (a, b) => a && b && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
                    const titleBounds = geometry(title);
                    const closeBounds = geometry(close);
                    const iconBounds = geometry(close?.querySelector('svg'));
                    const footerTargets = dialog ? [...dialog.querySelectorAll('[data-slot="dialog-footer"] button')]
                        .map(control => ({ label: control.textContent.trim(), ...geometry(control) })) : [];
                    const otherControls = dialog ? [...dialog.querySelectorAll('button')]
                        .filter(control => control !== button)
                        .map(control => {
                            const style = getComputedStyle(control);
                            const layers = [getComputedStyle(dialog).backgroundColor,
                                { color: style.backgroundColor, opacity: Number(style.opacity) }];
                            const background = pixel(layers);
                            const foreground = pixel([...layers, { color: style.color, opacity: Number(style.opacity) }]);
                            const levels = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
                            return { label: control.textContent.trim() || control.getAttribute('aria-label') || 'Close',
                                contrast: (levels[0] + 0.05) / (levels[1] + 0.05) };
                        }) : [];
                    return dialog ? {
                        themed: dialog.classList.contains('pilot-theme'),
                        title: dialog.querySelector('[data-slot=dialog-title]')?.textContent?.trim(),
                        controls: [...dialog.querySelectorAll('button')].map(button => button.textContent.trim()),
                        destructive: levels ? { foreground, background, contrast: (levels[0] + 0.05) / (levels[1] + 0.05), hovered: button.matches(':hover'), hit: hit?.getAttribute('data-test') ?? hit?.tagName } : null,
                        otherControls,
                        dialogBounds: geometry(dialog),
                        scrollHeight: dialog.scrollHeight,
                        clientHeight: dialog.clientHeight,
                        titleBounds, closeBounds, iconBounds, footerTargets,
                        titleCloseOverlap: overlaps(titleBounds, closeBounds),
                        titleIconOverlap: overlaps(titleBounds, iconBounds),
                    } : null;
                })(),
                filledCutoff: (() => {
                    if (!${JSON.stringify(item.filled === 1)}) return null;
                    const date = page.querySelector('[data-test="cutoff-date-cycle-grower"]');
                    const time = page.querySelector('[data-test="cutoff-time-cycle-grower"]');
                    const hidden = page.querySelector('input[name="cutoff"][type="hidden"]');
                    const preview = page.querySelector('[data-test="cutoff-preview-cycle-grower"]');
                    const bounds = element => element ? {
                        left: element.getBoundingClientRect().left,
                        right: element.getBoundingClientRect().right,
                        width: element.getBoundingClientRect().width,
                        fontSize: parseFloat(getComputedStyle(element).fontSize),
                    } : null;
                    return {
                        date: date?.value ?? null, time: time?.value ?? null,
                        hidden: hidden?.value ?? null,
                        preview: preview?.textContent?.trim() ?? null,
                        dateBounds: bounds(date), timeBounds: bounds(time),
                        previewBounds: bounds(preview),
                        cardRight: date?.closest('[data-test^="cycle-"]')?.getBoundingClientRect().right ?? null,
                    };
                })(),
                selectedCycle: (() => {
                    if (!${JSON.stringify(item.selected === 1)}) return null;
                    const select = page.querySelector('#adjustment-cycle');
                    const form = select?.closest('form');
                    if (!select || !form) return null;
                    const box = select.getBoundingClientRect();
                    const formBox = form.getBoundingClientRect();
                    const style = getComputedStyle(form);
                    const preview = page.querySelector('[data-test="adjustment-cycle-preview"]');
                    return {
                        value: select.value,
                        option: select.selectedOptions[0]?.textContent?.trim(),
                        preview: preview?.textContent?.trim() ?? null,
                        previewRight: preview?.getBoundingClientRect().right ?? null,
                        left: box.left, right: box.right, width: box.width,
                        contentLeft: formBox.left + parseFloat(style.paddingLeft),
                        contentRight: formBox.right - parseFloat(style.paddingRight),
                        cardRight: formBox.right,
                    };
                })(),
                viteError: !!document.querySelector('vite-error-overlay'),
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
            new URL(name + '.png', import.meta.url),
            Buffer.from(shot.data, 'base64'),
        );
        if (item.dialog && item.text === 200) {
            await evaluate(`(() => {
                const dialog = document.querySelector('[data-slot="dialog-content"]');
                [...dialog.querySelectorAll('[data-slot="dialog-footer"] button')]
                    .find(button => button.textContent.trim() === 'Keep cycle').focus();
            })()`);
            await new Promise((resolve) => setTimeout(resolve, 300));
            measured.dialog.footerFocus = await evaluate(`(() => {
                const dialog = document.querySelector('[data-slot="dialog-content"]');
                const keep = [...dialog.querySelectorAll('[data-slot="dialog-footer"] button')]
                    .find(button => button.textContent.trim() === 'Keep cycle');
                const box = keep.getBoundingClientRect();
                const panel = dialog.getBoundingClientRect();
                return { active: document.activeElement === keep, top: box.top, bottom: box.bottom,
                    panelTop: panel.top, panelBottom: panel.bottom, scrollTop: dialog.scrollTop,
                    visible: box.top >= panel.top - 1 && box.bottom <= Math.min(panel.bottom, innerHeight) + 1 };
            })()`);
            const focusedShot = await send('Page.captureScreenshot', {
                format: 'png',
                captureBeyondViewport: false,
            });
            await writeFile(
                new URL(name + '-focus-keep.png', import.meta.url),
                Buffer.from(focusedShot.data, 'base64'),
            );
        }
        if (
            !measured.theme ||
            measured.scrollWidth > measured.width + 1 ||
            measured.escaped.length ||
            measured.viteError ||
            !measured.title ||
            (item.reduced === 1 && !measured.reducedMotion) ||
            (item.dialog === 1 &&
                (!measured.dialog?.themed ||
                    !measured.dialog.title ||
                    !measured.dialog.controls.includes('Keep cycle') ||
                    !measured.dialog.controls.includes('Cancel cycle') ||
                    !measured.dialog.destructive ||
                    measured.dialog.destructive.contrast < 4.5 ||
                    measured.dialog.titleCloseOverlap ||
                    measured.dialog.titleIconOverlap ||
                    measured.dialog.dialogBounds.top < -1 ||
                    measured.dialog.dialogBounds.bottom > 901 ||
                    (item.text === 200 &&
                        (!measured.dialog.footerFocus?.active ||
                            !measured.dialog.footerFocus.visible)) ||
                    measured.dialog.footerTargets.length !== 2 ||
                    measured.dialog.footerTargets.some(
                        (target) => target.width < 43.5 || target.height < 43.5,
                    ) ||
                    measured.dialog.otherControls.some(
                        (control) =>
                            control.contrast <
                            (control.label === 'Keep cycle' ? 4.5 : 3),
                    ) ||
                    (item.hover === 1 &&
                        !measured.dialog.destructive.hovered))) ||
            (item.scenario === 'household' &&
                measured.walletLink !== '/manage/households/home/wallet') ||
            (item.filled === 1 &&
                (measured.filledCutoff?.date !== '2026-11-06' ||
                    measured.filledCutoff?.time !== '20:00' ||
                    measured.filledCutoff?.hidden !== '2026-11-06T20:00' ||
                    measured.filledCutoff?.preview !== '2026-11-06 20:00' ||
                    measured.filledCutoff?.dateBounds?.fontSize < 31.5 ||
                    measured.filledCutoff?.timeBounds?.fontSize < 31.5 ||
                    measured.filledCutoff?.dateBounds?.right >
                        measured.filledCutoff?.cardRight + 1 ||
                    measured.filledCutoff?.timeBounds?.right >
                        measured.filledCutoff?.cardRight + 1 ||
                    measured.filledCutoff?.previewBounds?.right >
                        measured.filledCutoff?.cardRight + 1)) ||
            (item.selected === 1 &&
                (measured.selectedCycle?.value !== 'cycle' ||
                    measured.selectedCycle?.preview !== 'Pickup 2026-11-07' ||
                    measured.selectedCycle?.previewRight >
                        measured.selectedCycle?.contentRight + 1 ||
                    measured.selectedCycle?.right >
                        measured.selectedCycle?.contentRight + 1 ||
                    measured.selectedCycle?.right >
                        measured.selectedCycle?.cardRight + 1)) ||
            (measured.minControlHeight !== null &&
                measured.minControlHeight < 43.5) ||
            (measured.minControlWidth !== null &&
                measured.minControlWidth < 43.5)
        ) {
            findings.push({ name, ...measured });
        }
        if (process.argv.includes('--focus-only')) {
            const hasTarget = await evaluate(`(() => {
                const page = document.querySelector('[data-slot="dialog-content"]') ?? document.querySelector('.pilot-page');
                const controls = [...page.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled])')]
                    .filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
                const target = controls.at(-1) ?? document.querySelector('button');
                if (!target) return false;
                target.dataset.fixtureLastFocus = 'yes';
                return true;
            })()`);
            if (!hasTarget) throw new Error('No focus target: ' + name);
            let reached = false;
            for (let attempt = 0; attempt < 100; attempt++) {
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
                reached = await evaluate(
                    "document.activeElement?.dataset.fixtureLastFocus === 'yes'",
                );
                if (reached) break;
            }
            const focus = await evaluate(`(() => {
                const el = document.activeElement;
                const box = el.getBoundingClientRect();
                const style = getComputedStyle(el);
                return { reached: el.dataset.fixtureLastFocus === 'yes', visible: el.matches(':focus-visible'),
                    left: box.left, right: box.right, top: box.top, bottom: box.bottom,
                    outline: style.outlineWidth, shadow: style.boxShadow,
                };
            })()`);
            if (
                !reached ||
                !focus.visible ||
                focus.left < -1 ||
                focus.right > item.width + 1 ||
                focus.top < -1 ||
                focus.bottom > 901
            )
                findings.push({ name, focus });
            const focusedShot = await send('Page.captureScreenshot', {
                format: 'png',
                captureBeyondViewport: false,
            });
            await writeFile(
                new URL(name + '-focus-last.png', import.meta.url),
                Buffer.from(focusedShot.data, 'base64'),
            );
            process.stdout.write(
                name + '-focus ' + JSON.stringify(focus) + '\n',
            );
        }
        process.stdout.write(name + ' ' + JSON.stringify(measured) + '\n');
    }
    ws.close();
    if (findings.length)
        throw new Error('Visual checks failed: ' + JSON.stringify(findings));
} finally {
    chrome.kill();
}

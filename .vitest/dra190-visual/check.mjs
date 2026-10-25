import { spawn } from 'node:child_process';
import { writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const prefix = '/private/tmp/dra190-review-probe-' + process.pid;
const chrome = spawn(
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    [
        '--headless=new',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--remote-debugging-port=0',
        '--user-data-dir=' + prefix,
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
        reject(new Error('Chrome exit ' + code + ' ' + stderr.slice(-1500))),
    );
    setTimeout(
        () => reject(new Error('No endpoint ' + stderr.slice(-1500))),
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
    for (const [
        scenario,
        mode,
        reduced,
        width,
        name,
        textSize,
        fallback,
        overlayKind,
    ] of [
        ['index', 'light', false, 375, 'dra190-index-light-375-chrome.png'],
        ['index', 'dark', false, 375, 'dra190-index-dark-375-chrome.png'],
        ['index', 'dark', false, 1280, 'dra190-index-dark-1280-cdp.png'],
        ['edit', 'dark', false, 375, 'dra190-edit-dark-375-cdp.png'],
        [
            'index',
            'dark',
            false,
            1280,
            'dra190-dialog-dark-1280.png',
            null,
            false,
            'dialog',
        ],
        [
            'index',
            'dark',
            false,
            375,
            'dra190-sheet-dark-375-cdp.png',
            null,
            false,
            'sheet',
        ],
        [
            'index',
            'dark',
            false,
            1280,
            'dra190-menu-dark-1280-cdp.png',
            null,
            false,
            'menu',
        ],
        ['error', 'light', false, 375, 'dra190-error-light-375.png'],
        ['error', 'dark', false, 375, 'dra190-error-dark-375-cdp.png'],
        ['welcome', 'light', false, 375, 'dra190-welcome-light-375-chrome.png'],
        ['index', 'light', true, 375, 'dra190-index-light-reduced-375-cdp.png'],
        [
            'index',
            'light',
            true,
            375,
            'dra190-sheet-reduced-375-cdp.png',
            null,
            false,
            'sheet',
        ],
        ['edit', 'light', false, 1280, 'dra190-edit-light-1280-chrome.png'],
        ['edit', 'dark', false, 1280, 'dra190-edit-dark-1280-chrome.png'],
        ['edit', 'light', false, 1280, 'dra190-edit-light-1280.png'],
        ['edit', 'dark', false, 1280, 'dra190-edit-dark-1280.png'],
        ['closed', 'light', false, 375, 'dra190-closed-light-375.png'],
        ['marked', 'light', false, 375, 'dra190-marked-light-375.png'],
        ['pickup', 'light', false, 375, 'dra190-pickup-light-375-cdp.png'],
        ['settled', 'light', false, 375, 'dra190-settled-light-375.png'],
        ['saving', 'light', false, 375, 'dra190-saving-light-375.png'],
        ['error', 'light', false, 375, 'dra190-focus-light-375.png'],
        [
            'index',
            'light',
            false,
            375,
            'dra190-index-light-200pct-375.png',
            200,
        ],
        [
            'edit',
            'system',
            false,
            375,
            'dra190-edit-system-200pct-375.png',
            200,
        ],
        [
            'edit',
            'light',
            false,
            375,
            'dra190-fallback-light-375.png',
            100,
            true,
        ],
    ].filter(
        (row) =>
            !process.argv.includes('--dark-only') ||
            ['dark', 'system'].includes(row[1]),
    )) {
        await send('Emulation.setDeviceMetricsOverride', {
            width,
            height: 812,
            deviceScaleFactor: 1,
            mobile: false,
        });
        await send('Emulation.setEmulatedMedia', {
            features: [
                {
                    name: 'prefers-reduced-motion',
                    value: reduced ? 'reduce' : 'no-preference',
                },
                {
                    name: 'prefers-color-scheme',
                    value: mode === 'system' ? 'dark' : mode,
                },
            ],
        });
        await send('Page.navigate', {
            url:
                'http://127.0.0.1:5179/.vitest/dra190-visual/index.html?scenario=' +
                (scenario === 'saving' ? 'edit' : scenario) +
                '&mode=' +
                mode +
                '&controls=0' +
                (textSize ? '&text=' + textSize : '') +
                (fallback ? '&fallback=1' : '') +
                (scenario === 'saving' ? '&hold=1' : '') +
                ((reduced && overlayKind !== 'sheet') ||
                overlayKind === 'dialog'
                    ? '&overlay=1&open=dialog'
                    : ''),
        });
        for (let i = 0; i < 100; i++) {
            if (
                await evaluate(
                    "document.readyState === 'complete' && !!document.querySelector('#app > div') && (location.search.includes('scenario=welcome') || !!document.querySelector('.order-page-header'))",
                )
            )
                break;
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        await evaluate('document.fonts.ready');
        await evaluate('document.fonts.load(\'400 16px "Instrument Sans"\')');
        if (overlayKind === 'sheet') {
            await evaluate(
                "document.querySelector('[data-slot=sidebar-trigger]').click()",
            );
            await new Promise((resolve) => setTimeout(resolve, 650));
        }
        if (overlayKind === 'menu') {
            await evaluate(
                "document.querySelector('[data-test=sidebar-menu-button]').click()",
            );
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        const result = await evaluate(`(() => {
      const describe = selector => {
        const element = document.querySelector(selector);
        if (!element) return null;
        const box = element.getBoundingClientRect(), css = getComputedStyle(element);
        return { class: element.className, x: box.x, y: box.y, width: box.width, right: box.right,
          color: css.color, background: css.backgroundColor, border: css.borderColor,
          font: css.fontFamily, animation: css.animationDuration, transition: css.transitionDuration };
      };
      return { scenario: ${JSON.stringify(scenario)}, mode: ${JSON.stringify(mode)},
        inner: innerWidth, client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
        fonts: [...document.fonts].map(face => ({ family: face.family, status: face.status })),
        header: describe('.order-page-header'), card: describe('a[href="/cycles/open/order"]'),
        canvas: describe('[data-slot="sidebar-inset"]'),
        sidebar: describe('[data-sidebar="sidebar"]'),
        popover: describe('[data-slot="dropdown-menu-content"]'),
        mutedText: describe('.text-muted-foreground'),
        saveButton: describe('[data-test="save-bread"]'),
        input: describe('input'), error: describe('[role="alert"] p'),
        dialog: describe('[data-slot="dialog-content"]'), overlay: describe('[data-slot="dialog-overlay"]'),
        sheet: describe('[data-mobile="true"][data-sidebar="sidebar"]'), sheetOverlay: describe('[data-slot="sheet-overlay"]'),
        pilotNodes: document.querySelectorAll('.pilot-theme').length,
        inputCard: document.querySelector('input') ? getComputedStyle(document.querySelector('input').closest('section')).backgroundColor : null,
        welcomeBackground: document.querySelector('[class*="bg-[#FDFDFC]"]') ? getComputedStyle(document.querySelector('[class*="bg-[#FDFDFC]"]')).backgroundColor : null,
        reduced: matchMedia('(prefers-reduced-motion: reduce)').matches };
    })()`);
        console.log(JSON.stringify(result));
        if (scenario === 'saving') {
            await evaluate(`(() => {
        const input = document.querySelector('#quantity-bread');
        input.value = '2'; input.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
      })()`);
            await new Promise((resolve) => setTimeout(resolve, 100));
            await evaluate(
                "document.querySelector('[data-test=save-bread]').click()",
            );
        }
        if (scenario === 'error') {
            for (let tab = 0; tab < 30; tab++) {
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
                if (
                    await evaluate(
                        "document.activeElement?.id === 'quantity-bread'",
                    )
                )
                    break;
            }
            console.log(
                JSON.stringify(
                    await evaluate(`(() => {
        const input = document.querySelector('#quantity-bread');
        const css = getComputedStyle(input);
        return { scenario: ${JSON.stringify(scenario)}, mode: ${JSON.stringify(mode)},
          focused: document.activeElement === input, focusVisible: input.matches(':focus-visible'),
          border: css.borderColor, outline: css.outlineColor, outlineWidth: css.outlineWidth,
          boxShadow: css.boxShadow };
      })()`),
                ),
            );
        }
        if (scenario === 'welcome')
            await new Promise((resolve) => setTimeout(resolve, 900));
        const screenshot = await send('Page.captureScreenshot', {
            format: 'png',
        });
        const file = new URL(name, import.meta.url);
        await writeFile(file, Buffer.from(screenshot.data, 'base64'));
        console.log(fileURLToPath(file));
    }
    ws.close();
} finally {
    chrome.kill();
}

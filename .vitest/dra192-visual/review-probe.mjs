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
        '--user-data-dir=/private/tmp/dra192-review-' + process.pid,
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
    chrome.on('exit', (code) => reject(new Error('Chrome exit ' + code)));
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

    for (const scenario of ['cycles', 'movements']) {
        for (const mode of ['light', 'dark']) {
            await send('Emulation.setDeviceMetricsOverride', {
                width: 375,
                height: 900,
                deviceScaleFactor: 1,
                mobile: false,
            });
            const query = new URLSearchParams({
                scenario,
                mode,
                width: '375',
                text: '200',
            });
            await send('Page.navigate', {
                url:
                    'http://127.0.0.1:5181/.vitest/dra192-visual/index.html?' +
                    query,
            });
            for (let attempt = 0; attempt < 100; attempt++) {
                if (
                    await evaluate(
                        `document.documentElement.dataset.fixtureQuery === ${JSON.stringify('?' + query)} && Boolean(document.querySelector('.pilot-page'))`,
                    )
                )
                    break;
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            await evaluate('document.fonts.ready');
            const result = await evaluate(`(() => {
                const isCycles = ${JSON.stringify(scenario)} === 'cycles';
                const control = isCycles
                    ? document.querySelector('input[name="cutoff"]')
                    : document.querySelector('#adjustment-cycle');
                if (!control) return { error: 'control missing' };
                control.value = isCycles ? '2026-11-06T20:00' : 'cycle';
                control.dispatchEvent(new Event('input', { bubbles: true }));
                control.dispatchEvent(new Event('change', { bubbles: true }));
                control.scrollIntoView({ block: 'center', inline: 'nearest' });
                const form = control.closest('form');
                const formStyle = getComputedStyle(form);
                const formBox = form.getBoundingClientRect();
                const controlBox = control.getBoundingClientRect();
                const parentBox = control.parentElement.getBoundingClientRect();
                const label = isCycles
                    ? document.querySelector('input[name="cutoff"]')?.getAttribute('aria-label')
                    : document.querySelector('label[for="adjustment-cycle"]')?.textContent?.trim();
                const option = control.selectedOptions?.[0]?.textContent?.trim() ?? null;
                const computed = getComputedStyle(control);
                return {
                    mode: ${JSON.stringify(mode)}, scenario: ${JSON.stringify(scenario)},
                    value: control.value, option, label,
                    input: { left: controlBox.left, right: controlBox.right,
                        top: controlBox.top, bottom: controlBox.bottom,
                        width: controlBox.width, height: controlBox.height },
                    parent: { left: parentBox.left, right: parentBox.right, width: parentBox.width },
                    form: { left: formBox.left, right: formBox.right, width: formBox.width,
                        contentLeft: formBox.left + parseFloat(formStyle.paddingLeft),
                        contentRight: formBox.right - parseFloat(formStyle.paddingRight) },
                    fontSize: computed.fontSize, paddingLeft: computed.paddingLeft,
                    paddingRight: computed.paddingRight,
                    overflowX: computed.overflowX,
                    viewport: innerWidth, documentWidth: document.documentElement.scrollWidth,
                };
            })()`);
            const name = scenario + '-' + mode + '-375-text200-review';
            const shot = await send('Page.captureScreenshot', {
                format: 'png',
                captureBeyondViewport: false,
            });
            await writeFile(
                new URL(name + '.png', import.meta.url),
                Buffer.from(shot.data, 'base64'),
            );
            process.stdout.write(JSON.stringify(result) + '\n');
        }
    }
    ws.close();
} finally {
    chrome.kill();
}

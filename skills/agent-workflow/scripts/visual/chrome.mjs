import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

export async function openChrome(binary = process.env.CHROME_BIN) {
    if (!binary)
        throw new Error(
            'Set CHROME_BIN to an installed Chrome/Chromium executable',
        );
    const profile = await mkdtemp(join(tmpdir(), 'workflow-visual-'));
    const child = spawn(
        binary,
        [
            '--headless=new',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-gpu',
            '--remote-debugging-port=0',
            `--user-data-dir=${profile}`,
            'about:blank',
        ],
        { stdio: ['ignore', 'ignore', 'pipe'] },
    );
    let socket;
    const pending = new Map();
    const closed = new Promise((resolve) => {
        child.once('exit', resolve);
        child.once('error', resolve);
    });
    const close = async () => {
        socket?.close();
        for (const request of pending.values())
            request.reject(new Error('Browser closed'));
        pending.clear();
        child.kill();
        const force = setTimeout(() => child.kill('SIGKILL'), 2000);
        await closed;
        clearTimeout(force);
        // Chrome subprocesses can finish profile writes after the parent exits.
        await rm(profile, {
            recursive: true,
            force: true,
            maxRetries: 5,
            retryDelay: 100,
        });
    };
    try {
        const endpoint = await new Promise((resolve, reject) => {
            let output = '';
            const timeout = setTimeout(
                () => reject(new Error('Chrome startup timed out')),
                10000,
            );
            const finish = (fn, value) => {
                clearTimeout(timeout);
                fn(value);
            };
            child.once('error', (e) => finish(reject, e));
            child.once('exit', (code) =>
                finish(reject, new Error(`Chrome exited: ${code}`)),
            );
            child.stderr.on('data', (chunk) => {
                output = (output + chunk).slice(-4000);
                const match = output.match(
                    /DevTools listening on (ws:\/\/\S+)/,
                );
                if (match) finish(resolve, match[1]);
            });
        });
        const targets = await (
            await fetch(
                `http://127.0.0.1:${new URL(endpoint).port}/json/list`,
                { signal: AbortSignal.timeout(5000) },
            )
        ).json();
        socket = new WebSocket(
            targets.find((t) => t.type === 'page').webSocketDebuggerUrl,
        );
        await new Promise((resolve, reject) => {
            const timer = setTimeout(
                () => reject(new Error('CDP connection timed out')),
                5000,
            );
            socket.addEventListener(
                'open',
                () => {
                    clearTimeout(timer);
                    resolve();
                },
                { once: true },
            );
            socket.addEventListener(
                'error',
                () => {
                    clearTimeout(timer);
                    reject(new Error('CDP connection failed'));
                },
                { once: true },
            );
        });
        socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            const request = pending.get(message.id);
            if (!request) return;
            pending.delete(message.id);
            if (message.error) request.reject(new Error(message.error.message));
            else request.resolve(message.result);
        });
        socket.addEventListener('close', () => {
            for (const request of pending.values())
                request.reject(new Error('CDP disconnected'));
            pending.clear();
        });
        let id = 0;
        const send = (method, params = {}) =>
            new Promise((resolve, reject) => {
                const number = ++id;
                const timeout = setTimeout(() => {
                    pending.delete(number);
                    reject(new Error(`CDP timeout: ${method}`));
                }, 15000);
                pending.set(number, {
                    resolve: (value) => {
                        clearTimeout(timeout);
                        resolve(value);
                    },
                    reject: (error) => {
                        clearTimeout(timeout);
                        reject(error);
                    },
                });
                socket.send(JSON.stringify({ id: number, method, params }));
            });
        const evaluate = async (expression) => {
            const result = await send('Runtime.evaluate', {
                expression,
                awaitPromise: true,
                returnByValue: true,
            });
            if (result.exceptionDetails)
                throw new Error(
                    result.exceptionDetails.exception?.description ??
                        result.exceptionDetails.text,
                );
            return result.result.value;
        };
        await send('Page.enable');
        return { send, evaluate, close };
    } catch (error) {
        await close();
        throw error;
    }
}
export async function waitFor(browser, expression, description) {
    const deadline = Date.now() + 10000;
    while (Date.now() < deadline) {
        if (await browser.evaluate(expression)) return;
        await new Promise((resolve) => setTimeout(resolve, 50));
    }
    throw new Error(`Timed out: ${description}`);
}

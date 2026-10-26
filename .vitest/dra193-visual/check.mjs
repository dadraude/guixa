// Acceptance checks the shared harness does not measure: contrast, 44 px
// targets, appearance persistence, Enter submission and the Welcome probe.
// Usage: CHROME_BIN=... node .vitest/dra193-visual/check.mjs <output.jsonl>
import { writeFileSync } from 'node:fs';
import { openChrome } from '../../skills/agent-workflow/scripts/visual/chrome.mjs';

const base = 'http://127.0.0.1:5182/.vitest/dra193-visual/index.html';
const out = process.argv[2] ?? '/tmp/dra193-check.jsonl';
const results = [];
const record = (check, id, failures) => {
    results.push({ check, id, ok: failures.length === 0, failures });
    if (failures.length) console.log('FAIL', check, id, failures.slice(0, 4));
};
const browser = await openChrome();
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));

async function open(query, { width = 375, text = 100, dark = false } = {}) {
    await browser.send('Emulation.setDeviceMetricsOverride', {
        width,
        height: 900,
        deviceScaleFactor: 1,
        mobile: false,
    });
    await browser.send('Emulation.setEmulatedMedia', {
        features: [
            {
                name: 'prefers-color-scheme',
                value: dark ? 'dark' : 'light',
            },
        ],
    });
    await browser.send('Page.navigate', {
        url: `${base}?${query}&text=${text}`,
    });
    for (let i = 0; i < 200; i++) {
        await sleep(50);
        if (
            await browser
                .evaluate(`document.documentElement.dataset.visualReady`)
                .catch(() => null)
        )
            return;
    }
    throw new Error(`not ready: ${query}`);
}

const probe = `(() => {
  const canvas = document.createElement('canvas'); canvas.width = canvas.height = 1;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const rgba = (css) => { ctx.clearRect(0,0,1,1); ctx.fillStyle = '#000'; ctx.fillStyle = css; ctx.fillRect(0,0,1,1); const d = ctx.getImageData(0,0,1,1).data; return [d[0], d[1], d[2], d[3] / 255]; };
  const over = (top, bottom) => { const a = top[3]; return [0,1,2].map(i => top[i] * a + bottom[i] * (1 - a)).concat(1); };
  const backdrop = (el) => { const stack = []; for (let n = el; n; n = n.parentElement) { const c = rgba(getComputedStyle(n).backgroundColor); if (c[3] > 0) stack.push(c); if (c[3] === 1) break; } let color = [255,255,255,1]; for (const layer of stack.reverse()) color = over(layer, color); return color; };
  const lum = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x,y) + 0.05) / (Math.min(x,y) + 0.05); };
  const visible = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 1 && r.height > 1 && s.visibility !== 'hidden' && s.display !== 'none' && !el.closest('.sr-only,[aria-hidden="true"]') && Number(s.opacity) > 0; };
  const failures = [];
  const root = document.querySelector('[data-slot="dialog-content"]') ?? document.body;
  const scope = document.querySelector('[data-slot="dialog-content"]') ? [document.querySelector('[data-slot="dialog-content"]')] : [document.body];
  const texts = [];
  for (const container of scope) {
    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
    for (let node; (node = walker.nextNode());) { if (!node.textContent.trim()) continue; const el = node.parentElement; if (!visible(el) || el.closest('[disabled],[aria-disabled="true"],svg,input,option')) continue; texts.push(el); }
  }
  for (const el of new Set(texts)) {
    const s = getComputedStyle(el); const size = parseFloat(s.fontSize); const bold = Number(s.fontWeight) >= 700;
    const large = size >= 24 || (size >= 18.66 && bold);
    const fg = over(rgba(s.color), backdrop(el)); const r = ratio(fg, backdrop(el));
    if (r < (large ? 3 : 4.5)) failures.push('text ' + r.toFixed(2) + ' ' + el.tagName + ' ' + el.textContent.trim().slice(0, 30));
  }
  for (const el of document.querySelectorAll('input:not([type="hidden"]):not([aria-hidden="true"]):not([data-slot="input-otp"]), [data-slot="input-otp-slot"], button[role="checkbox"]')) {
    if (!visible(el)) continue; const s = getComputedStyle(el);
    const border = rgba(s.borderTopColor); if (parseFloat(s.borderTopWidth) === 0) continue;
    const r = ratio(over(border, backdrop(el.parentElement)), backdrop(el.parentElement));
    if (r < 3) failures.push('boundary ' + r.toFixed(2) + ' ' + (el.id || el.name || el.tagName));
  }
  const ring = getComputedStyle(document.documentElement).getPropertyValue('--ring');
  const focusable = document.querySelector('.pilot-theme');
  if (focusable) { const cs = getComputedStyle(focusable); const c = rgba(cs.getPropertyValue('--ring')); const b = rgba(cs.getPropertyValue('--background')); const rr = ratio(c, b); if (rr < 3) failures.push('focus ring ' + rr.toFixed(2)); }
  const small = [];
  for (const el of document.querySelectorAll('button, a[href], input:not([type="hidden"]):not([aria-hidden="true"]):not([data-slot="input-otp"]), [role="button"], select')) {
    if (!visible(el) || el.matches('[role="checkbox"],input[type="checkbox"]') || el.closest('.sr-only')) continue;
    const r = el.getBoundingClientRect();
    if (r.width < 43.5 || r.height < 43.5) small.push(el.tagName + ' ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' ' + (el.textContent || el.getAttribute('aria-label') || el.id).trim().slice(0, 24));
  }
  return { failures, small, themed: !!document.querySelector('.pilot-theme') };
})()`;

const pages = [
    ['login', 'scenario=login'],
    ['login-error', 'scenario=login&error=1'],
    ['forgot', 'scenario=forgot-password'],
    ['reset', 'scenario=reset-password'],
    ['confirm', 'scenario=confirm-password'],
    ['otp', 'scenario=two-factor-challenge'],
    ['recovery', 'scenario=two-factor-challenge&recovery=1'],
    ['verify', 'scenario=verify-email&status=1'],
    ['accept', 'scenario=accept-invitation&error=1'],
    ['profile', 'scenario=settings-profile&role=manager&unverified=1&status=1'],
    ['profile-dialog', 'scenario=settings-profile&dialog=1&error=1'],
    ['security', 'scenario=settings-security&role=manager&twofactor=1'],
    ['security-setup', 'scenario=settings-security&setup=1'],
    ['security-remove', 'scenario=settings-security&dialog=1'],
    ['appearance', 'scenario=settings-appearance&role=producer'],
    ['orders-index', 'scenario=regression-orders-index'],
    ['orders-edit', 'scenario=regression-orders-edit&order=ordered'],
    ['orders-marked', 'scenario=regression-orders-edit&order=marked'],
];
const settingsPages = new Set(['profile', 'security', 'appearance']);
for (const dark of [false, true]) {
    for (const [id, query] of pages) {
        for (const width of [375, 1280]) {
            const mode = dark ? 'dark' : 'light';
            await open(`${query}&mode=${mode}`, { width, dark });
            const { failures, small, themed } = await browser.evaluate(probe);
            const name = `${id}-${mode}-${width}`;
            if (settingsPages.has(id)) {
                const selected = await browser.evaluate(
                    `document.querySelectorAll('.pilot-settings-nav [aria-current="page"]').length`,
                );
                if (selected !== 1)
                    failures.push(
                        `selected settings navigation items: ${selected}`,
                    );
            }
            record('contrast-boundary', name, failures);
            record('target-44', name, themed ? small : ['page not themed']);
        }
    }
}

// The Orders rows were changed (container-width columns): contrast and targets at the
// widths around the container threshold and at enlarged text.
for (const dark of [false, true]) {
    for (const [id, query] of [
        [
            'orders-edit-ordered',
            'scenario=regression-orders-edit&order=ordered&dirty=1',
        ],
        ['orders-edit-marked', 'scenario=regression-orders-edit&order=marked'],
        ['orders-index', 'scenario=regression-orders-index'],
    ]) {
        for (const [width, text] of [
            [1024, 100],
            [1024, 200],
            [375, 200],
        ]) {
            const mode = dark ? 'dark' : 'light';
            await open(`${query}&mode=${mode}`, { width, text, dark });
            const { failures, small } = await browser.evaluate(probe);
            const name = `${id}-${mode}-${width}-${text}`;
            record('contrast-boundary', name, failures);
            record('target-44', name, small);
        }
    }
}

// The settings gutter keeps 1rem at normal text and only narrows when it must.
for (const scenario of [
    'settings-profile',
    'settings-security',
    'settings-appearance',
]) {
    for (const [text, minimum] of [
        [100, 16],
        [200, 16],
    ]) {
        await open(`scenario=${scenario}&mode=light`, { width: 375, text });
        const gutter = await browser.evaluate(
            `parseFloat(getComputedStyle(document.querySelector('.pilot-settings-inner')).paddingLeft)`,
        );
        record(
            'settings-gutter',
            `${scenario}-${text}`,
            gutter >= minimum ? [] : [`gutter ${gutter}px < ${minimum}px`],
        );
    }
}

// The setup modal QR keeps a usable, unclipped square (Fortify emits a fixed 192 x 192 SVG),
// and its separator label matches the dialog surface.
for (const dark of [false, true]) {
    for (const [width, text] of [
        [375, 100],
        [375, 200],
        [1280, 100],
    ]) {
        await open(
            `scenario=settings-security&setup=1&mode=${dark ? 'dark' : 'light'}`,
            {
                width,
                text,
                dark,
            },
        );
        const info = await browser.evaluate(`(() => {
            const dialog = document.querySelector('[data-slot="dialog-content"]');
            const svg = dialog.querySelector('svg[viewBox="0 0 21 21"]');
            if (!svg) return { missing: true };
            const box = svg.getBoundingClientRect();
            const clipped = [];
            for (let n = svg.parentElement; n && n !== dialog; n = n.parentElement) {
                if (getComputedStyle(n).overflow === 'visible') continue;
                const r = n.getBoundingClientRect();
                if (box.left < r.left - 1 || box.right > r.right + 1 || box.top < r.top - 1 || box.bottom > r.bottom + 1) clipped.push(n.className.slice(0, 40));
            }
            const dialogBox = dialog.getBoundingClientRect();
            const style = getComputedStyle(dialog);
            const content = dialogBox.width - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const rightLimit = dialogBox.right - parseFloat(style.paddingRight);
            const leftLimit = dialogBox.left + parseFloat(style.paddingLeft);
            if (box.right > rightLimit + 1 || box.left < leftLimit - 1) clipped.push('dialog content edge');
            const frame = svg.closest('.aspect-square.rounded-lg');
            const frameBox = frame?.getBoundingClientRect();
            if (!frameBox || frameBox.right > rightLimit + 1 || frameBox.left < leftLimit - 1)
                clipped.push('QR frame beyond dialog content');
            const label = [...dialog.querySelectorAll('span')].find((s) => s.textContent.includes('enter the code manually'));
            const pick = (el) => getComputedStyle(el).backgroundColor;
            return { width: box.width, height: box.height, content, clipped, labelBackground: label && pick(label), dialogBackground: pick(dialog) };
        })()`);
        const failures = [];
        if (info.missing) failures.push('QR svg missing');
        else {
            if (info.clipped.length)
                failures.push('QR clipped by ' + info.clipped.join(','));
            const minimum = Math.min(160, info.content * 0.9);
            if (info.width < minimum || Math.abs(info.width - info.height) > 1)
                failures.push(
                    `QR ${info.width}x${info.height} < ${minimum} (content ${info.content})`,
                );
            if (info.labelBackground !== info.dialogBackground)
                failures.push(
                    `separator ${info.labelBackground} on ${info.dialogBackground}`,
                );
        }
        record(
            'setup-qr',
            `${dark ? 'dark' : 'light'}-${width}-${text}`,
            failures,
        );
    }
}

// Every "or ..." separator label masks its rule with the surface it sits on.
for (const dark of [false, true]) {
    for (const [id, query, text] of [
        ['login', 'scenario=login', 'continue with email'],
        [
            'confirm-password',
            'scenario=confirm-password',
            'confirm with password',
        ],
    ]) {
        await open(`${query}&mode=${dark ? 'dark' : 'light'}`, { dark });
        const info = await browser.evaluate(`(() => {
            const label = [...document.querySelectorAll('span')].find((s) => s.textContent.toLowerCase().includes('${text}'));
            const surface = label?.closest('.pilot-panel');
            return label && surface ? [getComputedStyle(label).backgroundColor, getComputedStyle(surface).backgroundColor] : null;
        })()`);
        record(
            'separator-surface',
            `${id}-${dark ? 'dark' : 'light'}`,
            info && info[0] === info[1]
                ? []
                : [`separator ${info?.[0]} on ${info?.[1]}`],
        );
    }
}

// Appearance selection and persistence on the real Appearance page.
async function appearanceState() {
    return browser.evaluate(`({
        dark: document.documentElement.classList.contains('dark'),
        stored: localStorage.getItem('appearance'),
        cookie: document.cookie.includes('appearance=' ) ? document.cookie.match(/appearance=([a-z]+)/)[1] : null,
        pressed: [...document.querySelectorAll('button[aria-pressed="true"]')].map((b) => b.textContent.trim()),
    })`);
}
async function clickTab(label) {
    await browser.evaluate(
        `[...document.querySelectorAll('button[aria-pressed]')].find((b) => b.textContent.trim() === '${label}').click()`,
    );
    await sleep(100);
}
{
    const persist = 'scenario=settings-appearance&persist=1';
    await open(persist, { dark: false });
    await browser.evaluate(
        'localStorage.clear(); document.cookie="appearance=;path=/;max-age=0"',
    );
    await open(persist, { dark: false });
    const failures = [];
    let s = await appearanceState();
    if (s.dark || !s.pressed.includes('System'))
        failures.push('default system/light ' + JSON.stringify(s));
    await clickTab('Dark');
    s = await appearanceState();
    if (!s.dark || s.stored !== 'dark' || s.cookie !== 'dark')
        failures.push('dark click ' + JSON.stringify(s));
    await open(persist, { dark: false });
    s = await appearanceState();
    if (!s.dark || !s.pressed.includes('Dark'))
        failures.push('dark persisted ' + JSON.stringify(s));
    await clickTab('Light');
    await open(persist, { dark: true });
    s = await appearanceState();
    if (s.dark || s.stored !== 'light' || !s.pressed.includes('Light'))
        failures.push('light persisted over OS dark ' + JSON.stringify(s));
    await clickTab('System');
    s = await appearanceState();
    if (!s.dark || s.stored !== 'system' || s.cookie !== 'system')
        failures.push('system follows OS dark ' + JSON.stringify(s));
    await open(persist, { dark: false });
    s = await appearanceState();
    if (s.dark || !s.pressed.includes('System'))
        failures.push('system follows OS light ' + JSON.stringify(s));
    record('appearance-persistence', 'appearance', failures);
}

// Enter submission through real key events on the native form.
async function press(key, code) {
    await browser.send('Input.dispatchKeyEvent', {
        type: 'keyDown',
        key,
        code,
        windowsVirtualKeyCode: 13,
        text: '\r',
    });
    await browser.send('Input.dispatchKeyEvent', {
        type: 'keyUp',
        key,
        code,
        windowsVirtualKeyCode: 13,
    });
}
const enterCases = [
    [
        'login',
        'scenario=login',
        ['#email', 'a@example.test'],
        ['#password', 'secret-test'],
    ],
    [
        'forgot-password',
        'scenario=forgot-password',
        ['#email', 'a@example.test'],
    ],
    [
        'reset-password',
        'scenario=reset-password',
        ['#password', 'secret-test'],
        ['#password_confirmation', 'secret-test'],
    ],
    [
        'confirm-password',
        'scenario=confirm-password',
        ['#password', 'secret-test'],
    ],
    [
        'accept-invitation',
        'scenario=accept-invitation',
        ['#password', 'secret-test'],
        ['#password_confirmation', 'secret-test'],
    ],
    [
        'recovery-code',
        'scenario=two-factor-challenge&recovery=1',
        ['input[name="recovery_code"]', 'abcde-12345'],
    ],
    ['otp', 'scenario=two-factor-challenge', ['#otp', '123456']],
    ['profile', 'scenario=settings-profile', ['#name', 'Maria Test']],
    [
        'security-password',
        'scenario=settings-security',
        ['#current_password', 'old-test'],
        ['#password', 'new-test'],
        ['#password_confirmation', 'new-test'],
    ],
];
for (const [id, query, ...fields] of enterCases) {
    await open(query);
    await browser.evaluate(
        `window.__requests = []; window.addEventListener('fixture-request', (e) => window.__requests.push(e.detail));`,
    );
    for (const [selector, value] of fields) {
        await browser.evaluate(`document.querySelector('${selector}').focus()`);
        await browser.send('Input.insertText', { text: value });
    }
    await press('Enter', 'Enter');
    await sleep(200);
    const requests = await browser.evaluate('window.__requests');
    const failures = [];
    if (requests.length !== 1) failures.push('requests ' + requests.length);
    record('enter-submission', id, failures);
}

// Welcome stays outside the theme.
for (const dark of [false, true]) {
    await open(`scenario=welcome&mode=${dark ? 'dark' : 'light'}`, { dark });
    const info = await browser.evaluate(`({
        themed: document.querySelectorAll('.pilot-theme').length,
        heading: getComputedStyle(document.querySelector('h1')).fontFamily,
        background: getComputedStyle(document.querySelector('body > #app > div')).backgroundColor,
        primary: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
    })`);
    const expectedBackground = dark ? 'rgb(10, 10, 10)' : 'rgb(253, 253, 252)';
    const failures = [];
    if (info.themed) failures.push('has .pilot-theme');
    if (info.background !== expectedBackground)
        failures.push('background ' + info.background);
    if (info.primary.startsWith('#'))
        failures.push('theme token on root ' + info.primary);
    record('welcome-unchanged', dark ? 'dark' : 'light', failures);
}

await browser.close();
writeFileSync(out, results.map((r) => JSON.stringify(r)).join('\n') + '\n');
const failed = results.filter((r) => !r.ok);
console.log(
    `${results.length - failed.length}/${results.length} checks passed`,
);
process.exit(failed.length ? 1 : 0);

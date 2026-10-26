// Acceptance checks the shared harness does not measure: 44 px hit areas,
// 8 px between adjacent targets, one shared left edge, end-aligned tabular
// amounts and no horizontal overflow.
// Usage: CHROME_BIN=... node .vitest/dra197-visual/check.mjs <output.jsonl>
import { writeFileSync } from 'node:fs';
import { openChrome } from '../../skills/agent-workflow/scripts/visual/chrome.mjs';

const base = 'http://127.0.0.1:5183/.vitest/dra197-visual/index.html';
const out = process.argv[2] ?? '/tmp/dra197-check.jsonl';
const results = [];
const browser = await openChrome();
const sleep = (ms) => new Promise((done) => setTimeout(done, ms));

async function open(query, { width, text, dark }) {
    await browser.send('Emulation.setDeviceMetricsOverride', {
        width,
        height: 900,
        deviceScaleFactor: 1,
        mobile: false,
    });
    await browser.send('Page.navigate', {
        url: `${base}?${query}&mode=${dark ? 'dark' : 'light'}&text=${text}`,
    });
    for (let i = 0; i < 200; i++) {
        await sleep(50);
        const ready = await browser
            .evaluate(`document.documentElement.dataset.visualReady`)
            .catch(() => null);
        if (ready) return;
    }
    throw new Error(`not ready: ${query}`);
}

const moneyChecks = `  // Every money-only leaf (amounts, credits, charges) uses tabular figures,
  // is end-aligned and, unless an action follows it, ends on its row's content edge.
  const money = [...scopeRoots.flatMap((root) => [...root.querySelectorAll('*')])].filter((el) => !el.children.length && visible(el) && /^[−-]?\\d[\\d.]*,\\d{2}\\s?€$/.test(el.textContent.trim()));
  if (!money.length && requireMoney) failures.push('no money leaves found');
  for (const el of money) {
    const s = getComputedStyle(el);
    if (!s.fontVariantNumeric.includes('tabular-nums')) failures.push('money figures ' + label(el));
    if (!['end', 'right'].includes(s.textAlign)) failures.push('money alignment ' + label(el));
    if (el.nextElementSibling?.matches('button, a')) continue;
    const parent = el.parentElement;
    const ps = getComputedStyle(parent);
    const edge = parent.getBoundingClientRect().right - parseFloat(ps.paddingRight) - parseFloat(ps.borderRightWidth);
    if (Math.abs(el.getBoundingClientRect().right - edge) > 0.5) failures.push('money end edge ' + (el.getBoundingClientRect().right - edge).toFixed(1) + ' ' + label(el) + ' ' + el.textContent.trim());
  }
  for (const figures of scopeRoots.flatMap((root) => [...root.querySelectorAll('.pilot-list-figures')])) {
    if (!visible(figures)) continue;
    const row = figures.parentElement;
    const rs = getComputedStyle(row);
    const edge = row.getBoundingClientRect().right - parseFloat(rs.paddingRight);
    if (Math.abs(figures.getBoundingClientRect().right - edge) > 0.5) failures.push('figures not on the row end edge');
    for (const item of figures.children) {
      if (Math.abs(item.getBoundingClientRect().right - figures.getBoundingClientRect().right) > 0.5 && item === figures.lastElementChild) failures.push('last figure not at end');
    }
  }
`;

const helpers = `
  const failures = [];
  const visible = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 1 && r.height > 1 && s.visibility !== 'hidden' && !el.closest('.sr-only,[aria-hidden="true"]'); };
  const label = (el) => el.tagName + ' ' + (el.getAttribute('aria-label') || el.dataset.test || el.textContent.trim().slice(0, 20));`;

const pageChecks = `
  const targets = [...document.querySelectorAll('a[href], button, input:not([type="hidden"]), select, [role="button"]')].filter(visible);
  for (const el of targets) {
    const r = el.getBoundingClientRect();
    if (r.width < 43.5 || r.height < 43.5) failures.push('target ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' ' + label(el));
  }
  for (let i = 0; i < targets.length; i++) for (let j = i + 1; j < targets.length; j++) {
    const a = targets[i], b = targets[j];
    if (a.contains(b) || b.contains(a)) continue;
    const p = a.getBoundingClientRect(), q = b.getBoundingClientRect();
    const gx = Math.max(0, p.left - q.right, q.left - p.right);
    const gy = Math.max(0, p.top - q.bottom, q.top - p.bottom);
    if (gx < 7.5 && gy < 7.5) failures.push('gap ' + Math.round(Math.max(gx, gy)) + ' ' + label(a) + ' | ' + label(b));
  }
  const scroller = document.scrollingElement;
  if (scroller.scrollWidth > scroller.clientWidth) failures.push('horizontal overflow ' + scroller.scrollWidth + '>' + scroller.clientWidth);
  const page = document.querySelector('main > .pilot-layout-page');
  if (page) {
    const edge = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return el.matches('a') ? r.left + parseFloat(s.paddingLeft) : r.left; };
    const lefts = [...page.children].filter(visible).map((el) => [label(el), edge(el)]);
    const first = lefts[0]?.[1];
    for (const [name, left] of lefts) if (Math.abs(left - first) > 0.5) failures.push('left edge ' + left.toFixed(1) + ' vs ' + first.toFixed(1) + ' ' + name);
  }
  for (const el of document.querySelectorAll('.pilot-amount')) {
    if (!visible(el)) continue;
    const s = getComputedStyle(el);
    if (!['end', 'right'].includes(s.textAlign)) failures.push('amount alignment ' + s.textAlign + ' ' + label(el));
    if (!s.fontVariantNumeric.includes('tabular-nums')) failures.push('amount figures ' + label(el));
  }
  const lineAmounts = [...document.querySelectorAll('[data-test="pickup-line-amount"], [data-test="pickup-total"]')].filter(visible);
  if (lineAmounts.length) {
    const rights = lineAmounts.map((el) => el.getBoundingClientRect().right);
    if (Math.max(...rights) - Math.min(...rights) > 0.5) failures.push('pickup amounts not on one end edge ' + rights.map((v) => v.toFixed(1)));
  }
  const rowAmounts = [...document.querySelectorAll('li .pilot-amount')].filter(visible).map((el) => el.getBoundingClientRect().right);
  if (rowAmounts.length > 1 && !lineAmounts.length && document.querySelector('[data-test^="quantity-"]')) {
    const wide = window.innerWidth > 1000;
    if (wide && Math.max(...rowAmounts) - Math.min(...rowAmounts) > 0.5) failures.push('row amounts not on one end edge ' + rowAmounts.map((v) => v.toFixed(1)));
  }
`;

// The whole page: targets, overflow, left edge and every money value.
const probe = `(() => {${helpers}
  const scopeRoots = [document.querySelector('main')];
  const requireMoney = !!document.querySelector('[data-test="order-total"]');
${pageChecks}
  ${moneyChecks}
  return failures;
})()`;

// Only the pickup lists, for Manage/Pickups/Show, whose own form overflows at
// 375 px/200% (pre-existing, T41): measure just what the shared rules own.
const pickupListProbe = `(() => {${helpers}
  const scopeRoots = [...document.querySelectorAll('[data-test="stock-lines"], [data-test="return-lines"], [data-test="pickup-adjustments"]')];
  const requireMoney = true;
  ${moneyChecks}
  return failures;
})()`;

const cases = [
    ['orders-index', 'scenario=orders-index'],
    ['orders-index-empty', 'scenario=orders-index&empty=1'],
    ['orders-edit', 'scenario=orders-edit&dirty=1'],
    ['orders-edit-marked', 'scenario=orders-edit&order=marked'],
    ['orders-edit-pickup', 'scenario=orders-edit&order=pickup'],
    ['orders-edit-long', 'scenario=orders-edit&long=1&dirty=1'],
    ['orders-edit-error', 'scenario=orders-edit&error=1'],
];
const pickupListCases = [
    { width: 375, text: 200, dark: false },
    { width: 375, text: 200, dark: true },
    { width: 1280, text: 100, dark: false },
    { width: 1280, text: 100, dark: true },
];
const viewports = [
    { width: 375, text: 100, dark: false },
    { width: 375, text: 200, dark: true },
    { width: 1024, text: 200, dark: false },
    { width: 1280, text: 100, dark: true },
];
for (const [id, query] of cases)
    for (const viewport of viewports) {
        await open(query, viewport);
        const failures = await browser.evaluate(probe);
        const name = `${id}@${viewport.width}/${viewport.text}/${viewport.dark ? 'dark' : 'light'}`;
        results.push({
            check: 'layout',
            id: name,
            ok: failures.length === 0,
            failures,
        });
        if (failures.length) console.log('FAIL', name, failures.slice(0, 5));
    }

for (const viewport of pickupListCases) {
    await open('scenario=pickup-show', viewport);
    const failures = await browser.evaluate(pickupListProbe);
    const name = `pickup-show-lists@${viewport.width}/${viewport.text}/${viewport.dark ? 'dark' : 'light'}`;
    results.push({
        check: 'pickup-lists',
        id: name,
        ok: failures.length === 0,
        failures,
    });
    if (failures.length) console.log('FAIL', name, failures.slice(0, 5));
}

await browser.close();
writeFileSync(out, results.map((r) => JSON.stringify(r)).join('\n') + '\n');
const failed = results.filter((r) => !r.ok);
console.log(
    `${results.length - failed.length}/${results.length} checks passed`,
);
process.exit(failed.length ? 1 : 0);

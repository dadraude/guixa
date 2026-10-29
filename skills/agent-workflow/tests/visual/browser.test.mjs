import assert from 'node:assert/strict';
import { test, before, after } from 'node:test';
import { openChrome } from '../../scripts/visual/chrome.mjs';
import { measureLayout, measureFocus } from '../../scripts/visual/measure.mjs';
import { measureAssertions } from '../../scripts/visual/assertions.mjs';
let browser;
before(async () => {
    browser = await openChrome();
});
after(async () => {
    await browser?.close();
});
const options = {
    root: 'main',
    panels: '.panel',
    content: 'h1,label,button,td,[data-visual-text]',
};
async function render(css, markup) {
    await browser.send('Emulation.setDeviceMetricsOverride', {
        width: 375,
        height: 600,
        deviceScaleFactor: 1,
        mobile: false,
    });
    await browser.evaluate(
        `document.open(); document.write(${JSON.stringify(`<style>html{font:32px Arial}body{margin:0;overflow-x:hidden}.panel{margin:16px;padding:16px;border:1px solid}button{font:inherit}button:focus-visible{outline:3px solid blue}${css}</style><main><section class="panel">${markup}</section></main>`)}); document.close()`,
    );
    return browser.evaluate(
        `(${measureLayout.toString()})(${JSON.stringify(options)})`,
    );
}
// The Node test runner owns completion and failure reporting; register the whole suite synchronously.
void test('detects table cells clipped while document width passes; accepts reflow', async () => {
    const markup = '<table><tr><td>Product</td><td>100.00</td></tr></table>';
    const broken = await render(
        'main{overflow:hidden}table{width:700px}',
        markup,
    );
    assert.equal(broken.scrollWidth, 375);
    assert.ok(broken.failures.some((f) => /Outside|clipped/.test(f)));
    const fixed = await render('td{display:block}', markup);
    assert.deepEqual(fixed.failures, []);
});
void test('detects long text and zero-width names; accepts wrapping', async () => {
    const markup =
        '<div class="row"><span data-visual-text>ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFG</span><button>Available</button></div>';
    assert.ok(
        (
            await render(
                '.row{display:flex}.row span{width:0}.row button{flex:none}',
                markup,
            )
        ).failures.some((f) => /Zero-size|Text escapes/.test(f)),
    );
    assert.deepEqual(
        (
            await render(
                '.row span{display:block;overflow-wrap:anywhere}',
                markup,
            )
        ).failures,
        [],
    );
});
void test('detects button labels outside their box and panel', async () => {
    const markup = '<button>Correct balance</button>';
    assert.ok(
        (
            await render('button{width:50px;white-space:nowrap}', markup)
        ).failures.some((f) => /Text escapes/.test(f)),
    );
    assert.deepEqual(
        (await render('button{max-width:100%;white-space:normal}', markup))
            .failures,
        [],
    );
});
void test('detects focused controls hidden by clipping or an overlay', async () => {
    await render(
        '#cover{position:fixed;inset:0;background:white;z-index:10}',
        '<button id="action">Save</button><div id="cover"></div>',
    );
    await browser.send('Input.dispatchKeyEvent', {
        type: 'keyDown',
        key: 'Tab',
        code: 'Tab',
        windowsVirtualKeyCode: 9,
    });
    await browser.send('Input.dispatchKeyEvent', {
        type: 'keyUp',
        key: 'Tab',
        code: 'Tab',
        windowsVirtualKeyCode: 9,
    });
    const hidden = await browser.evaluate(
        `(${measureFocus.toString()})(['#action'])`,
    );
    assert.deepEqual(hidden.matched, ['#action']);
    assert.ok(hidden.failures.includes('Focused control clipped or covered'));
    await browser.evaluate("document.querySelector('#cover').remove()");
    const shown = await browser.evaluate(
        `(${measureFocus.toString()})(['#action'])`,
    );
    assert.deepEqual(shown.failures, []);
});
void test('modal dialogs escape clipping ancestors while their own clipping is checked', async () => {
    await render(
        '.panel{width:80px;overflow:hidden}dialog{width:280px;padding:16px}dialog p{margin:0;font-size:16px}',
        '<dialog><p>Manage cycle</p></dialog>',
    );
    await browser.evaluate('document.querySelector("dialog").showModal()');
    const modalOptions = {
        root: 'body',
        panels: 'dialog[open]',
        content: 'dialog[open] p',
    };
    const result = await browser.evaluate(
        `(${measureLayout.toString()})(${JSON.stringify(modalOptions)})`,
    );
    assert.deepEqual(result.failures, []);
    await browser.evaluate(
        'document.querySelector("dialog p").style.width="700px"',
    );
    const clipped = await browser.evaluate(
        `(${measureLayout.toString()})(${JSON.stringify(modalOptions)})`,
    );
    assert.ok(
        clipped.failures.some((failure) =>
            /clipped|Outside|escapes/.test(failure),
        ),
    );
});
void test('native date subcontrols retain a visible focus-within outline at every Tab stop', async () => {
    await render(
        'input:focus-within{outline:3px solid blue}',
        '<input id="date" type="date"><button>Save</button>',
    );
    let nativeStops = 0;
    for (let step = 0; step < 6; step++) {
        for (const type of ['keyDown', 'keyUp']) {
            await browser.send('Input.dispatchKeyEvent', {
                type,
                key: 'Tab',
                code: 'Tab',
                windowsVirtualKeyCode: 9,
            });
        }
        const state = await browser.evaluate(
            `({ native:document.activeElement.id==='date' && !document.activeElement.matches(':focus-visible'), result:(${measureFocus.toString()})(['#date']) })`,
        );
        if (!state.native) continue;
        nativeStops++;
        assert.deepEqual(state.result.failures, []);
        await browser.evaluate(
            'document.querySelector("#date").style.outline="none"',
        );
        const invisible = await browser.evaluate(
            `(${measureFocus.toString()})(['#date'])`,
        );
        assert.ok(
            invisible.failures.includes('Keyboard focus not focus-visible'),
        );
        await browser.evaluate(
            'document.querySelector("#date").style.removeProperty("outline")',
        );
    }
    assert.ok(nativeStops > 0, 'Must exercise a native picker subcontrol');
});

void test('measures typography, spacing, targets, DOM state and contrast without captures', async () => {
    await render(
        'html{background:white}.panel{background:white;color:black;display:grid;gap:16px}button{min-width:44px;min-height:44px;font-size:1rem}',
        '<button id="save" aria-expanded="false">Save</button>',
    );
    const rules = [
        {
            selector: '#save',
            count: 1,
            minWidth: 44,
            minHeight: 44,
            fontSizeRem: 1,
            text: 'Save',
            attributes: { 'aria-expanded': 'false' },
            contrast: 4.5,
        },
        { selector: '.panel', gapRem: 0.5 },
    ];
    const measure = (input) =>
        browser.evaluate(
            '(' +
                measureAssertions.toString() +
                ')(' +
                JSON.stringify(input) +
                ')',
        );
    const good = await measure(rules);
    assert.deepEqual(good.failures, []);
    assert.deepEqual(good.unverified, []);
    assert.ok(good.results[0].observed[0].contrast >= 4.5);
    const bad = await measure([
        {
            selector: '#save',
            minWidth: 500,
            fontSizeRem: 2,
            text: 'Wrong',
            attributes: { 'aria-expanded': 'true' },
        },
    ]);
    assert.ok(bad.failures.length >= 4);
    await browser.evaluate(
        "document.querySelector('#save').style.backgroundImage='linear-gradient(white,black)'",
    );
    assert.ok(
        (await measure([{ selector: '#save', contrast: 4.5 }])).unverified
            .length,
    );
    assert.ok(
        (await measure([{ selector: '#save', unknown: 1 }])).unverified.length,
    );
    assert.deepEqual(
        (await measure([{ selector: '.absent', count: 0 }])).failures,
        [],
    );
});

void test('gap assertions check both axes and leave unresolved percentages unverified', async () => {
    await render('.panel{display:grid;gap:8px 32px}', '<button>Save</button>');
    const measure = (rules) =>
        browser.evaluate(
            '(' +
                measureAssertions.toString() +
                ')(' +
                JSON.stringify(rules) +
                ')',
        );
    const mixed = await measure([{ selector: '.panel', gapRem: 0.25 }]);
    assert.equal(mixed.results[0].passed, false);
    assert.ok(mixed.unverified.length);
    assert.deepEqual(
        (
            await measure([
                { selector: '.panel', rowGapRem: 0.25, columnGapRem: 1 },
            ])
        ).failures,
        [],
    );
    await browser.evaluate("document.querySelector('.panel').style.gap='10%'");
    assert.ok(
        (await measure([{ selector: '.panel', gapRem: 0.3125 }])).unverified
            .length,
    );
});

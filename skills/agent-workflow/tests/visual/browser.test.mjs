import assert from 'node:assert/strict';
import { test, before, after } from 'node:test';
import { openChrome } from '../../scripts/visual/chrome.mjs';
import { measureLayout, measureFocus } from '../../scripts/visual/measure.mjs';
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

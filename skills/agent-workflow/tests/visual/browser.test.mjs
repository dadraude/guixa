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

const measureRules = (rules) =>
    browser.evaluate(
        '(' + measureAssertions.toString() + ')(' + JSON.stringify(rules) + ')',
    );

void test('non-text contrast fails a field border or divider below 3:1 and passes at 3:1', async () => {
    // On white, #949494 is 3.03:1 and #959595 is 2.99:1.
    await render(
        'html{background:white}input{border:1px solid #949494}#faint{border-color:#959595}#underline{border-width:0 0 1px}li+li{border-top:1px solid color-mix(in srgb, black 60%, transparent)}ul.faint li+li{border-top-color:color-mix(in srgb, black 12%, transparent)}',
        '<input id="field"><input id="faint"><input id="underline"><ul><li>a</li><li id="divider">b</li></ul><ul class="faint"><li>a</li><li id="faint-divider">b</li></ul>',
    );
    const passing = await measureRules([
        { selector: '#field', borderContrast: 3 },
        { selector: '#underline', borderContrast: 3, borderSides: ['bottom'] },
        { selector: '#divider', borderContrast: 3, borderSides: ['top'] },
    ]);
    assert.deepEqual(passing.unverified, []);
    assert.deepEqual(passing.failures, []);
    assert.equal(
        passing.results[0].observed[0].borderContrast.toFixed(2),
        '3.03',
    );
    const failing = await measureRules([
        { selector: '#faint', borderContrast: 3 },
        { selector: '#faint-divider', borderContrast: 3, borderSides: ['top'] },
        { selector: '#underline', borderContrast: 3 },
    ]);
    assert.deepEqual(failing.unverified, []);
    assert.deepEqual(failing.results[0].failures, [
        'Insufficient border contrast: top 2.99',
        'Insufficient border contrast: right 2.99',
        'Insufficient border contrast: bottom 2.99',
        'Insufficient border contrast: left 2.99',
    ]);
    assert.match(
        failing.results[1].failures[0],
        /^Insufficient border contrast: top 1\./,
    );
    assert.ok(failing.results[2].failures.includes('No visible border: top'));
});

void test('non-text contrast never passes a border whose colours it cannot resolve', async () => {
    const unresolved = [
        [
            'gradient behind',
            '.panel{background-image:linear-gradient(red,blue)}',
        ],
        [
            'gradient on field',
            'input{background-image:linear-gradient(red,blue)}',
        ],
        ['dimmed ancestor', '.panel{opacity:.5}'],
        ['border image', 'input{border-image:linear-gradient(red,blue) 1}'],
        ['unparsed background', '.panel{background:lab(50 0 0)}'],
        ['unparsed border', 'input{border-color:lab(20 0 0)}'],
        ['no opaque background', 'html{background:transparent}'],
        ['three-dimensional edge', 'input{border-style:inset}'],
        ['hidden field', 'input{visibility:hidden}'],
    ];
    for (const [name, css] of unresolved) {
        await render(
            'html{background:white}input{border:1px solid black}' + css,
            '<input id="field">',
        );
        const result = await measureRules([
            { selector: '#field', borderContrast: 3 },
        ]);
        assert.deepEqual(
            result.unverified,
            ['#field: Border contrast colours or compositing are unsupported'],
            name,
        );
        assert.equal(result.results[0].passed, false, name);
    }
    for (const rule of [
        { borderContrast: 0.5 },
        { borderContrast: 3, borderSides: [] },
        { borderContrast: 3, borderSides: ['middle'] },
        { borderSides: ['top'] },
    ]) {
        const result = await measureRules([{ selector: '#field', ...rule }]);
        assert.deepEqual(
            result.unverified,
            ['#field: Invalid border contrast rule'],
            JSON.stringify(rule),
        );
    }
});

async function measureFocused(css, markup, rule = { focusContrast: 3 }) {
    await render(
        'html{background:white}#target:focus,#target:focus-visible{outline:none}' +
            css,
        markup,
    );
    await browser.evaluate("document.querySelector('#target').focus()");
    return measureRules([{ selector: '#target', ...rule }]);
}
const button = '<button id="target">Save</button>';

void test('focus indicator contrast reads outlines and rings against what is painted next to them', async () => {
    const passing = [
        [
            'outline',
            '#target:focus{outline:2px solid #949494;outline-offset:2px}',
            button,
        ],
        [
            'inset ring against own fill',
            '#target{background:black}#target:focus{box-shadow:inset 0 0 0 1px white}',
            button,
        ],
        [
            'outer oklab ring',
            '#target:focus{box-shadow:0 0 0 3px oklab(0.558165 0.0633351 0.107746)}',
            button,
        ],
        [
            'strong outline beside a faint ring',
            '#target:focus{outline:2px solid black;box-shadow:0 0 0 3px oklab(0.558165 0.0633351 0.107746 / 0.2)}',
            button,
        ],
    ];
    for (const [name, css, markup] of passing) {
        const result = await measureFocused(css, markup);
        assert.deepEqual(result.unverified, [], name);
        assert.deepEqual(result.failures, [], name);
    }
    // The oklab ring above is the computed form of #a85f12, 4.9:1 on white.
    const ring = await measureFocused(passing[2][1], button);
    assert.ok(
        Math.abs(ring.results[0].observed[0].focusContrast - 4.9) < 0.1,
        String(ring.results[0].observed[0].focusContrast),
    );
    const failing = [
        [
            'faint outline',
            '#target:focus{outline:2px solid #959595}',
            button,
            /^#target: Insufficient focus indicator contrast: 2\.99$/,
        ],
        [
            'ring lost on own fill',
            '#target{background:black}#target:focus{box-shadow:inset 0 0 0 1px #222}',
            button,
            /Insufficient focus indicator contrast: 1\./,
        ],
        [
            'painted backdrop, not the DOM ancestor',
            '.panel{position:relative;height:200px}#cover{position:absolute;inset:0;background:black}#target{position:absolute;top:60px;left:60px}#target:focus{outline:2px solid #222}',
            '<div id="cover"></div>' + button,
            /Insufficient focus indicator contrast: 1\./,
        ],
        ['no indicator', '', button, /No visible focus indicator/],
        [
            'clipped away',
            '.clip{overflow:hidden;width:max-content}.clip button{display:block}#target:focus{outline:2px solid black;outline-offset:4px}',
            '<div class="clip">' + button + '</div>',
            /No visible focus indicator/,
        ],
    ];
    for (const [name, css, markup, expected] of failing) {
        const result = await measureFocused(css, markup);
        assert.deepEqual(result.unverified, [], name);
        assert.equal(result.failures.length, 1, name);
        assert.match(result.failures[0], expected, name);
    }
    await browser.evaluate("document.querySelector('#target').blur()");
    assert.deepEqual(
        (await measureRules([{ selector: '#target', focusContrast: 3 }]))
            .failures,
        ['#target: Element is not focused'],
    );
});

void test('focus indicator contrast never passes an indicator it cannot resolve', async () => {
    const outline = '#target:focus{outline:2px solid black}';
    const unresolved = [
        ['unparsed outline', '#target:focus{outline:2px solid lab(10 0 0)}'],
        [
            'unparsed ring beside a good outline',
            '#target:focus{outline:2px solid black;box-shadow:0 0 0 3px lab(50 0 0)}',
        ],
        ['browser default ring', '#target:focus{outline:auto}'],
        ['dashed outline', '#target:focus{outline:2px dashed black}'],
        ['only a blurred shadow', '#target:focus{box-shadow:0 0 8px black}'],
        [
            'gradient behind',
            outline + '.panel{background-image:linear-gradient(red,blue)}',
        ],
        ['dimmed ancestor', outline + '.panel{opacity:.5}'],
        ['no opaque backdrop', outline + 'html{background:transparent}'],
    ];
    for (const [name, css] of unresolved) {
        const result = await measureFocused(css, button);
        assert.deepEqual(
            result.unverified,
            ['#target: Focus indicator colours or compositing are unsupported'],
            name,
        );
        assert.deepEqual(result.failures, [], name);
        assert.equal(result.results[0].passed, false, name);
    }
    const invalid = await measureFocused(outline, button, {
        focusContrast: 0,
    });
    assert.deepEqual(invalid.unverified, [
        '#target: Invalid focus contrast rule',
    ]);
});

void test('a text or visible filter measures what is on screen and cannot pass on nothing', async () => {
    await render(
        'html{background:white}.off{display:none}.sr{position:absolute;width:1px;height:1px;overflow:hidden;color:white}',
        '<div id="group"><p id="read">Read</p><p class="off">Hidden</p><span class="sr">Spoken</span><button disabled style="color:#ddd;background:white">Off</button><div><span>Nested</span></div></div>',
    );
    const rule = { selector: '#group, #group *', contrast: 4.5 };
    const text = await measureRules([{ ...rule, filter: 'text' }]);
    assert.deepEqual(text.failures, []);
    assert.deepEqual(text.unverified, []);
    assert.equal(text.results[0].observed.length, 2);
    // `visible` drops only what is not rendered, so the unreadable disabled and screen-reader text still fail it.
    const visible = await measureRules([{ ...rule, filter: 'visible' }]);
    assert.ok(visible.failures.length > 0);
    assert.ok(visible.results[0].observed.length > 2);
    for (const filter of ['text', 'visible']) {
        const none = await measureRules([
            { selector: '.off', contrast: 4.5, filter },
        ]);
        assert.deepEqual(none.failures, ['.off: Missing element'], filter);
    }
    assert.deepEqual(
        (await measureRules([{ ...rule, filter: 'shown' }])).unverified,
        ['#group, #group *: Unsupported element filter'],
    );
});

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
        'html{background:white}.off{display:none}.sr{position:absolute;width:1px;height:1px;overflow:hidden;color:white}.clipped{position:absolute;width:1px;height:1px;padding:16px;overflow:hidden;clip-path:inset(50%);color:white}',
        '<div id="group"><p id="read">Read</p><p class="off">Hidden</p><span class="sr">Spoken</span><div class="sr"><h2>Announced</h2></div><div class="clipped"><h2>Padded</h2></div><button disabled style="color:#ddd;background:white">Off</button><div><span>Nested</span></div></div>',
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

void test('a divider painted as a filled line meets the same ratio against what is behind it', async () => {
    const css =
        'html{background:white}hr{border:0;height:1px;background:#949494}#faint{background:#959595}#none{background:transparent}#unparsed{background:lab(50 0 0)}#striped{background-image:linear-gradient(red,blue)}';
    await render(
        css,
        '<hr id="line"><hr id="faint"><hr id="none"><hr id="unparsed"><hr id="striped">',
    );
    const rule = (selector) => ({ selector, fillContrast: 3 });
    const passing = await measureRules([rule('#line')]);
    assert.deepEqual(passing.failures, []);
    assert.deepEqual(passing.unverified, []);
    assert.deepEqual(
        (await measureRules([rule('#faint'), rule('#none')])).failures,
        ['#faint: Insufficient fill contrast: 2.99', '#none: No visible fill'],
    );
    const unresolved = await measureRules([
        rule('#unparsed'),
        rule('#striped'),
        { selector: '#line', fillContrast: 0 },
    ]);
    assert.deepEqual(unresolved.failures, []);
    assert.deepEqual(unresolved.unverified, [
        '#unparsed: Fill contrast colours or compositing are unsupported',
        '#striped: Fill contrast colours or compositing are unsupported',
        '#line: Invalid fill contrast rule',
    ]);
});

void test('a focus indicator behind a modal is read through the page that stopped taking the pointer', async () => {
    const result = await measureFocused(
        'html{background:transparent}body{background:white;min-height:100vh;pointer-events:none}#shade,#dialog{position:fixed;pointer-events:auto;top:0;bottom:0}#shade{left:0;right:0;background:rgb(0 0 0 / 0.8)}#dialog{left:0;width:200px;background:black}#target{position:absolute;top:40px;left:0;width:199px}#target:focus{outline:2px solid white;outline-offset:2px}',
        '<div id="shade"></div><div id="dialog">' + button + '</div>',
    );
    assert.deepEqual(result.unverified, []);
    assert.deepEqual(result.failures, []);
    assert.equal(
        await browser.evaluate('document.querySelectorAll("style").length'),
        1,
    );
});

// A focused control in a low layer, ringed in white on its black bar, with something else reaching over one side.
const layered = (cover, css = '') =>
    measureFocused(
        `#bar,#over{position:fixed;top:0;height:200px}#bar{left:0;width:200px;z-index:10;background:black}#target{position:absolute;top:40px;left:0;width:199px;height:44px}#target:focus{outline:2px solid white;outline-offset:2px}${cover}${css}`,
        '<div id="bar">' + button + '</div><div id="over"></div>',
    );

const unsupportedFocus =
    '#target: Focus indicator colours or compositing are unsupported';

void test('an indicator point covered by a higher layer is not visible and cannot change the verdict', async () => {
    // The right side lies under a white bar of a higher layer, as the sidebar brand under the app bar: the other
    // sides still show the ring on black.
    const aside = await layered(
        '#over{left:200px;right:0;z-index:30;background:white}',
    );
    assert.deepEqual(aside.unverified, []);
    assert.deepEqual(aside.failures, []);
    assert.equal(aside.results[0].observed[0].focusContrast.toFixed(0), '21');
    // Wholly covered, the ring would contrast with the cover; that contrast is not the ring's.
    const hidden = await layered(
        '#over{left:0;right:0;z-index:30;background:black}',
    );
    assert.deepEqual(hidden.failures, ['#target: No visible focus indicator']);
    const tinted = await layered(
        '#over{left:200px;right:0;z-index:30;background:rgb(255 0 0 / 0.5)}',
    );
    assert.deepEqual(tinted.unverified, [
        '#target: Focus indicator colours or compositing are unsupported',
    ]);
});

void test('what paints beneath an indicator stays its adjacent colour', async () => {
    const beneath = [
        ['lower layer', '#over{left:200px;right:0;z-index:5;background:white}'],
        [
            'transparent higher layer',
            '#over{left:200px;right:0;z-index:30;background:transparent}',
        ],
    ];
    const lower = await layered(beneath[0][1]);
    assert.deepEqual(lower.failures, [
        '#target: Insufficient focus indicator contrast: 1.00',
    ]);
    const transparent = await layered(beneath[1][1]);
    assert.deepEqual(transparent.unverified, []);
    // Whether a following in-flow box paints over an indicator is not proven, so it is neither dropped nor graded.
    const flow = await measureFocused(
        '#target{display:block;height:44px;width:100px}#target:focus{outline:2px solid #222;outline-offset:2px}#after{height:60px;margin-top:-4px;background:black}',
        button + '<div id="after"></div>',
    );
    assert.deepEqual(flow.unverified, [unsupportedFocus]);
});

void test('an inset ring covered by the control content is not visible', async () => {
    const result = await measureFocused(
        '#target{position:relative;width:100px;height:44px;border:0;background:white}#target:focus{box-shadow:inset 0 0 0 4px black}#fill{position:absolute;inset:0;background:black}',
        '<div tabindex="0" id="target"><div id="fill"></div></div>',
    );
    assert.deepEqual(result.failures, ['#target: No visible focus indicator']);
});

void test('an element filter named like an inherited property is unsupported', async () => {
    await render('', '<p id="read">Read</p>');
    const result = await measureRules([
        { selector: '#read', count: 1, filter: 'toString' },
    ]);
    assert.deepEqual(result.unverified, ['#read: Unsupported element filter']);
});

void test('positioned boxes and clipping hide indicator points on either side of the box edge', async () => {
    const ringed =
        '#target{position:relative;width:100px;height:44px;border:0;background:black;color:white}';
    const hidden = {
        'later positioned box of the same layer': [
            '#target:focus{outline:2px solid #222;outline-offset:2px}#after{position:relative;margin:-60px -10px 0;height:90px;background:white}',
            button + '<div id="after"></div>',
        ],
        'inset ring clipped by an ancestor': [
            '#clip{width:40px;height:20px;overflow:hidden;margin:10px}#target{margin:-10px 0 0 -30px}#target:focus{box-shadow:inset 0 0 0 4px white}',
            '<div id="clip">' + button + '</div>',
        ],
    };
    for (const [name, [css, markup]] of Object.entries(hidden)) {
        const result = await measureFocused(ringed + css, markup);
        assert.deepEqual(result.unverified, [], name);
        assert.deepEqual(
            result.failures,
            ['#target: No visible focus indicator'],
            name,
        );
    }
});

// A 100 by 44 white control, 40 px into the panel, and a white outline 2 px outside it unless the case says otherwise.
const control =
    '#target{display:block;margin:40px;width:100px;height:44px;border:0;padding:0;background:white}';
const outside = '#target:focus{outline:4px solid black;outline-offset:2px}';
const plain = '<div tabindex="0" id="target"></div>';
// A dark strip painted behind the top side, and a white box reaching over the same place.
const strip =
    '#strip,#cover{position:absolute;left:0;width:375px;height:14px;top:var(--top)}#strip{background:#111;z-index:-1}:root{--top:62px}';

// The cases that were graded or dropped although they cannot be proven: each one has to stay unverified.
async function resolved(cases) {
    const wrong = [];
    for (const [name, [css, markup]] of Object.entries(cases)) {
        const result = await measureFocused(control + outside + css, markup);
        if (result.unverified[0] !== unsupportedFocus || result.failures.length)
            wrong.push(name);
    }
    return wrong;
}

void test('a point is left out or graded only when the page proves what is painted there', async () => {
    const unverified = {
        'cover made invisible by an ancestor': [
            strip + '#ghost{opacity:0}#cover{background:white;z-index:5}',
            '<div id="strip"></div>' +
                plain +
                '<div id="ghost"><div id="cover"></div></div>',
        ],
        'cover inside a filtered ancestor': [
            strip +
                '#ghost{position:absolute;left:0;top:0;width:375px;height:80px;filter:opacity(0)}#cover{background:white;z-index:5}',
            '<div id="strip"></div>' +
                plain +
                '<div id="ghost"><div id="cover"></div></div>',
        ],
        'masked cover': [
            strip +
                '#cover{background:white;z-index:5;mask-image:linear-gradient(transparent,transparent)}',
            '<div id="strip"></div>' + plain + '<div id="cover"></div>',
        ],
        'z-index kept inside a will-change ancestor': [
            strip +
                '#trap{will-change:transform}#cover{background:white;z-index:5}#target{position:relative;z-index:1}',
            '<div id="trap"><div id="cover"></div></div>' + plain,
        ],
        'z-index kept inside a containing ancestor': [
            strip +
                '#trap{contain:layout}#cover{background:white;z-index:5}#target{position:relative;z-index:1}',
            '<div id="trap"><div id="cover"></div></div>' + plain,
        ],
        'inert cover': [
            strip + '#cover{background:white;z-index:5}',
            '<div id="strip"></div>' + plain + '<div id="cover" inert></div>',
        ],
        'pseudo-element cover': [
            strip +
                '#host::after{content:"";position:absolute;left:0;width:375px;height:14px;top:62px;background:white;z-index:5}',
            '<div id="strip"></div>' + plain + '<div id="host"></div>',
        ],
        'border painted over the point': [
            strip + '#cover{height:0;border-top:14px solid white;z-index:5}',
            '<div id="strip"></div>' + plain + '<div id="cover"></div>',
        ],
        'drawing painted over the point': [
            strip + '#cover{z-index:5}',
            '<div id="strip"></div>' +
                plain +
                '<svg id="cover" width="375" height="14"><rect width="375" height="14" fill="white"/></svg>',
        ],
    };
    assert.deepEqual(await resolved(unverified), []);
    // Clipped to its content box, the cover paints nothing there: the ring is graded on the strip behind it.
    const clipped = await measureFocused(
        control +
            outside +
            strip +
            '#cover{height:0;padding-bottom:14px;background:white;background-clip:content-box;z-index:5}',
        '<div id="strip"></div>' + plain + '<div id="cover"></div>',
    );
    assert.deepEqual(clipped.unverified, []);
    assert.deepEqual(clipped.failures, [
        '#target: Insufficient focus indicator contrast: 1.11',
    ]);
});

void test('an outline drawn inside the box is graded against the content beneath it', async () => {
    const result = await measureFocused(
        control +
            '#fill{height:30px;background:#111}#target:focus{outline:2px solid black;outline-offset:-4px}',
        '<div tabindex="0" id="target"><div id="fill"></div></div>',
    );
    assert.deepEqual(result.unverified, []);
    assert.deepEqual(result.failures, [
        '#target: Insufficient focus indicator contrast: 1.11',
    ]);
});

void test('a shadow ring is not graded where a following box or its own layers may hide it', async () => {
    const ring = '#target:focus{box-shadow:0 0 0 8px black}';
    const following = await measureFocused(
        control +
            ring +
            '#after{height:20px;margin-top:-46px;background:white}',
        plain + '<div id="after"></div>',
    );
    assert.deepEqual(following.unverified, [unsupportedFocus]);
    for (const [name, css] of Object.entries({
        'under an earlier shadow': 'box-shadow:0 0 0 8px white,0 0 0 6px black',
        'under an inset earlier shadow':
            'box-shadow:inset 0 0 0 8px white,inset 0 0 0 6px black',
        'under the outline':
            'outline:8px solid white;outline-offset:0;box-shadow:0 0 0 8px black',
    })) {
        const result = await measureFocused(
            control + `#target:focus{${css}}`,
            plain,
        );
        assert.deepEqual(result.unverified, [], name);
        assert.deepEqual(
            result.failures,
            ['#target: Insufficient focus indicator contrast: 1.00'],
            name,
        );
    }
    const blurred = await measureFocused(
        control +
            '#target:focus{box-shadow:0 0 1px 10px white,0 0 0 6px black}',
        plain,
    );
    assert.deepEqual(blurred.unverified, [unsupportedFocus]);
});

void test('a control whose indicator cannot be located or may be clipped is never passed', async () => {
    const wrapped = '<div id="wrap">' + plain + '</div>';
    const cases = {
        'masked ancestor': [
            '#wrap{mask-image:linear-gradient(transparent,transparent)}',
            wrapped,
        ],
        'clip-path ancestor': ['#wrap{clip-path:inset(40px)}', wrapped],
        'paint containment': [
            '#wrap{contain:paint;margin:40px;width:100px}#target{margin:0}',
            wrapped,
        ],
        'rotated control': ['#target{rotate:20deg}', plain],
        'control below its layer': [
            '#target{position:relative;z-index:-1}.panel{background:white}',
            plain,
        ],
        'absolute control past a clipping ancestor': [
            '#wrap{width:10px;height:10px;overflow:hidden}#target{position:absolute;top:60px;left:60px;margin:0}',
            wrapped,
        ],
        'side below a viewport that cannot scroll to it': [
            '#target{position:fixed;top:560px;left:0}',
            plain,
        ],
    };
    assert.deepEqual(await resolved(cases), []);
});

void test('each way of painting that the rule does not model keeps the point unverified', async () => {
    // A white cover of a higher layer over the top side, trapped or reordered by the CSS of its ancestor.
    const trapped = (ancestor) => [
        strip +
            `#trap{${ancestor}}#cover{background:white;z-index:5}#target{position:relative;z-index:1}`,
        '<div id="trap"><div id="cover"></div></div>' + plain,
    ];
    const over = (css, markup = '<div id="cover"></div>') => [
        strip + css,
        '<div id="strip"></div>' + plain + markup,
    ];
    const shown = (id) =>
        `<script>document.querySelector('${id}').showPopover()</script>`;
    const cases = {
        'cover under a size container': trapped('container-type:inline-size'),
        'cover under a backdrop filter': trapped('backdrop-filter:blur(1px)'),
        'cover under a 3d context': trapped('transform-style:preserve-3d'),
        'cover reordered in a flex row': [
            strip +
                '#trap{order:1}#cover{background:white;z-index:5}.panel{display:flex}',
            '<div id="trap"><div id="cover"></div></div>' + plain,
        ],
        'cover in the top layer': over(
            '#cover{margin:0;border:0;padding:0;inset:auto;top:62px;background:white}',
            '<div id="cover" popover="manual"></div>' + shown('#cover'),
        ),
        'unparsed cover colour': over(
            '#cover{background:lab(100 0 0);z-index:5}',
        ),
        'image background cover': over(
            '#cover{background-image:linear-gradient(white,white);z-index:5}',
        ),
        'cover clipped to its text': over(
            '#cover{background:white;background-clip:text;z-index:5}',
        ),
        'image over the point': over(
            '#cover{z-index:5}',
            '<img id="cover" alt="" width="375" height="14" src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=">',
        ),
        'pseudo-element cover that ignores the pointer': over(
            '#cover::after{content:"";position:absolute;inset:0;background:white;pointer-events:none}#cover{z-index:5}',
        ),
        'translucent shadow of the control over its ring': [
            '#target:focus{outline:none;box-shadow:0 0 0 8px rgb(255 255 255 / 0.5),0 0 0 6px black}',
            plain,
        ],
        'ring at the edge of another layer of the control': [
            '#target:focus{outline:none;box-shadow:0 0 0 3px white,0 0 0 6px black}',
            plain,
        ],
        'pseudo-element of a box elsewhere that ignores the pointer': over(
            '#host::after{content:"";position:absolute;left:0;top:62px;width:375px;height:14px;background:white;z-index:5;pointer-events:none}',
            '<div id="host"></div>',
        ),
        'blurred layer of the control over a ring beside a good outline': [
            '#target:focus{outline:4px solid black;outline-offset:14px;box-shadow:0 0 1px 10px white,0 0 0 6px black}',
            plain,
        ],
        'table row group background that hit testing leaves out': [
            'table{border-collapse:collapse}tbody{background:#111}td{padding:0}',
            '<table><tbody><tr><td>' + plain + '</td></tr></tbody></table>',
        ],
        'outline over positioned content': [
            '#target{position:relative}#fill{position:absolute;inset:-8px;background:white}',
            '<div tabindex="0" id="target"><div id="fill"></div></div>',
        ],
        'inset ring over content below its layer': [
            '#target{position:relative;z-index:0}#target:focus{outline:none;box-shadow:inset 0 0 0 8px black}#fill{position:absolute;z-index:-1;inset:0;background:white}',
            '<div tabindex="0" id="target"><div id="fill"></div></div>',
        ],
        'skipped content ancestor': [
            '#wrap{content-visibility:auto}',
            '<div id="wrap">' + plain + '</div>',
        ],
        'legacy clip ancestor': [
            '#wrap{position:absolute;clip:rect(0,300px,300px,0)}',
            '<div id="wrap">' + plain + '</div>',
        ],
        'skewed control': ['#target{transform:skewX(10deg)}', plain],
        'scaled control': ['#target{scale:1.2}', plain],
        'control in the top layer': [
            '#wrap{margin:0;border:0;background:white}',
            '<div id="wrap" popover="manual">' +
                plain +
                '</div>' +
                shown('#wrap'),
        ],
        'ring in a clip margin': [
            '#wrap{overflow:clip;overflow-clip-margin:1px;margin:40px;width:100px}#target{margin:0}',
            '<div id="wrap">' + plain + '</div>',
        ],
    };
    assert.deepEqual(await resolved(cases), []);
});

void test('what is proven to lie beneath a point is graded and nothing else counts', async () => {
    const graded = {
        // The fixed box keeps its child's z-index, so the child stays beneath the higher control.
        'z-index kept inside a fixed box': [
            '#trap{position:fixed;left:0;top:62px;width:375px;height:14px}#cover{height:14px;background:#111;position:relative;z-index:5}#target{position:relative;z-index:1}',
            '<div id="trap"><div id="cover"></div></div>' + plain,
            ['#target: Insufficient focus indicator contrast: 1.11'],
        ],
    };
    for (const [name, [css, markup, failures]] of Object.entries(graded)) {
        const result = await measureFocused(control + outside + css, markup);
        assert.deepEqual(result.unverified, [], name);
        assert.deepEqual(result.failures, failures, name);
    }
});

void test('a shadow of another box lowers a ratio to its worst case and never raises it', async () => {
    const ringed = (colour, shadow) =>
        measureFocused(
            control +
                `#target:focus{outline:4px solid ${colour};outline-offset:2px}#caster{position:absolute;left:0;width:375px;height:1px;top:20px;box-shadow:${shadow}}`,
            '<div tabindex="0" id="target"></div><div id="caster"></div>',
        );
    // A 10% tint over the top side of a black ring on white: still far above 3:1, and below the untinted 21:1.
    const light = await ringed('black', '0 49px 0 5px rgb(0 0 0 / 0.1)');
    assert.deepEqual(light.unverified, []);
    assert.deepEqual(light.failures, []);
    assert.ok(light.results[0].observed[0].focusContrast < 17);
    // An opaque shadow of the page's colour can hide the ring altogether.
    const hiding = await ringed('black', '0 49px 0 5px white');
    assert.deepEqual(hiding.failures, [
        '#target: Insufficient focus indicator contrast: 1.00',
    ]);
    // A grey ring between the bare and the tinted backdrop may vanish into it: no ratio can be told.
    const crossing = await ringed('#777', '0 49px 0 5px rgb(0 0 0 / 0.9)');
    assert.deepEqual(crossing.unverified, [unsupportedFocus]);
    const inside = await measureFocused(
        control +
            '#target:focus{outline:4px solid black;outline-offset:2px}#caster{position:absolute;left:0;width:375px;height:14px;top:62px;box-shadow:inset 0 0 0 9px white}',
        '<div tabindex="0" id="target"></div><div id="caster"></div>',
    );
    assert.deepEqual(inside.failures, [
        '#target: Insufficient focus indicator contrast: 1.00',
    ]);
    // Tints of two colours are bounded by black and by white at their joint strength.
    const mixed = await ringed(
        'black',
        '0 49px 0 5px rgb(0 0 0 / 0.1), 0 49px 0 5px rgb(255 0 0 / 0.1)',
    );
    assert.deepEqual(mixed.unverified, []);
    assert.deepEqual(mixed.failures, []);
    assert.ok(
        mixed.results[0].observed[0].focusContrast <
            light.results[0].observed[0].focusContrast,
    );
    // A grey ring that one of those extremes can bring to its backdrop's colour has no bound.
    const unbounded = await ringed(
        '#999',
        '0 49px 0 5px rgb(0 0 0 / 0.6), 0 49px 0 5px rgb(255 0 0 / 0.6)',
    );
    assert.deepEqual(unbounded.unverified, [unsupportedFocus]);
});

void test('a soft shadow of the control over its own ring is bounded like any other tint', async () => {
    // A toast: a faint drop shadow listed over a faint ring, beside an outline that shows the focus.
    const toast = await measureFocused(
        control +
            '#target:focus{outline:2px solid black;outline-offset:2px;box-shadow:0 4px 12px rgb(0 0 0 / 0.1),0 0 0 2px rgb(0 0 0 / 0.2)}',
        '<div tabindex="0" id="target"></div>',
    );
    assert.deepEqual(toast.unverified, []);
    assert.deepEqual(toast.failures, []);
    // Without the outline no ring passes, and the blurred shadow might be what shows the focus: unverified.
    const faint = await measureFocused(
        control +
            '#target:focus{outline:none;box-shadow:0 4px 12px rgb(0 0 0 / 0.1),0 0 0 2px rgb(0 0 0 / 0.2)}',
        '<div tabindex="0" id="target"></div>',
    );
    assert.deepEqual(faint.unverified, [unsupportedFocus]);
    const opaque = await measureFocused(
        control +
            '#target:focus{outline:none;box-shadow:0 4px 12px black,0 0 0 2px black}',
        '<div tabindex="0" id="target"></div>',
    );
    assert.deepEqual(opaque.unverified, [unsupportedFocus]);
});

void test('the worst case of a tint decides the ratio, and a tint that cannot be bounded decides nothing', async () => {
    const shaded = (ring, shadow, extra = '') =>
        measureFocused(
            control +
                `#target:focus{${ring}}#caster{position:absolute;left:0;width:375px;height:1px;top:20px;box-shadow:${shadow}}${extra}`,
            '<div tabindex="0" id="target"></div><div id="dim"><div id="caster"></div></div>',
        );
    const outline = (colour) =>
        `outline:4px solid ${colour};outline-offset:2px`;
    const fails = async (ring, shadow, ratio) => {
        const result = await shaded(ring, shadow);
        assert.deepEqual(result.unverified, [], shadow);
        assert.match(result.failures[0], ratio, shadow);
    };
    // A grey ring is closest to its backdrop when the tint lies beneath it.
    await fails(outline('#777'), '0 49px 0 5px rgb(0 0 0 / 0.3)', /: 2\.1/);
    // Black and white at the joint strength of two tints: the white one is the worst for a black ring.
    await fails(
        outline('black'),
        '0 49px 0 5px rgb(0 0 0 / 0.5), 0 49px 0 5px rgb(255 255 255 / 0.5)',
        /: 1\.8/,
    );
    const own = await shaded(
        'outline:none;box-shadow:0 0 1px 10px rgb(0 0 0 / 0.3),0 0 0 6px #777',
        'none',
    );
    assert.deepEqual(own.unverified, [unsupportedFocus]);
    for (const [name, shadow, extra] of [
        ['unparsed shadow colour', '0 49px 0 5px lab(100 0 0)', ''],
        [
            'shadow of a dimmed box',
            '0 49px 0 5px rgb(0 0 0 / 0.1)',
            '#dim{opacity:.5}',
        ],
    ]) {
        const result = await shaded(outline('black'), shadow, extra);
        assert.deepEqual(result.unverified, [unsupportedFocus], name);
    }
});

void test('text lying under a sample point leaves the adjacent colour unproven', async () => {
    // A control over a line of text, as a skip link over the app bar: the ring crosses the glyphs.
    const over = (css) =>
        measureFocused(
            control +
                outside +
                '#target{position:relative;z-index:5}#words{position:absolute;left:0;top:62px;margin:0;font:14px/14px Arial;white-space:nowrap}' +
                css,
            '<p id="words">MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM</p><div tabindex="0" id="target"></div>',
        );
    assert.deepEqual((await over('')).unverified, [unsupportedFocus]);
    // Hidden under an opaque box that lies over the text and under the control, it paints nothing there.
    const hidden = await over(
        '#words{z-index:1}.panel::after{content:none}#target{box-shadow:none}#words{visibility:hidden}',
    );
    assert.deepEqual(hidden.unverified, []);
    assert.deepEqual(hidden.failures, []);
});

void test('only the ink of a text under a sample point leaves it unproven', async () => {
    // The left midpoint, at (36, 62), lies below the baseline of this line: its descenders reach it, other letters do not.
    const over = (words, css = '') =>
        measureFocused(
            control +
                outside +
                '#target{position:absolute;top:40px;left:40px;margin:0;z-index:5}#words{position:absolute;left:0;top:23px;margin:0;font:40px/40px Arial;white-space:nowrap}' +
                css,
            `<p id="words">${words.slice(0, 2)}</p><div tabindex="0" id="target"></div>`,
        );
    const clear = await over('aaaa');
    assert.deepEqual(clear.unverified, []);
    assert.deepEqual(clear.failures, []);
    // With the point above the x-height, small letters stay clear and capitals reach it.
    const above = await over('aaaa', '#words{top:54px}');
    assert.deepEqual(above.unverified, []);
    for (const [name, words, css] of [
        ['capital', 'AAAA', '#words{top:54px}'],
        ['descender', 'gggg', ''],
        ['underline', 'aaaa', '#words{text-decoration:underline}'],
        ['underline of an ancestor', 'aaaa', 'body{text-decoration:underline}'],
        ['transformed case', 'aaaa', '#words{text-transform:uppercase}'],
        ['text shadow', 'aaaa', '#words{text-shadow:0 8px black}'],
        ['scaled line', 'aaaa', '#words{scale:1 1.2}'],
    ]) {
        const result = await over(words, css);
        assert.deepEqual(result.unverified, [unsupportedFocus], name);
    }
});

void test('a ring that reaches past the viewport is graded with the page scrolled to it', async () => {
    const below = (outline) =>
        measureFocused(
            control +
                `#target:focus{outline:4px solid ${outline};outline-offset:2px}#target{position:absolute;top:556px;left:40px;margin:0}.panel{height:900px}`,
            plain,
        );
    await browser.evaluate('0');
    const seen = await below('black');
    assert.deepEqual(seen.unverified, []);
    assert.deepEqual(seen.failures, []);
    const faint = await below('#eee');
    assert.match(faint.failures[0], /Insufficient focus indicator contrast/);
    assert.equal(await browser.evaluate('scrollY'), 0);
});

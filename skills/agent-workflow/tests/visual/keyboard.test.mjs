import assert from 'node:assert/strict';
import { test, before, after } from 'node:test';
import { openChrome } from '../../scripts/visual/chrome.mjs';
import { walkTabOrder, runSteps } from '../../scripts/visual/keyboard.mjs';
let browser;
before(async () => {
    browser = await openChrome();
});
after(async () => {
    await browser?.close();
});

async function render(markup, css = '', script = '') {
    await browser.send('Emulation.setDeviceMetricsOverride', {
        width: 375,
        height: 600,
        deviceScaleFactor: 1,
        mobile: false,
    });
    await browser.evaluate(
        `document.open(); document.write(${JSON.stringify(`<style>html{background:white}button{display:inline-block;min-width:60px;min-height:44px;margin:8px}button:focus-visible{outline:2px solid black;outline-offset:2px}${css}</style>${markup}`)}); document.close(); ${script}`,
    );
}
const options = {
    regions: ['header', 'main'],
    focusContrast: 3,
    minTarget: 44,
};
const page =
    '<header><button id="menu">Menu</button></header><main><button id="first">First</button><button id="second">Second</button><br><button id="third">Third</button></main>';

void test('tab order walk reaches every control in reading order with a visible indicator and a full target', async () => {
    await render(page);
    const result = await walkTabOrder(browser, options);
    assert.deepEqual(result.failures, []);
    assert.deepEqual(
        result.stops.map((stop) => stop.element),
        ['button#menu', 'button#first', 'button#second', 'button#third'],
    );
    assert.ok(result.stops.every((stop) => stop.focusContrast > 3));
});

void test('tab order walk fails order, reach, target and indicator defects', async () => {
    const defects = [
        [
            'reordered in a row',
            page,
            '#first{order:2}main{display:flex}',
            '',
            /button#second after button#first leaves reading order/,
        ],
        [
            'region read backwards',
            page,
            'body{display:flex;flex-direction:column-reverse}',
            '',
            /Region main is read before region header/,
        ],
        [
            'later region first',
            '<main><button id="first">First</button></main><header><button id="menu">Menu</button></header>',
            'body{display:flex;flex-direction:column-reverse}',
            '',
            /button#menu after button#first leaves reading order/,
        ],
        [
            'trapped before the end',
            page,
            '',
            "document.querySelector('#first').addEventListener('keydown', event => event.preventDefault())",
            /Tab did not reach button#second/,
        ],
        [
            'small target',
            page,
            '#second{min-height:0;height:30px}',
            '',
            /button#second: Target [\d.]+x30 below 44x44/,
        ],
        [
            'faint indicator',
            page,
            '#second:focus-visible{outline-color:#ddd}',
            '',
            /button#second: Insufficient focus indicator contrast/,
        ],
        [
            'no indicator',
            page,
            '#second:focus-visible{outline:none}',
            '',
            /button#second: No visible focus indicator/,
        ],
        [
            'unresolved indicator',
            page,
            '#second:focus-visible{outline-color:lab(10 0 0)}',
            '',
            /button#second: Focus indicator colours or compositing are unsupported/,
        ],
        [
            'outside the regions',
            page + '<button id="stray">Stray</button>',
            '',
            '',
            /button#stray: Outside the declared reading regions/,
        ],
    ];
    for (const [name, markup, css, script, expected] of defects) {
        await render(markup, css, script);
        const { failures } = await walkTabOrder(browser, options);
        assert.ok(
            failures.some((failure) => expected.test(failure)),
            `${name}: ${failures.join('; ')}`,
        );
    }
});

const dialog =
    '<button id="open">Open</button><div id="panel" role="dialog" hidden><button id="inside">Inside</button></div>';
const dialogScript = (restores) =>
    `{ const opener = document.querySelector('#open'), panel = document.querySelector('#panel');
    opener.addEventListener('click', () => { panel.hidden = false; document.querySelector('#inside').focus(); });
    panel.addEventListener('keydown', event => { if (event.key !== 'Escape') return; panel.hidden = true; ${restores ? 'opener.focus();' : ''} }); }`;
const steps = [
    { tabTo: '#open' },
    {
        press: 'Enter',
        assertions: [{ selector: '#inside:focus-visible', count: 1 }],
        layout: { root: 'body', panels: '#panel', content: '#inside' },
    },
    {
        press: 'Escape',
        assertions: [
            { selector: '#panel[hidden]', count: 1 },
            { selector: '#open:focus', count: 1 },
        ],
    },
];

void test('keyboard steps open an overlay with real keys and require focus back on its opener', async () => {
    await render(dialog, '', dialogScript(true));
    const passing = await runSteps(browser, steps);
    assert.deepEqual(passing.failures, []);
    assert.equal(passing.steps.length, 3);
    await render(dialog, '', dialogScript(false));
    const failing = await runSteps(browser, steps);
    assert.deepEqual(failing.failures, [
        'step 3 (Escape): #open:focus: Wrong element count',
        'step 3 (Escape): #open:focus: Missing element',
    ]);
});

void test('keyboard steps fail unreachable targets, clipped overlays and unknown steps', async () => {
    await render(
        dialog,
        '#panel{width:600px}#inside{width:500px}',
        dialogScript(true),
    );
    const result = await runSteps(browser, [
        ...steps.slice(0, 2),
        { tabTo: '#absent' },
        { press: 'F13' },
        { press: 'Enter', click: '#open' },
    ]);
    for (const expected of [
        /step 2 \(Enter\): Outside viewport/,
        /step 3 \(tabTo #absent\): Tab did not reach #absent/,
        /step 4: Unsupported keyboard step/,
        /step 5: Unsupported keyboard step/,
    ])
        assert.ok(
            result.failures.some((failure) => expected.test(failure)),
            result.failures.join('; '),
        );
});

void test('tab order is read with every scroll container back at its start', async () => {
    const buttons = Array.from(
        { length: 12 },
        (_, index) => `<button id="item-${index}">Item ${index}</button><br>`,
    ).join('');
    await render(
        `<header><button id="menu">Menu</button></header><main><button id="top">Top</button><div id="scroller">${buttons}</div></main>`,
        '#scroller{height:150px;overflow:auto;padding:8px}',
    );
    const result = await walkTabOrder(browser, {
        regions: ['header', 'main'],
    });
    assert.deepEqual(result.failures, []);
    assert.equal(result.stops.length, 14);
});

// Drives real key presses through CDP: the Tab walk of a page and the steps that operate an overlay.
import { measureAssertions } from './assertions.mjs';
import { measureFocus, measureLayout } from './measure.mjs';

const KEYS = {
    Tab: { code: 'Tab', keyCode: 9 },
    ShiftTab: { key: 'Tab', code: 'Tab', keyCode: 9, modifiers: 8 },
    Enter: { code: 'Enter', keyCode: 13, text: '\r' },
    Escape: { code: 'Escape', keyCode: 27 },
    Space: { key: ' ', code: 'Space', keyCode: 32, text: ' ' },
    ArrowDown: { code: 'ArrowDown', keyCode: 40 },
    ArrowUp: { code: 'ArrowUp', keyCode: 38 },
};
const STEP_KEYS = ['press', 'tabTo', 'assertions', 'layout'];
const LAYOUT_KEYS = ['root', 'panels', 'content'];
const WALK_KEYS = ['regions', 'focusContrast', 'minTarget'];

// Unsupported input is reported, never read as "nothing to check".
const isRecord = (value) =>
    value !== null && typeof value === 'object' && !Array.isArray(value);
const onlyKeys = (value, keys) =>
    isRecord(value) && Object.keys(value).every((key) => keys.includes(key));
const isSelector = (value) => typeof value === 'string' && value.trim() !== '';
const optional = (record, key, valid) =>
    !Object.hasOwn(record, key) || valid(record[key]);
const isMinimum = (value) => Number.isFinite(value) && value >= 1;

const validWalk = (options) =>
    onlyKeys(options, WALK_KEYS) &&
    Array.isArray(options.regions) &&
    options.regions.length > 0 &&
    options.regions.every(isSelector) &&
    optional(options, 'focusContrast', isMinimum) &&
    optional(options, 'minTarget', isMinimum);

const validLayout = (layout) =>
    onlyKeys(layout, LAYOUT_KEYS) &&
    isSelector(layout.root) &&
    isSelector(layout.panels) &&
    optional(layout, 'content', isSelector);

const inBrowser = (browser, fn, ...args) =>
    browser.evaluate(
        `(${fn.toString()})(${args.map((arg) => JSON.stringify(arg)).join(',')})`,
    );

// Waits for the frames, transitions and overlay animations a key press starts.
const settle = (browser) =>
    browser.evaluate(`(async () => {
        const frame = () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
        await frame();
        const finite = document.getAnimations().filter(a => Number.isFinite(a.effect?.getComputedTiming().endTime));
        await Promise.race([Promise.all(finite.map(a => a.finished.catch(() => {}))), pause(1500)]);
        await pause(100);
        await frame();
    })()`);

async function press(browser, name) {
    const { key = name, code, keyCode, text, modifiers = 0 } = KEYS[name];
    const event = { key, code, modifiers, windowsVirtualKeyCode: keyCode };
    await browser.send('Input.dispatchKeyEvent', {
        type: 'keyDown',
        ...event,
        ...(text ? { text } : {}),
    });
    await browser.send('Input.dispatchKeyEvent', { type: 'keyUp', ...event });
    await settle(browser);
}

// Sequential navigation restarts at the top of the document.
function restart() {
    document.body.tabIndex = -1;
    document.body.focus();
    document.body.removeAttribute('tabindex');
    window.scrollTo(0, 0);
    window.__tabStops = [];
}

function recordStop() {
    const element = document.activeElement;
    if (element === document.body || window.__tabStops.includes(element))
        return null;
    window.__tabStops.push(element);
    const name = element.dataset.test
        ? `[data-test=${element.dataset.test}]`
        : element.id && !element.id.startsWith('reka-')
          ? `#${element.id}`
          : `[${element.textContent.trim().replace(/\s+/g, ' ').slice(0, 40) || element.getAttribute('aria-label') || element.dataset.slot}]`;
    return element.tagName.toLowerCase() + name;
}

// Geometry is read after the walk, with the page and every scroll container back at its start.
function describeWalk(regions) {
    for (const element of document.querySelectorAll('*'))
        if (element.scrollTop || element.scrollLeft) element.scrollTo(0, 0);
    window.scrollTo(0, 0);
    const box = (element) => {
        const { top, bottom, left, right, width, height } =
            element.getBoundingClientRect();
        return { top, bottom, left, right, width, height };
    };
    const regionOf = (element) =>
        regions.findIndex((selector) => element.closest(selector));
    const tabbable = [
        ...document.querySelectorAll(
            'a[href],button,input:not([type="hidden"]),select,textarea,summary,[tabindex]',
        ),
    ].filter(
        (element) =>
            element.tabIndex >= 0 &&
            !element.disabled &&
            !element.closest('[inert],[aria-hidden="true"]') &&
            element.getClientRects().length > 0 &&
            getComputedStyle(element).visibility !== 'hidden',
    );
    return {
        stops: window.__tabStops.map((element) => ({
            region: regionOf(element),
            box: box(element),
        })),
        missed: tabbable
            .filter((element) => !window.__tabStops.includes(element))
            .map(
                (element) =>
                    element.tagName.toLowerCase() +
                    (element.id
                        ? `#${element.id}`
                        : `[${element.textContent.trim().slice(0, 40)}]`),
            ),
        regions: regions.map((selector) => {
            const element = document.querySelector(selector);
            return element && element.getClientRects().length
                ? box(element)
                : null;
        }),
    };
}

// `next` is wholly above `previous`, or wholly to its left on the same row.
const readBefore = (next, previous) =>
    next.bottom <= previous.top + 1 ||
    (next.top < previous.bottom - 1 &&
        previous.top < next.bottom - 1 &&
        next.right <= previous.left + 1);

function orderFailures(stops, regions, names) {
    const failures = [];
    const present = regions
        .map((box, index) => ({ box, name: names[index] }))
        .filter((region) => region.box);
    for (let index = 1; index < present.length; index++) {
        const previous = present[index - 1],
            next = present[index];
        if (readBefore(next.box, previous.box))
            failures.push(
                `Region ${next.name} is read before region ${previous.name}`,
            );
    }
    for (let index = 1; index < stops.length; index++) {
        const previous = stops[index - 1],
            next = stops[index];
        const backwards =
            next.region < previous.region ||
            (next.region === previous.region &&
                readBefore(next.box, previous.box));
        if (backwards)
            failures.push(
                `Tab order: ${next.element} after ${previous.element} leaves reading order`,
            );
    }
    return failures;
}

export async function walkTabOrder(browser, options, maxTabs = 100) {
    if (!validWalk(options))
        return { stops: [], failures: ['Unsupported tab order options'] };
    const { regions, focusContrast, minTarget } = options;
    await inBrowser(browser, restart);
    await settle(browser);
    const walked = [],
        failures = [];
    let complete = false;
    for (let step = 0; step <= maxTabs && !complete; step++) {
        await press(browser, 'Tab');
        const element = await inBrowser(browser, recordStop);
        if (element === null) {
            complete = true;
            continue;
        }
        const focus = await inBrowser(browser, measureFocus, [':focus']);
        const indicator = focusContrast
            ? await inBrowser(browser, measureAssertions, [
                  { selector: ':focus', focusContrast },
              ])
            : { results: [], failures: [], unverified: [] };
        failures.push(
            ...[
                ...focus.failures,
                ...indicator.failures,
                ...indicator.unverified,
            ].map(
                (message) => `${element}: ${message.replace(/^:focus: /, '')}`,
            ),
        );
        walked.push({
            element,
            focusContrast:
                indicator.results[0]?.observed[0]?.focusContrast ?? null,
        });
    }
    if (!complete)
        failures.push(`Tab walk did not finish in ${maxTabs} presses`);
    if (!walked.length) failures.push('Tab reached no control');
    const geometry = await inBrowser(browser, describeWalk, regions);
    const stops = walked.map((stop, index) => ({
        ...stop,
        ...geometry.stops[index],
    }));
    failures.push(
        ...geometry.missed.map((name) => `Tab did not reach ${name}`),
    );
    for (const { element, region, box } of stops) {
        if (region < 0)
            failures.push(`${element}: Outside the declared reading regions`);
        if (
            minTarget &&
            (box.width + 0.5 < minTarget || box.height + 0.5 < minTarget)
        )
            failures.push(
                `${element}: Target ${Math.round(box.width * 10) / 10}x${Math.round(box.height * 10) / 10} below ${minTarget}x${minTarget}`,
            );
    }
    failures.push(...orderFailures(stops, geometry.regions, regions));
    return { stops, failures };
}

function matchesFocus(selector) {
    return !!document.activeElement?.matches(selector);
}

async function tabTo(browser, selector, maxTabs) {
    for (let step = 0; step < maxTabs; step++) {
        if (await inBrowser(browser, matchesFocus, selector)) return true;
        await press(browser, 'Tab');
    }
    return false;
}

const validStep = (step) =>
    onlyKeys(step, STEP_KEYS) &&
    Object.hasOwn(step, 'press') !== Object.hasOwn(step, 'tabTo') &&
    optional(step, 'press', (key) => Object.hasOwn(KEYS, key)) &&
    optional(step, 'tabTo', isSelector) &&
    optional(step, 'assertions', Array.isArray) &&
    optional(step, 'layout', validLayout);

export async function runSteps(browser, steps, maxTabs = 100) {
    if (!Array.isArray(steps) || !steps.length)
        return { steps: [], failures: ['Unsupported keyboard steps'] };
    const records = [],
        failures = [];
    for (const [index, step] of steps.entries()) {
        const number = index + 1;
        if (!validStep(step)) {
            failures.push(`step ${number}: Unsupported keyboard step`);
            continue;
        }
        const label = step.press ?? `tabTo ${step.tabTo}`;
        const own = [];
        if (step.press) await press(browser, step.press);
        else if (!(await tabTo(browser, step.tabTo, maxTabs)))
            own.push(`Tab did not reach ${step.tabTo}`);
        const assertions = await inBrowser(
            browser,
            measureAssertions,
            step.assertions ?? [],
        );
        own.push(...assertions.failures, ...assertions.unverified);
        if (step.layout)
            own.push(
                ...(await inBrowser(browser, measureLayout, step.layout))
                    .failures,
            );
        failures.push(
            ...own.map((message) => `step ${number} (${label}): ${message}`),
        );
        records.push({ index, label, assertions, failures: own });
    }
    return { steps: records, failures };
}

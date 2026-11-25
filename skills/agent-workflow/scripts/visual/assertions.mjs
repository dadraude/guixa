// Self-contained: evaluated in the fixture's browser and isolated regression tests.
export function measureAssertions(assertions = []) {
    const results = [],
        failures = [],
        unverified = [];
    const pixels = (value) =>
        /^-?[0-9]+(?:\.[0-9]+)?px$/.test(value)
            ? Number.parseFloat(value)
            : NaN;
    const known = [
        'selector',
        'count',
        'minWidth',
        'minHeight',
        'fontSizeRem',
        'gapRem',
        'rowGapRem',
        'columnGapRem',
        'styles',
        'text',
        'attributes',
        'contrast',
        'borderContrast',
        'borderSides',
        'focusContrast',
        'fillContrast',
        'filter',
    ];
    const sides = ['top', 'right', 'bottom', 'left'];
    const shown = (node) =>
        node.getClientRects().length > 0 &&
        getComputedStyle(node).visibility !== 'hidden';
    // Screen-reader text lives in a box clipped away or down to a pixel.
    const clippedToPixel = (node) => {
        for (let current = node; current; current = current.parentElement) {
            const box = current.getBoundingClientRect();
            const style = getComputedStyle(current);
            if (
                style.clipPath === 'inset(50%)' ||
                (box.width <= 1 &&
                    box.height <= 1 &&
                    style.overflow === 'hidden')
            )
                return true;
        }
        return false;
    };
    // Enabled elements on screen that render their own text.
    const rendersText = (node) => {
        return (
            shown(node) &&
            !clippedToPixel(node) &&
            !node.closest(':disabled,[aria-disabled="true"],[data-disabled]') &&
            (node.matches('input:not([type="hidden"]),textarea') ||
                [...node.childNodes].some(
                    (child) =>
                        child.nodeType === Node.TEXT_NODE &&
                        child.textContent.trim(),
                ))
        );
    };
    const filters = { visible: shown, text: rendersText };
    const color = (value) => {
        const m = value.match(
            /^rgba?\(([\d.]+)[, ]+([\d.]+)[, ]+([\d.]+)(?:\s*[,/]\s*([\d.]+))?\)$/,
        );
        return m
            ? [Number(m[1]), Number(m[2]), Number(m[3]), Number(m[4] ?? 1)]
            : null;
    };
    const over = (front, back) => {
        const alpha = front[3] + back[3] * (1 - front[3]);
        return [
            ...front
                .slice(0, 3)
                .map((v, i) =>
                    alpha
                        ? (v * front[3] + back[i] * back[3] * (1 - front[3])) /
                          alpha
                        : 0,
                ),
            alpha,
        ];
    };
    const luminance = (rgb) =>
        rgb
            .slice(0, 3)
            .map((v) => v / 255)
            .map((v) =>
                v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4,
            )
            .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
    const ratio = (first, second) => {
        const values = [luminance(first), luminance(second)].sort(
            (a, b) => a - b,
        );
        return (values[1] + 0.05) / (values[0] + 0.05);
    };
    // Truncated so that a failing 2.996 never reads as 3.00.
    const truncated = (value) => (Math.floor(value * 100) / 100).toFixed(2);
    const fromOklab = (lightness, a, b) => {
        const [l, m, s] = [
            lightness + 0.3963377774 * a + 0.2158037573 * b,
            lightness - 0.1055613458 * a - 0.0638541728 * b,
            lightness - 0.0894841775 * a - 1.291485548 * b,
        ].map((v) => v ** 3);
        return [
            4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
            -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
            -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s,
        ].map((v) => {
            const linear = Math.min(1, Math.max(0, v));
            return (
                255 *
                (linear <= 0.0031308
                    ? linear * 12.92
                    : 1.055 * linear ** (1 / 2.4) - 0.055)
            );
        });
    };
    // Edges also arrive as color(srgb ...) from color-mix() and as oklab(...) from opacity utilities.
    const edgeColor = (value) => {
        const m = value.match(
            /^(color\(srgb|oklab\() ?(-?[\d.e-]+) (-?[\d.e-]+) (-?[\d.e-]+)(?: \/ ([\d.]+))?\)$/,
        );
        if (!m) return color(value);
        const channels = m.slice(2, 5).map(Number);
        return [
            ...(m[1] === 'oklab('
                ? fromOklab(...channels)
                : channels.map((v) => v * 255)),
            Number(m[5] ?? 1),
        ];
    };
    const composited = (computed) =>
        computed.backgroundImage === 'none' &&
        Number(computed.opacity) === 1 &&
        computed.filter === 'none' &&
        computed.mixBlendMode === 'normal';
    // The opaque colour painted by the node and its ancestors, or null when it cannot be resolved.
    const backdrop = (node) => {
        let background = [0, 0, 0, 0];
        for (let current = node; current; current = current.parentElement) {
            const computed = getComputedStyle(current);
            const parsed = edgeColor(computed.backgroundColor);
            if (!composited(computed) || !parsed) return null;
            if (background[3] < 1) background = over(background, parsed);
        }
        return background[3] < 1 ? null : background;
    };
    // WCAG 1.4.11: each border side against the colour adjacent to it outside the element.
    const measureBorder = (node, rule, record, observed) => {
        const style = getComputedStyle(node);
        const outside = backdrop(node.parentElement);
        const own = edgeColor(style.backgroundColor);
        const edges = (rule.borderSides ?? sides).map((side) => ({
            side,
            style: style.getPropertyValue(`border-${side}-style`),
            width: pixels(style.getPropertyValue(`border-${side}-width`)),
            color: edgeColor(style.getPropertyValue(`border-${side}-color`)),
        }));
        const supported =
            shown(node) &&
            outside &&
            own &&
            composited(style) &&
            style.borderImageSource === 'none' &&
            edges.every(
                (edge) =>
                    ['none', 'hidden', 'solid'].includes(edge.style) &&
                    edge.color,
            );
        if (!supported) {
            record.unverified.push(
                'Border contrast colours or compositing are unsupported',
            );
            return;
        }
        // A translucent border shows the element's own background only when that background reaches under it.
        const under =
            style.backgroundClip === 'border-box'
                ? over(own, outside)
                : outside;
        observed.borderSides = {};
        for (const edge of edges) {
            if (edge.style !== 'solid' || !(edge.width > 0)) {
                record.failures.push('No visible border: ' + edge.side);
                continue;
            }
            const measured = ratio(over(edge.color, under), outside);
            observed.borderSides[edge.side] = measured;
            if (measured < rule.borderContrast)
                record.failures.push(
                    `Insufficient border contrast: ${edge.side} ${truncated(measured)}`,
                );
        }
        const measured = Object.values(observed.borderSides);
        if (measured.length) observed.borderContrast = Math.min(...measured);
    };
    const clippedAway = (node, x, y) => {
        for (
            let current = node.parentElement;
            current && current !== document.body;
            current = current.parentElement
        ) {
            const style = getComputedStyle(current);
            const box = current.getBoundingClientRect();
            if (
                (style.overflowX !== 'visible' &&
                    (x < box.left || x > box.right)) ||
                (style.overflowY !== 'visible' &&
                    (y < box.top || y > box.bottom))
            )
                return true;
        }
        return false;
    };
    // What is painted under one point of an indicator, which for a positioned control is not its DOM ancestors.
    const paintedAt = (node, x, y, inward) => {
        let background = [0, 0, 0, 0];
        for (const element of document.elementsFromPoint(x, y)) {
            if (node.contains(element) && !(inward && element === node))
                continue;
            const computed = getComputedStyle(element);
            const parsed = edgeColor(computed.backgroundColor);
            if (
                !composited(computed) ||
                !parsed ||
                element.matches('img,video,canvas')
            )
                return null;
            background = over(background, parsed);
            if (background[3] >= 1) return background;
        }
        return null;
    };
    // The points along an indicator: `distance` runs outward from the border box, negative inward.
    const indicatorPoints = (node, distance) => {
        const box = node.getBoundingClientRect();
        const x = box.left + box.width / 2,
            y = box.top + box.height / 2;
        return [
            [x, box.top - distance],
            [x, box.bottom + distance],
            [box.left - distance, y],
            [box.right + distance, y],
        ].filter(
            ([px, py]) =>
                px >= 0 &&
                px < innerWidth &&
                py >= 0 &&
                py < innerHeight &&
                (distance < 0 || !clippedAway(node, px, py)),
        );
    };
    // Outlines and solid rings; anything else that could draw the indicator is reported, never guessed.
    const indicators = (style) => {
        const found = { rings: [], unresolved: false, other: false };
        const width = pixels(style.outlineWidth),
            offset = pixels(style.outlineOffset);
        if (style.outlineStyle !== 'none' && width !== 0) {
            const parsed = edgeColor(style.outlineColor);
            if (style.outlineStyle !== 'solid' || !parsed)
                found.unresolved = true;
            else if (parsed[3] > 0)
                found.rings.push({
                    color: parsed,
                    distance: offset + width / 2,
                });
        }
        const shadows =
            style.boxShadow === 'none'
                ? []
                : style.boxShadow.split(/,(?![^(]*\))/);
        for (const shadow of shadows) {
            const m = shadow
                .trim()
                .match(
                    /^(.+\)) (-?[\d.]+)px (-?[\d.]+)px ([\d.]+)px (-?[\d.]+)px( inset)?$/,
                );
            const parsed = m && edgeColor(m[1]);
            if (!parsed) {
                found.unresolved = true;
                continue;
            }
            const [x, y, blur, spread] = m.slice(2, 6).map(Number);
            if (parsed[3] === 0 || (!x && !y && !blur && !spread)) continue;
            if (x || y || blur || spread < 0) found.other = true;
            else
                found.rings.push({
                    color: parsed,
                    distance: m[6]
                        ? -(pixels(style.borderTopWidth) + spread / 2)
                        : spread / 2,
                });
        }
        return found;
    };
    // WCAG 1.4.11: the strongest indicator of the focused element, at its weakest visible point.
    const measureFocusContrast = (node, rule, record, observed) => {
        if (node !== document.activeElement) {
            record.failures.push('Element is not focused');
            return;
        }
        const found = indicators(getComputedStyle(node));
        let unresolved = found.unresolved;
        for (let current = node; current; current = current.parentElement)
            if (!composited(getComputedStyle(current))) unresolved = true;
        const ratios = [];
        // A modal stops the page behind it from being hit-tested; sampling needs every painted element.
        const hitTestAll = document.createElement('style');
        hitTestAll.textContent = '*{pointer-events:auto!important}';
        document.head.append(hitTestAll);
        for (const ring of found.rings) {
            const measured = indicatorPoints(node, ring.distance).map(
                ([x, y]) => {
                    const under = paintedAt(node, x, y, ring.distance < 0);
                    return under && ratio(over(ring.color, under), under);
                },
            );
            if (measured.includes(null)) unresolved = true;
            else if (measured.length) ratios.push(Math.min(...measured));
        }
        hitTestAll.remove();
        const best = ratios.length ? Math.max(...ratios) : null;
        const passes = best !== null && best >= rule.focusContrast;
        if (unresolved || (!passes && found.other)) {
            record.unverified.push(
                'Focus indicator colours or compositing are unsupported',
            );
            return;
        }
        observed.focusContrast = best;
        if (best === null) record.failures.push('No visible focus indicator');
        else if (!passes)
            record.failures.push(
                'Insufficient focus indicator contrast: ' + truncated(best),
            );
    };
    // A divider drawn as a thin filled element rather than as a border.
    const measureFill = (node, rule, record, observed) => {
        const style = getComputedStyle(node);
        const outside = backdrop(node.parentElement);
        const fill = edgeColor(style.backgroundColor);
        if (!shown(node) || !outside || !fill || !composited(style)) {
            record.unverified.push(
                'Fill contrast colours or compositing are unsupported',
            );
            return;
        }
        if (fill[3] === 0) {
            record.failures.push('No visible fill');
            return;
        }
        observed.fillContrast = ratio(over(fill, outside), outside);
        if (observed.fillContrast < rule.fillContrast)
            record.failures.push(
                'Insufficient fill contrast: ' +
                    truncated(observed.fillContrast),
            );
    };
    const validBorderRule = (rule) =>
        Number.isFinite(rule.borderContrast) &&
        rule.borderContrast >= 1 &&
        (rule.borderSides === undefined ||
            (Array.isArray(rule.borderSides) &&
                rule.borderSides.length > 0 &&
                rule.borderSides.every((side) => sides.includes(side))));
    for (const [index, rule] of assertions.entries()) {
        const record = {
            index,
            selector: rule.selector,
            observed: [],
            failures: [],
            unverified: [],
        };
        if (
            !rule.selector ||
            Object.keys(rule).some((key) => !known.includes(key))
        )
            record.unverified.push('Unsupported assertion or missing selector');
        else if (rule.filter !== undefined && !filters[rule.filter])
            record.unverified.push('Unsupported element filter');
        else {
            let nodes = [];
            try {
                nodes = [...document.querySelectorAll(rule.selector)];
            } catch {
                record.unverified.push('Invalid selector');
            }
            if (rule.filter) nodes = nodes.filter(filters[rule.filter]);
            record.matched = nodes.length;
            if (rule.count !== undefined && nodes.length !== rule.count)
                record.failures.push('Wrong element count');
            if (!nodes.length && rule.count !== 0)
                record.failures.push('Missing element');
            for (const node of nodes) {
                const style = getComputedStyle(node),
                    rect = node.getBoundingClientRect();
                const rem = parseFloat(
                    getComputedStyle(document.documentElement).fontSize,
                );
                const rowGapRem = pixels(style.rowGap) / rem;
                const columnGapRem = pixels(style.columnGap) / rem;
                const observed = {
                    width: rect.width,
                    height: rect.height,
                    fontSizeRem: parseFloat(style.fontSize) / rem,
                    rowGapRem,
                    columnGapRem,
                    gapRem: rowGapRem === columnGapRem ? rowGapRem : NaN,
                };
                if (
                    [
                        'minWidth',
                        'minHeight',
                        'fontSizeRem',
                        'gapRem',
                        'rowGapRem',
                        'columnGapRem',
                    ].some((key) => rule[key] !== undefined) &&
                    !shown(node)
                )
                    record.failures.push('Measured element is hidden');
                for (const [key, actual] of [
                    ['minWidth', rect.width],
                    ['minHeight', rect.height],
                    ['fontSizeRem', observed.fontSizeRem],
                    ['gapRem', observed.gapRem],
                    ['rowGapRem', observed.rowGapRem],
                    ['columnGapRem', observed.columnGapRem],
                ]) {
                    if (rule[key] === undefined) continue;
                    if (!Number.isFinite(rule[key]) || !Number.isFinite(actual))
                        record.unverified.push(
                            'Unsupported numeric measurement: ' + key,
                        );
                    else if (
                        key.startsWith('min')
                            ? actual + 0.5 < rule[key]
                            : Math.abs(actual - rule[key]) > 0.01
                    )
                        record.failures.push(
                            key +
                                ': expected ' +
                                rule[key] +
                                ', measured ' +
                                actual,
                        );
                }
                observed.styles = {};
                for (const [property, expected] of Object.entries(
                    rule.styles ?? {},
                )) {
                    const actual = style.getPropertyValue(property);
                    observed.styles[property] = actual;
                    if (!actual)
                        record.unverified.push(
                            'Unsupported CSS property: ' + property,
                        );
                    else if (actual !== expected)
                        record.failures.push('Wrong style: ' + property);
                }
                if (rule.text !== undefined) {
                    observed.text = node.textContent.trim();
                    if (observed.text !== rule.text)
                        record.failures.push('Wrong text');
                }
                observed.attributes = {};
                for (const [name, expected] of Object.entries(
                    rule.attributes ?? {},
                )) {
                    observed.attributes[name] = node.getAttribute(name);
                    if (node.getAttribute(name) !== expected)
                        record.failures.push('Wrong attribute: ' + name);
                }
                if (rule.contrast !== undefined) {
                    let background = [0, 0, 0, 0];
                    let supported =
                        shown(node) &&
                        Number.isFinite(rule.contrast) &&
                        rule.contrast >= 1;
                    for (
                        let parent = node;
                        parent;
                        parent = parent.parentElement
                    ) {
                        const computed = getComputedStyle(parent);
                        if (
                            computed.backgroundImage !== 'none' ||
                            Number(computed.opacity) !== 1 ||
                            computed.filter !== 'none' ||
                            computed.mixBlendMode !== 'normal'
                        )
                            supported = false;
                        const parsed = color(computed.backgroundColor);
                        if (!parsed) supported = false;
                        else if (background[3] < 1)
                            background = over(background, parsed);
                    }
                    const foreground = color(style.color);
                    if (!foreground || background[3] < 1 || !supported)
                        record.unverified.push(
                            'Contrast background/compositing is unsupported',
                        );
                    else {
                        observed.contrast = ratio(
                            over(foreground, background),
                            background,
                        );
                        if (observed.contrast < rule.contrast)
                            record.failures.push(
                                'Insufficient contrast: ' +
                                    observed.contrast.toFixed(2),
                            );
                    }
                }
                if (
                    rule.borderContrast !== undefined ||
                    rule.borderSides !== undefined
                ) {
                    if (validBorderRule(rule))
                        measureBorder(node, rule, record, observed);
                    else record.unverified.push('Invalid border contrast rule');
                }
                if (rule.focusContrast !== undefined) {
                    if (
                        Number.isFinite(rule.focusContrast) &&
                        rule.focusContrast >= 1
                    )
                        measureFocusContrast(node, rule, record, observed);
                    else record.unverified.push('Invalid focus contrast rule');
                }
                if (rule.fillContrast !== undefined) {
                    if (
                        Number.isFinite(rule.fillContrast) &&
                        rule.fillContrast >= 1
                    )
                        measureFill(node, rule, record, observed);
                    else record.unverified.push('Invalid fill contrast rule');
                }
                record.observed.push(observed);
            }
        }
        record.passed = !record.failures.length && !record.unverified.length;
        failures.push(
            ...record.failures.map((message) => rule.selector + ': ' + message),
        );
        unverified.push(
            ...record.unverified.map(
                (message) => rule.selector + ': ' + message,
            ),
        );
        results.push(record);
    }
    return { results, failures, unverified };
}

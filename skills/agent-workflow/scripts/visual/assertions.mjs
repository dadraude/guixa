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
    ];
    const sides = ['top', 'right', 'bottom', 'left'];
    const shown = (node) =>
        node.getClientRects().length > 0 &&
        getComputedStyle(node).visibility !== 'hidden';
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
    // Borders also arrive as color(srgb ...), the computed form of a color-mix() divider.
    const edgeColor = (value) => {
        const m = value.match(
            /^color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: \/ ([\d.]+))?\)$/,
        );
        return m
            ? [...m.slice(1, 4).map((v) => v * 255), Number(m[4] ?? 1)]
            : color(value);
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
            // Truncated so that a failing 2.996 never reads as 3.00.
            const shownRatio = (Math.floor(measured * 100) / 100).toFixed(2);
            if (measured < rule.borderContrast)
                record.failures.push(
                    `Insufficient border contrast: ${edge.side} ${shownRatio}`,
                );
        }
        const measured = Object.values(observed.borderSides);
        if (measured.length) observed.borderContrast = Math.min(...measured);
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
        else {
            let nodes = [];
            try {
                nodes = [...document.querySelectorAll(rule.selector)];
            } catch {
                record.unverified.push('Invalid selector');
            }
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

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
    // Properties that change how, or whether, a box is painted.
    const plainPaint = (style) =>
        Number(style.opacity) === 1 &&
        style.filter === 'none' &&
        style.mixBlendMode === 'normal' &&
        style.clipPath === 'none' &&
        style.maskImage === 'none';
    const inTopLayer = (element) => element.matches(':modal, :popover-open');
    // A control painted with any of these, itself or through an ancestor, cannot have its indicator located.
    const unlocatable = (element) => {
        const style = getComputedStyle(element);
        return (
            !plainPaint(style) ||
            style.clip !== 'auto' ||
            /paint|strict|content/.test(style.contain) ||
            style.contentVisibility !== 'visible' ||
            !/^(none|matrix\(1, 0, 0, 1, [-\d.e]+, [-\d.e]+\))$/.test(
                style.transform,
            ) ||
            style.rotate !== 'none' ||
            style.scale !== 'none' ||
            Number(style.zIndex) < 0 ||
            inTopLayer(element)
        );
    };
    const isPositioned = (style) => style.position !== 'static';
    // Where a branch below the common ancestor is painted: its z-level and whether anything in it is positioned.
    // Null when the branch uses CSS whose painting order is not modelled here. Chrome versions differ on whether a
    // size container keeps its descendants' z-index inside it, so the caller states which reading it asks for.
    const layerOf = (branch, containersTrap) => {
        let positioned = false;
        for (const element of branch) {
            const style = getComputedStyle(element);
            if (
                inTopLayer(element) ||
                style.order !== '0' ||
                style.willChange !== 'auto' ||
                style.contain !== 'none' ||
                style.backdropFilter !== 'none' ||
                style.transformStyle !== 'flat'
            )
                return null;
            const flexItem = /flex|grid/.test(
                getComputedStyle(element.parentElement).display,
            );
            if (style.zIndex !== 'auto' && (isPositioned(style) || flexItem))
                return { positioned: true, level: Number(style.zIndex) };
            // These keep the z-index of everything inside them at level zero.
            if (
                (containersTrap && style.containerType !== 'normal') ||
                ['fixed', 'sticky'].includes(style.position) ||
                !plainPaint(style) ||
                style.isolation !== 'auto' ||
                [
                    style.transform,
                    style.translate,
                    style.rotate,
                    style.scale,
                    style.perspective,
                ].some((value) => value !== 'none')
            )
                return { positioned: true, level: 0 };
            positioned ||= isPositioned(style);
        }
        return { positioned, level: 0 };
    };
    const pathUp = (element, end) => {
        const path = [];
        for (
            let current = element;
            current !== end;
            current = current.parentElement
        )
            path.unshift(current);
        return path;
    };
    // Whether a painted box is `above` or `beneath` an indicator of the node, or null when CSS leaves it unproven.
    // `kind` is the indicator: an `outline`, or an `inset` or `outset` shadow.
    const stackedAt = (element, node, kind) => {
        const [passing, trapping] = [false, true].map((containersTrap) =>
            stackedWith(element, node, kind, containersTrap),
        );
        return passing === trapping ? passing : null;
    };
    const stackedWith = (element, node, kind, containersTrap) => {
        if (element.contains(node)) return 'beneath';
        if (node.contains(element)) {
            const path = pathUp(element, node);
            const layer = layerOf(path, containersTrap);
            if (!layer || layer.level < 0) return null;
            // Content is painted over an inset shadow; an outline is painted over content that forms no layer.
            if (kind === 'inset') return 'above';
            return kind === 'outline' && !layer.positioned && layer.level === 0
                ? 'beneath'
                : null;
        }
        let common = element.parentElement;
        while (!common.contains(node)) common = common.parentElement;
        const [other, own] = [element, node].map((end) => {
            const layer = layerOf(pathUp(end, common), containersTrap);
            return (
                layer && {
                    ...layer,
                    rank: layer.positioned ? layer.level : -0.5,
                }
            );
        });
        if (!other || !own) return null;
        if (other.rank !== own.rank)
            return other.rank > own.rank ? 'above' : 'beneath';
        // Two boxes in the flow of one layer: which paints over an indicator depends on their kinds of box.
        if (!other.positioned) return null;
        return element.compareDocumentPosition(node) &
            Node.DOCUMENT_POSITION_FOLLOWING
            ? 'beneath'
            : 'above';
    };
    const holds = (box, x, y) =>
        x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
    const edgeWidths = (style, property) =>
        Object.fromEntries(
            sides.map((side) => [
                side,
                pixels(style.getPropertyValue(property.replace('*', side))) ||
                    0,
            ]),
        );
    const shrink = (box, by) => ({
        top: box.top + by.top,
        right: box.right - by.right,
        bottom: box.bottom - by.bottom,
        left: box.left + by.left,
    });
    // The zero-size or listed box shadows of a computed value; null when one cannot be read.
    const parseShadows = (value) => {
        if (value === 'none') return [];
        const shadows = value.split(/,(?![^(]*\))/).map((shadow) => {
            const m = shadow
                .trim()
                .match(
                    /^(.+\)) (-?[\d.]+)px (-?[\d.]+)px ([\d.]+)px (-?[\d.]+)px( inset)?$/,
                );
            const parsed = m && edgeColor(m[1]);
            if (!parsed) return null;
            const [x, y, blur, spread] = m.slice(2, 6).map(Number);
            return { color: parsed, x, y, blur, spread, inset: !!m[6] };
        });
        if (shadows.includes(null)) return null;
        return shadows.filter(
            ({ color: parsed, x, y, blur, spread }) =>
                parsed[3] > 0 && (x || y || blur || spread),
        );
    };
    const pseudoPaints = (element) =>
        ['::before', '::after'].some((pseudo) => {
            const style = getComputedStyle(element, pseudo);
            if (
                ['none', 'normal'].includes(style.content) ||
                style.display === 'none'
            )
                return false;
            const background = edgeColor(style.backgroundColor);
            const widths = edgeWidths(style, 'border-*-width');
            return (
                !background ||
                background[3] > 0 ||
                style.backgroundImage !== 'none' ||
                style.boxShadow !== 'none' ||
                style.outlineStyle !== 'none' ||
                sides.some(
                    (side) =>
                        widths[side] > 0 &&
                        style.getPropertyValue(`border-${side}-style`) !==
                            'none',
                )
            );
        });
    // The colour an element's box paints at a point: transparent when it paints nothing there, null when unknown.
    const paintOf = (element, x, y) => {
        const nothing = [0, 0, 0, 0];
        if (pseudoPaints(element)) return null;
        const root =
            element === document.documentElement || element === document.body;
        const boxes = [...element.getClientRects()];
        const box = boxes.find((rect) => holds(rect, x, y));
        if (!box && !root) return null;
        const style = getComputedStyle(element);
        const background = edgeColor(style.backgroundColor);
        if (!background) return null;
        const borders = edgeWidths(style, 'border-*-width');
        const inBorder =
            !!box &&
            !holds(shrink(box, borders), x, y) &&
            sides.some((side) => {
                const parsed = edgeColor(
                    style.getPropertyValue(`border-${side}-color`),
                );
                return borders[side] > 0 && (!parsed || parsed[3] > 0);
            });
        const replaced =
            element instanceof SVGElement ||
            element.matches('img,video,canvas,iframe,object,embed');
        if (
            !background[3] &&
            style.backgroundImage === 'none' &&
            !inBorder &&
            !replaced
        )
            return nothing;
        for (let current = element; current; current = current.parentElement)
            if (!plainPaint(getComputedStyle(current))) return null;
        if (style.backgroundImage !== 'none' || inBorder || replaced)
            return null;
        if (style.backgroundClip === 'border-box' || root) return background;
        if (style.backgroundClip === 'text') return null;
        const inner = shrink(box, borders);
        const painted =
            style.backgroundClip === 'content-box'
                ? shrink(inner, edgeWidths(style, 'padding-*'))
                : inner;
        return holds(painted, x, y) ? background : nothing;
    };
    // Tints of one colour add up. Different colours, in an unknown order, are kept as `mixed`: any mixture of them
    // lies between black and white at their joint strength.
    const joinTints = (added, tint) => {
        if (!added[3]) return tint;
        if (!tint[3]) return added;
        const alpha = over(added, tint)[3];
        const same = added.slice(0, 3).join() === tint.slice(0, 3).join();
        return same && !added[4] && !tint[4]
            ? [...tint.slice(0, 3), alpha]
            : [0, 0, 0, alpha, 'mixed'];
    };
    // The strongest tint other boxes' shadows can add at a point, which hit testing never reports: transparent when
    // none reaches it, null when it cannot be bounded.
    const shadeAt = (node, x, y) => {
        let shade = [0, 0, 0, 0];
        for (const element of document.querySelectorAll('*')) {
            if (element === node) continue;
            const style = getComputedStyle(element);
            if (style.boxShadow === 'none' || style.visibility !== 'visible')
                continue;
            const box = element.getBoundingClientRect();
            if (!box.width && !box.height) continue;
            const shadows = parseShadows(style.boxShadow);
            if (!shadows) return null;
            for (const shadow of shadows) {
                const reach =
                    Math.max(Math.abs(shadow.x), Math.abs(shadow.y)) +
                    shadow.blur +
                    shadow.spread;
                const around = (by) =>
                    shrink(box, { top: by, right: by, bottom: by, left: by });
                const reaches = shadow.inset
                    ? holds(box, x, y) && !holds(around(reach), x, y)
                    : !holds(box, x, y) && holds(around(-reach), x, y);
                if (!reaches) continue;
                for (
                    let current = element;
                    current;
                    current = current.parentElement
                )
                    if (!plainPaint(getComputedStyle(current))) return null;
                shade = joinTints(shadow.color, shade);
            }
        }
        return shade;
    };
    // An inert subtree is painted but never hit, so nothing under it can be told apart from it.
    const inertAt = (x, y) =>
        [...document.querySelectorAll('[inert], [inert] *')].some((element) =>
            [...element.getClientRects()].some((box) => holds(box, x, y)),
        );
    // Hit testing leaves out the row, group and column boxes whose backgrounds show through a table's cells.
    const missedTablePaint = (stack, x, y) =>
        [
            ...document.querySelectorAll('tr,thead,tbody,tfoot,col,colgroup'),
        ].some((element) => {
            if (stack.includes(element)) return false;
            const style = getComputedStyle(element);
            const background = edgeColor(style.backgroundColor);
            return (
                (!background ||
                    background[3] > 0 ||
                    style.backgroundImage !== 'none') &&
                holds(element.getBoundingClientRect(), x, y)
            );
        });
    // Underlines, shadows and changed letter shapes can paint where the font's own glyphs do not.
    const plainText = (element) => {
        const style = getComputedStyle(element);
        if (
            style.textTransform !== 'none' ||
            style.textShadow !== 'none' ||
            style.fontVariantCaps !== 'normal'
        )
            return false;
        for (let current = element; current; current = current.parentElement)
            if (getComputedStyle(current).textDecorationLine !== 'none')
                return false;
        return true;
    };
    const meter = document.createElement('canvas').getContext('2d');
    // Whether the ink of one character may reach a point: its box across, its glyph's height down. A character
    // box that does not match the font's own metrics counts in full.
    const inkAt = (range, character, x, y) => {
        const metrics = meter.measureText(character);
        const ascent = metrics.fontBoundingBoxAscent,
            height = ascent + metrics.fontBoundingBoxDescent;
        return [...range.getClientRects()].some((box) => {
            if (x < box.left - 2 || x > box.right + 2) return false;
            if (Math.abs(box.height - height) > 1.5) return holds(box, x, y);
            const baseline = box.top + ascent;
            return (
                y >= baseline - metrics.actualBoundingBoxAscent - 1 &&
                y <= baseline + metrics.actualBoundingBoxDescent + 1
            );
        });
    };
    // Whether glyphs of an element's own text may paint at a point, where they would be the adjacent colour.
    const textAt = (element, x, y) => {
        const style = getComputedStyle(element);
        if (style.visibility !== 'visible') return false;
        meter.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
        const plain = plainText(element);
        return [...element.childNodes].some((child) => {
            if (child.nodeType !== Node.TEXT_NODE || !child.textContent.trim())
                return false;
            const range = document.createRange();
            range.selectNodeContents(child);
            const near = [...range.getClientRects()].some(
                (box) =>
                    y >= box.top - box.height && y <= box.bottom + box.height,
            );
            if (!near) return false;
            if (!plain)
                return [...range.getClientRects()].some((box) =>
                    holds(box, x, y),
                );
            let offset = 0;
            return [...child.data].some((character) => {
                range.setStart(child, offset);
                offset += character.length;
                range.setEnd(child, offset);
                return !!character.trim() && inkAt(range, character, x, y);
            });
        });
    };
    // What is painted next to one point of an indicator: the adjacent colour and the tint shadows may add to it,
    // `unpainted` when the indicator is provably not painted there, or null when neither is proven.
    const paintedAt = (node, x, y, kind) => {
        if (inertAt(x, y)) return null;
        const shade = shadeAt(node, x, y);
        if (!shade) return null;
        const stack = document.elementsFromPoint(x, y);
        if (missedTablePaint(stack, x, y)) return null;
        let background = [0, 0, 0, 0];
        for (const element of stack) {
            const painted = paintOf(element, x, y);
            if (!painted || textAt(element, x, y)) return null;
            if (!painted[3]) continue;
            const stacked = stackedAt(element, node, kind);
            if (!stacked) return null;
            if (stacked === 'above')
                return painted[3] >= 1 && !background[3] ? 'unpainted' : null;
            background = over(background, painted);
            if (background[3] >= 1) return { colour: background, shade };
        }
        return null;
    };
    // Whether an ancestor's overflow removes the point: only an ancestor in the containing block chain provably does.
    const clippedAt = (node, x, y) => {
        let escapes = false;
        for (
            let child = node, current = node.parentElement;
            current && current !== document.body;
            child = current, current = current.parentElement
        ) {
            escapes ||= ['absolute', 'fixed'].includes(
                getComputedStyle(child).position,
            );
            const style = getComputedStyle(current);
            const box = current.getBoundingClientRect();
            const outside =
                (style.overflowX !== 'visible' &&
                    (x < box.left || x > box.right)) ||
                (style.overflowY !== 'visible' &&
                    (y < box.top || y > box.bottom));
            if (outside)
                return escapes || style.overflowClipMargin !== '0px'
                    ? null
                    : true;
        }
        return false;
    };
    // A point outside the viewport hits nothing, so unless it is clipped away it stays unknown.
    const sampleAt = (node, x, y, kind) => {
        // Nothing before the document's origin can ever be scrolled into view.
        if (x + scrollX < 0 || y + scrollY < 0) return 'unpainted';
        const clipped = clippedAt(node, x, y);
        if (clipped !== false) return clipped ? 'unpainted' : null;
        return paintedAt(node, x, y, kind);
    };
    // The four side midpoints of an indicator: `distance` runs outward from the border box, negative inward.
    const indicatorPoints = (node, distance) => {
        const box = node.getBoundingClientRect();
        const x = box.left + box.width / 2,
            y = box.top + box.height / 2;
        return [
            [x, box.top - distance],
            [x, box.bottom + distance],
            [box.left - distance, y],
            [box.right + distance, y],
        ];
    };
    // Outlines and solid rings, in painting order from the top; anything else that could draw the indicator is
    // reported, never guessed. `reach` is the span of distances each layer paints.
    const indicators = (style) => {
        const found = {
            rings: [],
            layers: [],
            unresolved: false,
            other: false,
        };
        const width = pixels(style.outlineWidth),
            offset = pixels(style.outlineOffset);
        if (style.outlineStyle !== 'none' && width !== 0) {
            const parsed = edgeColor(style.outlineColor);
            const solid = style.outlineStyle === 'solid' && !!parsed;
            const layer = {
                color: parsed,
                solid,
                kind: 'outline',
                reach: [offset, offset + width],
                distance: offset + width / 2,
            };
            if (!solid) found.unresolved = true;
            if (!solid || parsed[3] > 0) found.layers.push(layer);
            if (solid && parsed[3] > 0) found.rings.push(layer);
        }
        const shadows = parseShadows(style.boxShadow);
        if (!shadows) found.unresolved = true;
        const border = pixels(style.borderTopWidth);
        for (const shadow of shadows ?? []) {
            const solid =
                !shadow.x && !shadow.y && !shadow.blur && shadow.spread > 0;
            const spread =
                Math.max(Math.abs(shadow.x), Math.abs(shadow.y)) +
                shadow.blur +
                shadow.spread;
            const layer = {
                color: shadow.color,
                solid,
                kind: shadow.inset ? 'inset' : 'outset',
                reach: shadow.inset
                    ? [-(border + spread), -border]
                    : [0, spread],
                distance: shadow.inset
                    ? -(border + shadow.spread / 2)
                    : shadow.spread / 2,
            };
            found.layers.push(layer);
            if (solid) found.rings.push(layer);
            else found.other = true;
        }
        return found;
    };
    // What the control's own layers painted over a ring do at its sample distance: `hidden` by an opaque ring, a
    // `tint` from translucent blurred or offset shadows, or null when neither is proven.
    const underOwnLayer = (found, ring) => {
        let tint = [0, 0, 0, 0];
        for (const layer of found.layers.slice(0, found.layers.indexOf(ring))) {
            const [from, to] = layer.reach;
            // Within half a pixel of either edge the covering is partial; further out the layer does not reach.
            if (ring.distance <= from - 0.5 || ring.distance >= to + 0.5)
                continue;
            const within =
                ring.distance >= from + 0.5 && ring.distance <= to - 0.5;
            if (layer.solid)
                return within && layer.color[3] >= 1 ? { hidden: true } : null;
            if (!layer.color || layer.color[3] >= 1) return null;
            tint = joinTints(layer.color, tint);
        }
        return { tint };
    };
    // The lowest ratio a ring can show at a point: bare, or with a tint beneath it or over it and its surroundings.
    // Null when a tint could bring one colour to the other, which hides the ring.
    const lowestRatio = (ring, { colour, shade }) => {
        const bare = over(ring, colour);
        const values = [ratio(bare, colour)];
        if (!shade[3]) return values[0];
        const side = (first, second) =>
            Math.sign(luminance(first) - luminance(second));
        const extremes = shade[4]
            ? [
                  [0, 0, 0, shade[3]],
                  [255, 255, 255, shade[3]],
              ]
            : [shade];
        for (const tint of extremes) {
            const tinted = over(tint, colour);
            const beneath = over(ring, tinted);
            const covered = over(tint, bare);
            if (
                side(beneath, tinted) !== side(bare, colour) ||
                side(covered, tinted) * side(bare, colour) < 0
            )
                return null;
            values.push(ratio(beneath, tinted), ratio(covered, tinted));
        }
        return Math.min(...values);
    };
    // A ring that reaches past the viewport is measured with the page scrolled to centre the control.
    const inView = (node, reach, measure) => {
        const box = node.getBoundingClientRect();
        if (
            box.left >= reach &&
            box.top >= reach &&
            box.right + reach < innerWidth &&
            box.bottom + reach < innerHeight
        )
            return measure();
        const start = { left: scrollX, top: scrollY, behavior: 'instant' };
        scrollBy({
            left: (box.left + box.right - innerWidth) / 2,
            top: (box.top + box.bottom - innerHeight) / 2,
            behavior: 'instant',
        });
        try {
            return measure();
        } finally {
            scrollTo(start);
        }
    };
    // WCAG 1.4.11: the strongest indicator of the focused element, at its weakest painted point.
    const measureFocusContrast = (node, rule, record, observed) => {
        if (node !== document.activeElement) {
            record.failures.push('Element is not focused');
            return;
        }
        const found = indicators(getComputedStyle(node));
        let unresolved = found.unresolved;
        for (let current = node; current; current = current.parentElement)
            if (unlocatable(current)) unresolved = true;
        const ratios = [];
        // A modal stops the page behind it from being hit-tested; sampling needs every painted box.
        const hitTestAll = document.createElement('style');
        hitTestAll.textContent =
            '*,*::before,*::after{pointer-events:auto!important}';
        document.head.append(hitTestAll);
        const gradePoints = (ring) => {
            const own = underOwnLayer(found, ring);
            return indicatorPoints(node, ring.distance)
                .map(([x, y]) => {
                    const under = sampleAt(node, x, y, ring.kind);
                    // A point already hidden by the page stays hidden whatever the control paints over it.
                    if (under === 'unpainted' || own?.hidden)
                        return 'unpainted';
                    if (!own || !under) return null;
                    return {
                        colour: under.colour,
                        shade: joinTints(own.tint, under.shade),
                    };
                })
                .filter((under) => under !== 'unpainted')
                .map((under) => under && lowestRatio(ring.color, under));
        };
        const reach = Math.max(0, ...found.rings.map((ring) => ring.distance));
        const graded = inView(node, reach, () => found.rings.map(gradePoints));
        for (const measured of graded) {
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
        else if (
            rule.filter !== undefined &&
            !Object.hasOwn(filters, rule.filter)
        )
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

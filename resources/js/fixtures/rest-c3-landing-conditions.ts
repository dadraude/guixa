/*
 * Observations the C3 manifests assert as `data-ev-*` attributes on <body> (spec 005, T46): colour roles from rendered
 * colours, and the page data visible at the current width. They are recomputed when the DOM changes, so overlays count.
 */

const record = (key: string, value: string) => {
    const name = `data-ev-${key}`;
    if (document.body.getAttribute(name) !== value)
        document.body.setAttribute(name, value);
};

const shown = (element: Element) =>
    element.getClientRects().length > 0 &&
    getComputedStyle(element).visibility === 'visible';

// A landing token as the `rgb()` string computed colours use.
const token = (name: string) => {
    const scope = document.querySelector('.reference-style.landing');
    if (!scope) return 'missing';
    const value = getComputedStyle(scope).getPropertyValue(name).trim();
    const hex = value.match(/^#([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i);
    return hex
        ? `rgb(${hex
              .slice(1)
              .map((part) => parseInt(part, 16))
              .join(', ')})`
        : value;
};

// The colours an element actually paints.
export const paintedColours = (element: Element) => {
    const style = getComputedStyle(element);
    const colours = [style.color, style.backgroundColor];
    for (const side of ['top', 'right', 'bottom', 'left'])
        if (parseFloat(style.getPropertyValue(`border-${side}-width`)) > 0)
            colours.push(style.getPropertyValue(`border-${side}-color`));
    if (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0)
        colours.push(style.outlineColor);
    if (element instanceof SVGElement) colours.push(style.fill, style.stroke);
    colours.push(
        ...(style.boxShadow.match(/(?:rgba?|color|oklab)\([^)]*\)/g) ?? []),
    );
    return colours;
};

// The oklab() form opacity utilities compute to, back in 8-bit sRGB.
const fromOklab = ([lightness, a, b]: number[]) => {
    const [l, m, s] = [
        lightness + 0.3963377774 * a + 0.2158037573 * b,
        lightness - 0.1055613458 * a - 0.0638541728 * b,
        lightness - 0.0894841775 * a - 1.291485548 * b,
    ].map((value) => value ** 3);
    return [
        4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s,
    ].map((value) => {
        const linear = Math.min(1, Math.max(0, value));
        return (
            255 *
            (linear <= 0.0031308
                ? linear * 12.92
                : 1.055 * linear ** (1 / 2.4) - 0.055)
        );
    });
};

// Channels and alpha of the computed colour forms: rgb(), color(srgb) from color-mix() and oklab().
const channels = (colour: string) => {
    const match = colour.match(/^(rgba?\(|color\(srgb |oklab\()([^)]*)\)$/);
    if (!match) return null;
    const [first, second, third, alpha = 1] = match[2]
        .split(/[\s,/]+/)
        .filter(Boolean)
        .map(Number);
    const values = [first, second, third];
    if (match[1] === 'oklab(') return { rgb: fromOklab(values), alpha };
    return {
        rgb: match[1].startsWith('color')
            ? values.map((value) => value * 255)
            : values,
        alpha,
    };
};

// Whether a painted colour is the token's colour, at any opacity above none.
export const paintsToken = (colour: string, value: string) => {
    const painted = channels(colour);
    const wanted = channels(value);
    return (
        !!painted &&
        !!wanted &&
        painted.alpha > 0 &&
        painted.rgb.every(
            (channel, index) => Math.abs(channel - wanted.rgb[index]) <= 1,
        )
    );
};

const ochreUses = () => {
    const ochre = token('--welcome-ochre');
    return [...document.body.querySelectorAll('*')]
        .filter(shown)
        .filter((element) =>
            paintedColours(element).some((colour) =>
                paintsToken(colour, ochre),
            ),
        ).length;
};

// "painted/total": how many visible error texts use the danger token.
const dangerTexts = () => {
    const danger = token('--destructive');
    const errors = [
        ...document.querySelectorAll('#main-content .text-destructive'),
    ].filter((element) => shown(element) && element.textContent?.trim());
    const painted = errors.filter(
        (element) => getComputedStyle(element).color === danger,
    );
    return `${painted.length}/${errors.length}`;
};

// How many visible elements of an open menu or toast paint a status token.
const overlayUses = (name: string) => {
    const value = token(name);
    return [
        ...document.body.querySelectorAll(
            '[data-slot="dropdown-menu-content"] *, [data-sonner-toast], [data-sonner-toast] *',
        ),
    ]
        .filter(shown)
        .filter((element) =>
            paintedColours(element).some((colour) =>
                paintsToken(colour, value),
            ),
        ).length;
};

// Whether the open tooltip and its visible text lie inside the viewport and the tooltip box. The runner's layout check
// cannot say: the tooltip also holds a screen-reader copy of the text, clipped to a pixel.
const tooltipFits = () => {
    const tooltip = document.querySelector('[data-slot="tooltip-content"]');
    if (!tooltip || !shown(tooltip)) return 'none';
    const box = tooltip.getBoundingClientRect();
    const rects: DOMRect[] = [box];
    for (const node of tooltip.childNodes) {
        if (node.nodeType !== Node.TEXT_NODE) continue;
        const range = document.createRange();
        range.selectNodeContents(node);
        rects.push(...range.getClientRects());
    }
    const inside = (rect: DOMRect) =>
        rect.left >= Math.max(0, box.left - 1) &&
        rect.right <= Math.min(window.innerWidth, box.right + 1) &&
        rect.top >= Math.max(0, box.top - 1) &&
        rect.bottom <= Math.min(window.innerHeight, box.bottom + 1);
    return String(rects.length > 1 && rects.every(inside));
};

// The edge the stock quantities share: where each starts, where each ends, or neither.
const quantityEdge = () => {
    const boxes = [
        ...document.querySelectorAll(
            '#main-content [data-test="stock-offer"] .tabular-nums',
        ),
    ].map((element) => element.getBoundingClientRect());
    if (!boxes.length) return 'none';
    const shared = (edge: 'left' | 'right') =>
        boxes.every((box) => Math.abs(box[edge] - boxes[0][edge]) <= 1);
    if (shared('left')) return 'start';
    return shared('right') ? 'end' : 'mixed';
};

const noticeRole = () => {
    const notice = document.querySelector(
        '#main-content [role="status"].pilot-layout-notice',
    );
    if (!notice) return 'none';
    const colour = getComputedStyle(notice).color;
    const roles = {
        foreground: token('--foreground'),
        danger: token('--destructive'),
        ochre: token('--welcome-ochre'),
    };
    return (
        Object.entries(roles).find(([, value]) => value === colour)?.[0] ??
        colour
    );
};

// Page data as read on screen; column labels, table heads and breadcrumbs change with the layout and are left out.
const visibleData = () => {
    const root = document.querySelector('#main-content');
    if (!root) return '';
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const parts: string[] = [];
    while (walker.nextNode()) {
        const node = walker.currentNode;
        const parent = node.parentElement!;
        const text = node.textContent!.replace(/\s+/g, ' ').trim();
        if (
            !text ||
            !shown(parent) ||
            parent.closest(
                '.pilot-cell-label, thead, .reference-mobile-trail, [aria-hidden="true"]',
            )
        )
            continue;
        parts.push(text);
    }
    return parts.join(' | ');
};

const observe = () => {
    record('ochre-uses', String(ochreUses()));
    record('danger-text', dangerTexts());
    record('notice-role', noticeRole());
    record('overlay-danger', String(overlayUses('--destructive')));
    record('overlay-success', String(overlayUses('--pilot-success')));
    record('tooltip-fits', tooltipFits());
    record('quantity-edge', quantityEdge());
    record('visible-data', visibleData());
    record(
        'tokens',
        ['--destructive', '--pilot-success', '--pilot-warning']
            .map(token)
            .join(' ; '),
    );
};

export const recordConditions = () => {
    let scheduled = false;
    const schedule = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            observe();
        });
    };
    new MutationObserver(schedule).observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
    });
    window.addEventListener('focusin', schedule);
    observe();
};

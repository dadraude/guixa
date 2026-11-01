// Self-contained so the same function runs in a browser through CDP and in browser regression tests.
export function measureLayout({
    root,
    panels,
    content = 'h1,h2,h3,label,input,select,textarea,button,a,td,th,[data-visual-text]',
}) {
    const failures = [];
    const page = document.querySelector(root);
    if (!page) return { failures: [`Missing root: ${root}`] };
    const shown = (element) =>
        element.getClientRects().length > 0 &&
        getComputedStyle(element).visibility !== 'hidden' &&
        getComputedStyle(element).display !== 'none';
    const describe = (element) =>
        element.id
            ? `#${element.id}`
            : `${element.tagName.toLowerCase()}: ${element.textContent.trim().slice(0, 50)}`;
    const insideX = (box, bounds) =>
        box.left >= bounds.left - 1 && box.right <= bounds.right + 1;
    const contentBox = (element) => {
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return {
            left:
                box.left +
                parseFloat(style.borderLeftWidth) +
                parseFloat(style.paddingLeft),
            right:
                box.right -
                parseFloat(style.borderRightWidth) -
                parseFloat(style.paddingRight),
        };
    };
    const viewport = { left: 0, right: innerWidth };
    if (document.documentElement.scrollWidth > innerWidth + 1)
        failures.push('Horizontal document overflow');
    const panelElements = [
        ...page.querySelectorAll(panels),
        ...(page.matches(panels) ? [page] : []),
    ].filter(shown);
    if (!panelElements.length) failures.push(`No visible panels: ${panels}`);
    const elements = [
        ...new Set([...panelElements, ...page.querySelectorAll(content)]),
    ].filter(shown);
    for (const element of elements) {
        const box = element.getBoundingClientRect();
        const name = describe(element);
        if (box.width <= 0 || box.height <= 0)
            failures.push(`Zero-size content: ${name}`);
        if (!insideX(box, viewport)) failures.push(`Outside viewport: ${name}`);
        const panel = element.parentElement?.closest(panels);
        if (panel && !insideX(box, contentBox(panel)))
            failures.push(`Outside panel content: ${name}`);
        const rects = [box];
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (!node.textContent.trim() || !shown(node.parentElement))
                continue;
            const range = document.createRange();
            range.selectNodeContents(node);
            for (const rect of range.getClientRects()) {
                if (!rect.width) continue;
                rects.push(rect);
                if (
                    !insideX(rect, box) ||
                    !insideX(rect, viewport) ||
                    (panel && !insideX(rect, contentBox(panel)))
                )
                    failures.push(`Text escapes container: ${name}`);
            }
        }
        for (
            // A modal lives in the top layer and escapes its DOM ancestors' clipping.
            let ancestor = element.matches(':modal')
                ? null
                : element.parentElement;
            ancestor;
            ancestor = ancestor.parentElement
        ) {
            const style = getComputedStyle(ancestor);
            const bounds = ancestor.getBoundingClientRect();
            for (const rect of rects) {
                if (
                    ['hidden', 'clip', 'auto', 'scroll'].includes(
                        style.overflowX,
                    ) &&
                    !insideX(rect, bounds)
                )
                    failures.push(`Horizontally clipped: ${name}`);
                if (
                    ['hidden', 'clip'].includes(style.overflowY) &&
                    (rect.top < bounds.top - 1 ||
                        rect.bottom > bounds.bottom + 1)
                )
                    failures.push(`Vertically clipped: ${name}`);
            }
            if (ancestor.matches(':modal')) break;
        }
    }
    return {
        viewport: innerWidth,
        scrollWidth: document.documentElement.scrollWidth,
        elements: elements.length,
        failures: [...new Set(failures)],
    };
}
export function measureFocus(selectors) {
    const element = document.activeElement;
    const matched = selectors.filter((selector) => element.matches(selector));
    if (!matched.length) return { matched, failures: [] };
    const rect = element.getBoundingClientRect();
    const style = getComputedStyle(element);
    const failures = [];
    if (
        rect.width <= 0 ||
        rect.height <= 0 ||
        rect.left < -1 ||
        rect.right > innerWidth + 1 ||
        rect.top < -1 ||
        rect.bottom > innerHeight + 1
    )
        failures.push('Focused control outside viewport');
    const hit = document.elementFromPoint(
        rect.left + rect.width / 2,
        rect.top + rect.height / 2,
    );
    if (!hit || (hit !== element && !element.contains(hit)))
        failures.push('Focused control clipped or covered');
    // Chrome's native picker button can own focus while the input remains activeElement.
    const nativePickerFocus =
        element.matches("input:is([type='date'],[type='time']):focus-within") &&
        style.outlineStyle !== 'none' &&
        parseFloat(style.outlineWidth) > 0 &&
        !['transparent', 'rgba(0, 0, 0, 0)'].includes(style.outlineColor);
    if (!element.matches(':focus-visible') && !nativePickerFocus)
        failures.push('Keyboard focus not focus-visible');
    if (style.outlineStyle === 'none' && style.boxShadow === 'none')
        failures.push(
            'No outline or shadow on focused control; inspect alternative focus styling',
        );
    return {
        matched,
        failures,
        rect: {
            left: rect.left,
            right: rect.right,
            top: rect.top,
            bottom: rect.bottom,
        },
    };
}

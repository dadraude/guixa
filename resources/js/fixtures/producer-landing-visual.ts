import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import { defineComponent, h, type DefineComponent } from 'vue';
import CutoffController from '@/actions/App/Http/Controllers/Producer/CutoffController';
import ProductAvailabilityController from '@/actions/App/Http/Controllers/Producer/ProductAvailabilityController';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import AppLayout from '@/layouts/AppLayout.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

/*
 * Deterministic review fixture for the Producer pages in the landing identity (spec 005, slice 2).
 * Only the HTTP client is mocked: the real Inertia router, layouts, pages and portals run. It proves
 * presentation, never persistence or authorization. `role` (comma separated) only picks the shell
 * navigation. The six Producer pages mount here so each page task measures its own title and look.
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Producer/Products/Index';
const state = query.get('state') ?? 'default';
const roles = (query.get('role') ?? 'producer').split(',');
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
if (!components[`../pages/${pageName}.vue`]) {
    throw new Error(`Unknown visual page: ${pageName}`);
}

const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'light' || mode === 'dark' ? mode : 'system',
);
initializeTheme();
if (query.get('fallback') === '1')
    document.documentElement.dataset.fontFallback = 'true';
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const longName =
    'Cooperativa agrícola de la Vall de les Hortes i del Ganxet tradicional de la plana';
const longUnit = 'safata de cartró reciclat de mig quilo amb tapa de vidre';
const makeProduct = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    id,
    name,
    unit: 'unitat',
    price: '3.50',
    variable: false,
    containersPerUnit: null as string | null,
    active: true,
    ...over,
});
const makeStock = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    id,
    name,
    unit: 'pot',
    price: '8.90',
    active: true,
    available: '12.000',
    ...over,
});
const inactiveBread = makeProduct('product-3', 'Brioix de l’àvia', {
    active: false,
    price: '2.25',
});
const productsByState: Record<string, ReturnType<typeof makeProduct>[]> = {
    default: [
        makeProduct('product-1', 'Pa de pagès'),
        makeProduct('product-2', 'Ous de pagès', {
            unit: 'dotzena',
            price: '4.20',
            variable: true,
            containersPerUnit: '12.000',
        }),
        inactiveBread,
    ],
    // The inactive product comes first: the first checkpoint measures this state.
    checkpoint: [inactiveBread, makeProduct('product-1', 'Pa de pagès')],
    empty: [],
    'long-name': [
        makeProduct('product-1', longName, {
            unit: longUnit,
            price: '1234.50',
            containersPerUnit: '1234567.000',
        }),
        makeProduct('product-3', longName, { active: false }),
    ],
};
const stockByState: Record<string, ReturnType<typeof makeStock>[]> = {
    default: [
        makeStock('stock-1', 'Mel de farigola'),
        makeStock('stock-2', 'Mel de bruc', { active: false }),
        makeStock('stock-3', 'Mel de castanyer', { available: '0.000' }),
    ],
    empty: [],
    'long-name': [
        makeStock('stock-1', longName, {
            unit: longUnit,
            price: '1234.50',
            available: '1234567.000',
        }),
        makeStock('stock-2', longName, { active: false }),
    ],
};
const productsState = state.replace(
    /^(?:busy|error|toggle|variant-off|nav-.*)$/,
    'checkpoint',
);
// Stock rows are shown in every populated state; `no-stock` and `empty` have none.
const listKey = state === 'no-stock' ? 'default' : productsState;
const productRows = (productsByState[listKey] ?? productsByState.default).map(
    (row) => ({ ...row }),
);
const stockRows =
    state === 'no-stock' ? [] : (stockByState[listKey] ?? stockByState.default);
// Cycle summary (spec 005 T13): sent with lines, sent with nothing to deliver, not sent (mixed
// availability), no products, long name and unit. The toggle changes `available` like the real action.
const cycleLine = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    productId: id,
    name,
    unit: 'kg',
    available: true,
    quantity: null as string | null,
    ...over,
});
const cycleProducts: Record<string, ReturnType<typeof cycleLine>[]> = {
    default: [
        cycleLine('p-1', 'Tomàquet de pera'),
        cycleLine('p-2', 'Pastanaga', { available: false }),
    ],
    sent: [
        cycleLine('p-1', 'Tomàquet de pera', { quantity: '12.500' }),
        cycleLine('p-2', 'Pastanaga', { quantity: '1234.000' }),
        cycleLine('p-3', 'Enciam', { unit: 'unitat', quantity: null }),
    ],
    'sent-empty': [],
    'no-products': [],
    'long-name': [
        cycleLine('p-1', longName, { unit: longUnit }),
        cycleLine('p-2', longName, { available: false, unit: longUnit }),
    ],
    'sent-long': [
        cycleLine('p-1', longName, {
            unit: longUnit,
            quantity: '1234567.500',
        }),
    ],
};
const cycleKey = state === 'cycle-toggle' ? 'default' : state;
const cycleSent = state.startsWith('sent');
const cycleSummary = {
    cycleId: 'cycle-1',
    pickupDate: '2026-11-14',
    sent: cycleSent,
    products: (cycleProducts[cycleKey] ?? cycleProducts.default).map((row) => ({
        ...row,
    })),
};
const movementLine = (
    id: string,
    type: string,
    quantity: string,
    balanceAfter = '12.000',
    over: Partial<{ note: string; authorName: string }> = {},
) => ({
    id,
    occurredAt: '2026-11-10 09:30',
    type,
    quantity,
    balanceAfter,
    note: null as string | null,
    authorName: 'Maria Soler',
    cycleId: 'cycle-1',
    pickupDate: '2026-11-14',
    basketId: null as string | null,
    ...over,
});
// Producer stock movement history (spec 005 T14): populated, empty, long content and paged.
const movementPage = (
    lines: ReturnType<typeof movementLine>[],
    paging: {
        total?: number;
        page?: number;
        prevUrl?: string;
        nextUrl?: string;
    } = {},
) => ({
    lines,
    total: paging.total ?? lines.length,
    page: paging.page ?? 1,
    prevUrl: paging.prevUrl ?? null,
    nextUrl: paging.nextUrl ?? null,
});
const movementHistoriesByState: Record<
    string,
    ReturnType<typeof movementPage>
> = {
    default: movementPage([
        movementLine('line-1', 'sale', '-3.000'),
        movementLine('line-2', 'entry', '10.500'),
    ]),
    empty: movementPage([]),
    'long-content': movementPage([
        movementLine('line-1', 'adjustment', '-1234567.000', '1234567.500', {
            note: `Ajust per inventari complet del magatzem de ${longName}`,
            authorName: longName,
        }),
        movementLine('line-2', 'entry', '1234567.000', '2469134.500'),
    ]),
    paged: movementPage([movementLine('line-1', 'sale', '-3.000')], {
        total: 60,
        page: 2,
        prevUrl: '/producer/stock/stock-1/movements?page=1',
        nextUrl: '/producer/stock/stock-1/movements?page=3',
    }),
};
const movementHistory =
    movementHistoriesByState[state] ?? movementHistoriesByState.default;
const pageProps: Record<string, Record<string, unknown>> = {
    'Producer/Products/Index': {
        products: productRows,
        stockProducts: stockRows,
    },
    'Producer/Products/Create': {},
    'Producer/Products/Edit': { product: productRows[0], used: false },
    'Producer/Cutoff': { cutoff: { daysBefore: 3, time: '20:00' } },
    'Producer/CycleSummary': { summary: cycleSummary },
    'Producer/Stock/Movements': {
        product: {
            name: state === 'long-content' ? longName : 'Mel de farigola',
            unit: 'pot',
            available: state === 'long-content' ? '1234567.500' : '12.000',
        },
        history: movementHistory,
    },
    // Unmigrated control page (settings/Profile stays old-theme until C4).
    'settings/Profile': { verificationLinkSent: false },
    Welcome: {},
};
const areas = [
    { role: 'producer', label: 'Producer', href: '/producer/products' },
    { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
];
const urls: Record<string, string> = {
    'Producer/Products/Index': '/producer/products',
    'Producer/Products/Create': '/producer/products/create',
    'Producer/Products/Edit': '/producer/products/product-1/edit',
    'Producer/Cutoff': '/producer/cutoff',
    'Producer/CycleSummary': '/producer/cycles/cycle-1',
    'Producer/Stock/Movements': '/producer/stock/stock-1/movements',
    'settings/Profile': '/settings/profile',
    Welcome: '/',
};
const page = {
    component: pageName,
    url: urls[pageName] ?? '/producer/products',
    version: 'producer-landing',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user:
                pageName === 'Welcome'
                    ? null
                    : {
                          id: 'account-1',
                          name: 'Maria Soler',
                          email: 'maria@example.test',
                          avatar: '',
                          email_verified_at: '2026-10-01T12:00:00Z',
                          created_at: '2026-10-01T12:00:00Z',
                          updated_at: '2026-10-01T12:00:00Z',
                      },
            roles: pageName === 'Welcome' ? [] : roles,
        },
        roleAreas:
            pageName === 'Welcome'
                ? []
                : areas.filter((area) => roles.includes(area.role)),
        sidebarOpen: true,
        ...pageProps[pageName],
    },
};

// Request spy: every request the real router sends, so the switch is proven to send what it always sent.
const requestLog: string[] = [];
const switchRequests: string[] = [];
// Create/Edit forms and delete-unused (spec 005 T11): requests the real router sends for those pages.
const formRequests: string[] = [];
const formStates = ['form-invalid', 'form-busy', 'delete-busy', 'delete-error'];
// Default cutoff form (spec 005 T12): the PUT the real router sends for Producer/Cutoff.
const cutoffRequests: string[] = [];
const cutoffStates = ['cutoff-invalid', 'cutoff-busy'];
// Cycle summary availability toggle (spec 005 T13): the PUT the real router sends for a not-sent cycle.
const availabilityRequests: string[] = [];
const pagesByPath: Record<string, string> = {
    '/settings/profile': 'settings/Profile',
    '/producer/products': 'Producer/Products/Index',
};
http.setClient({
    request: async (config: { url: string; method?: string }) => {
        const url = new URL(config.url, window.location.origin);
        const method = (config.method ?? 'get').toLowerCase();
        requestLog.push(`${method} ${url.pathname}`);
        const reply = (body: Record<string, unknown>) => ({
            status: 200,
            data: JSON.stringify(body),
            headers: { 'x-inertia': 'true' },
        });
        if (
            cutoffStates.includes(state) &&
            method !== 'get' &&
            url.pathname === '/producer/cutoff'
        ) {
            cutoffRequests.push(`${method} ${url.pathname}${url.search}`);
            // Busy: the request never answers, so the page stays in `processing`.
            if (state === 'cutoff-busy') return new Promise(() => {});
            return reply({
                ...page,
                props: {
                    ...page.props,
                    errors: {
                        days_before: 'Els dies han de ser entre 1 i 6.',
                        domain: 'L’hora de tancament no és vàlida.',
                    },
                },
            });
        }
        if (
            state === 'cycle-toggle' &&
            method !== 'get' &&
            url.pathname.endsWith('/availability')
        ) {
            availabilityRequests.push(`${method} ${url.pathname}${url.search}`);
            const row = cycleSummary.products.find((line) =>
                url.pathname.includes(`/products/${line.productId}/`),
            );
            if (row) row.available = !row.available;
            return reply({
                ...page,
                props: { ...page.props, summary: cycleSummary },
            });
        }
        const isFormRequest =
            method !== 'get' &&
            (url.pathname === '/producer/products' ||
                url.pathname.endsWith('/unused'));
        if (formStates.includes(state) && isFormRequest) {
            formRequests.push(`${method} ${url.pathname}${url.search}`);
            // Busy: the request never answers, so the page stays in `processing`.
            if (state.endsWith('-busy')) return new Promise(() => {});
            return reply({
                ...page,
                props: {
                    ...page.props,
                    errors:
                        state === 'delete-error'
                            ? { domain: 'No s’ha pogut eliminar el producte.' }
                            : {
                                  name: 'Ja existeix un producte amb aquest nom.',
                              },
                },
            });
        }
        if (
            url.pathname.startsWith('/producer/products/') &&
            method !== 'get'
        ) {
            switchRequests.push(`${method} ${url.pathname}${url.search}`);
            // Busy: the request never answers, so the page stays in `processing`.
            if (state === 'busy') return new Promise(() => {});
            if (state === 'error')
                return reply({
                    ...page,
                    props: {
                        ...page.props,
                        errors: {
                            domain: 'No s’ha pogut canviar el producte.',
                        },
                    },
                });
            const product = productRows.find((row) =>
                url.pathname.includes(row.id),
            );
            if (product)
                product.active = url.pathname.endsWith('/reactivation');
            return reply({
                ...page,
                props: { ...page.props, products: productRows },
            });
        }
        const component = pagesByPath[url.pathname] ?? pageName;
        return reply({
            ...page,
            component,
            url: url.pathname,
            props: { ...page.props, ...pageProps[component] },
        });
    },
});

// Pre-slice fixture: the same route with the pilot theme but without the variant.
const RevertedLayout = defineComponent({
    inheritAttrs: false,
    setup:
        (_, { attrs, slots }) =>
        () =>
            h(
                AppSidebarLayout,
                { ...attrs, pilotTheme: true, managerTheme: false },
                slots,
            ),
});

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

const sleep = (ms: number) =>
    new Promise((resolve) => window.setTimeout(resolve, ms));
const waitFor = async <T>(find: () => T | null | undefined): Promise<T> => {
    for (let attempt = 0; attempt < 120; attempt++) {
        const found = find();
        if (found) return found;
        await sleep(25);
    }
    throw new Error(`Visual target missing in state ${state}`);
};
const visible = (element: HTMLElement) => element.getClientRects().length > 0;
const rgbOf = (element: Element, value: string) => {
    const probe = document.createElement('span');
    probe.style.color = value;
    element.append(probe);
    const color = getComputedStyle(probe).color;
    probe.remove();
    return color;
};
const measured: Record<string, string> = {
    shell: '.reference-style',
    // The mobile sidebar is the SheetContent itself (data-slot is overridden by Sidebar.vue).
    sheet: '[data-sidebar="sidebar"][data-mobile="true"]',
    menu: '[data-slot="dropdown-menu-content"]',
};
// Colour transitions on the shell and sheet must finish before a value is recorded.
const settle = async () => {
    await sleep(50);
    for (let attempt = 0; attempt < 40; attempt++) {
        const running = document
            .getAnimations()
            .filter((animation) => animation.playState === 'running');
        if (running.length === 0) return;
        await Promise.allSettled(
            running.map((animation) => animation.finished),
        );
        await sleep(25);
    }
};
const snapshot = async (label: string) => {
    await settle();
    for (const [name, selector] of Object.entries(measured)) {
        const element = document.querySelector(selector);
        if (!element) continue;
        document.body.setAttribute(
            `data-${label}-${name}-primary`,
            rgbOf(element, 'var(--primary)'),
        );
        document.body.setAttribute(
            `data-${label}-${name}-bg`,
            getComputedStyle(element).backgroundColor,
        );
    }
};
const heading = () => document.querySelector('#main-content h1')?.textContent;
const visit = async (path: string, title: string) => {
    router.visit(path);
    await waitFor(() => heading() === title);
    await sleep(150);
};
const openSheet = async () => {
    if (document.querySelector(measured.sheet)) return;
    (
        await waitFor(() =>
            document.querySelector<HTMLElement>(
                'button[data-sidebar="trigger"]',
            ),
        )
    ).click();
    await waitFor(() => document.querySelector(measured.sheet));
    await sleep(600);
};
const openMenu = async () => {
    if (document.querySelector(measured.menu)) return;
    const trigger = await waitFor(() =>
        [
            ...document.querySelectorAll<HTMLElement>(
                '[data-test="sidebar-menu-button"]',
            ),
        ].find(visible),
    );
    trigger.dispatchEvent(
        new KeyboardEvent('keydown', {
            key: 'ArrowDown',
            code: 'ArrowDown',
            bubbles: true,
            cancelable: true,
        }),
    );
    await waitFor(() => document.querySelector(measured.menu));
    await sleep(300);
};

// Non-text contrast (3:1) is not measured by the runner's `contrast` rule, which covers text only.
// The fixture resolves each colour through a canvas, composites it over the opaque background behind
// the element and records whether the 3:1 floor holds.
type Rgba = [number, number, number, number];
const canvasContext = document
    .createElement('canvas')
    .getContext('2d', { willReadFrequently: true })!;
const toRgba = (css: string): Rgba => {
    canvasContext.clearRect(0, 0, 1, 1);
    canvasContext.fillStyle = '#000';
    canvasContext.fillStyle = css;
    canvasContext.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = canvasContext.getImageData(0, 0, 1, 1).data;
    return [r, g, b, a / 255];
};
const over = (top: Rgba, bottom: Rgba): Rgba => {
    const alpha = top[3] + bottom[3] * (1 - top[3]);
    const mix = (index: number) =>
        (top[index] * top[3] + bottom[index] * bottom[3] * (1 - top[3])) /
        alpha;
    return [mix(0), mix(1), mix(2), alpha];
};
// The element's own fill is not what its border or focus ring sits against.
const backdropOf = (element: Element): Rgba => {
    let backdrop: Rgba = [255, 255, 255, 1];
    const chain: Element[] = [];
    for (
        let node: Element | null = element.parentElement;
        node;
        node = node.parentElement
    )
        chain.unshift(node);
    for (const node of chain) {
        const layer = toRgba(getComputedStyle(node).backgroundColor);
        if (layer[3] > 0) backdrop = over(layer, backdrop);
    }
    return backdrop;
};
const luminance = ([r, g, b]: Rgba) => {
    const [lr, lg, lb] = [r, g, b].map((channel) => {
        const unit = channel / 255;
        return unit <= 0.03928 ? unit / 12.92 : ((unit + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * lr + 0.7152 * lg + 0.0722 * lb;
};
const ratio = (first: Rgba, second: Rgba) => {
    const [high, low] = [luminance(first), luminance(second)].sort(
        (a, b) => b - a,
    );
    return (high + 0.05) / (low + 0.05);
};
const against = (colour: string, node: HTMLElement) =>
    ratio(over(toRgba(colour), backdropOf(node)), backdropOf(node));
// A scripted focus() does not match :focus-visible in an unfocused headless page, so the keyboard
// outline is resolved from the stylesheet: the last unlayered :focus-visible rule that applies to the
// node and sets a solid outline of at least 2px.
const focusOutlineColour = (node: HTMLElement): string | null => {
    let colour: string | null = null;
    const walk = (rules: CSSRuleList, layered: boolean) => {
        for (const rule of Array.from(rules)) {
            if (rule instanceof CSSStyleRule && !layered) {
                const applies = rule.selectorText
                    .split(',')
                    .map((part) => part.trim())
                    .filter((part) => part.includes(':focus-visible'))
                    .some((part) => {
                        const base = part.replaceAll(':focus-visible', '');
                        const target = /[\s>+~]$/.test(base)
                            ? `${base}*`
                            : base || '*';
                        try {
                            return node.matches(target);
                        } catch {
                            return false;
                        }
                    });
                const match = rule.style
                    .getPropertyValue('outline')
                    .match(/^(\d+(?:\.\d+)?)px solid (.+)$/);
                if (applies && match && parseFloat(match[1]) >= 2)
                    colour = rgbOf(node, match[2]);
            }
            if ('cssRules' in rule)
                walk(
                    rule.cssRules as CSSRuleList,
                    layered || rule instanceof CSSLayerBlockRule,
                );
        }
    };
    for (const sheet of Array.from(document.styleSheets)) {
        try {
            walk(sheet.cssRules, false);
        } catch {
            // Cross-origin sheets cannot be read; the fixture has none.
        }
    }
    return colour;
};
// Per control class of the product list: node count and weakest 3:1 ratio. A class with no nodes
// records "false", so an empty list can never pass.
const recordControlContrast = () => {
    const nodes = (selector: string) => [
        ...document.querySelectorAll<HTMLElement>(`#main-content ${selector}`),
    ];
    const report = (name: string, ratios: number[]) => {
        const weakest = ratios.length ? Math.min(...ratios) : 0;
        const set = (key: string, value: string) =>
            document.body.setAttribute(`data-ev-${name}-${key}`, value);
        set('count', String(ratios.length));
        set('ratio', weakest.toFixed(2));
        set('ok', String(ratios.length > 0 && weakest >= 3));
    };
    const outlined = nodes('[data-slot="button"]').filter(
        (button) => parseFloat(getComputedStyle(button).borderTopWidth) > 0,
    );
    report(
        'border',
        outlined.map((button) =>
            against(getComputedStyle(button).borderTopColor, button),
        ),
    );
    const toggles = nodes('input[type="checkbox"]');
    report(
        'switch',
        toggles.map((toggle) =>
            against(getComputedStyle(toggle).borderTopColor, toggle),
        ),
    );
    // The thumb is the ::before of the checkbox; it sits on the toggle's own fill.
    report(
        'thumb',
        toggles.map((toggle) => {
            const track = over(
                toRgba(getComputedStyle(toggle).backgroundColor),
                backdropOf(toggle),
            );
            const thumb = toRgba(
                getComputedStyle(toggle, '::before').backgroundColor,
            );
            return ratio(over(thumb, track), track);
        }),
    );
    const focusable = nodes('a, button, input[type="checkbox"]').filter(
        visible,
    );
    report(
        'focus',
        focusable.flatMap((node) => {
            const colour = focusOutlineColour(node);
            return colour ? [against(colour, node)] : [];
        }),
    );
    document.body.setAttribute(
        'data-ev-focus-targets',
        String(focusable.length),
    );
};

// Non-text contrast of the Create/Edit form controls. A text field's border is measured against the
// page backdrop and against its own fill (`dark:bg-input/30`), keeping the weaker ratio.
const recordFormContrast = () => {
    const nodes = (selector: string) => [
        ...document.querySelectorAll<HTMLElement>(`#main-content ${selector}`),
    ];
    const report = (name: string, ratios: number[]) => {
        const weakest = ratios.length ? Math.min(...ratios) : 0;
        const set = (key: string, value: string) =>
            document.body.setAttribute(`data-ev-${name}-${key}`, value);
        set('count', String(ratios.length));
        set('ratio', weakest.toFixed(2));
        set('ok', String(ratios.length > 0 && weakest >= 3));
    };
    const edge = (node: HTMLElement) => {
        const border = over(
            toRgba(getComputedStyle(node).borderTopColor),
            backdropOf(node),
        );
        const fill = over(
            toRgba(getComputedStyle(node).backgroundColor),
            backdropOf(node),
        );
        return Math.min(ratio(border, backdropOf(node)), ratio(border, fill));
    };
    report(
        'field',
        nodes('input:not([type="checkbox"]):not([type="hidden"])').map(edge),
    );
    report('checkbox', nodes('input[type="checkbox"]').map(edge));
    const outlined = nodes('[data-slot="button"]').filter(
        (button) => parseFloat(getComputedStyle(button).borderTopWidth) > 0,
    );
    report(
        'border',
        outlined.map((button) =>
            against(getComputedStyle(button).borderTopColor, button),
        ),
    );
    const focusable = nodes('a, button, input').filter(visible);
    report(
        'focus',
        focusable.flatMap((node) => {
            const colour = focusOutlineColour(node);
            return colour ? [against(colour, node)] : [];
        }),
    );
    document.body.setAttribute(
        'data-ev-focus-targets',
        String(focusable.length),
    );
};

// The switch is a real Form: clicking it must send exactly what ProductController defines.
const requestFor = (id: string, active: boolean) => {
    const form = (
        active
            ? ProductController.destroy.form(id)
            : ProductController.reactivate.form(id)
    ) as { action: string; method: string };
    const target = new URL(form.action, window.location.origin);
    return `${form.method} ${target.pathname}${target.search}`;
};
const switchLabel = (id: string) =>
    document
        .querySelector(`[data-test$="-${id}"]`)
        ?.parentElement?.querySelector('span')
        ?.textContent?.trim() ?? 'none';
const clickSwitch = async (kind: string, id: string, settledLabel: string) => {
    const input = await waitFor(() =>
        document.querySelector<HTMLElement>(`[data-test="${kind}-${id}"]`),
    );
    input.focus();
    input.click();
    await waitFor(() =>
        switchLabel(id) === settledLabel ? document.body : null,
    );
    await sleep(150);
};
// Create/Edit helpers. A scripted click on a focused button stands in for Enter/Space activation,
// which the browser turns into the same click; the dialog's native Escape is stood in by close().
const formRequestsText = () => formRequests.join(' ; ');
const setEvidence = (key: string, value: string) =>
    document.body.setAttribute(`data-ev-${key}`, value);
const byTest = (name: string) =>
    waitFor(() => document.querySelector<HTMLElement>(`[data-test="${name}"]`));
const openDialog = async () => {
    const opener = await byTest('delete-unused-product');
    opener.focus();
    opener.click();
    await waitFor(() => document.querySelector('dialog[open]'));
    await sleep(200);
    return opener;
};
const fillAndSubmitCreate = async () => {
    const values: Record<string, string> = {
        name: 'Pa de pagès',
        unit: 'unitat',
        price: '3.50',
    };
    for (const [id, value] of Object.entries(values))
        (
            await waitFor(() =>
                document.querySelector<HTMLInputElement>(`#${id}`),
            )
        ).value = value;
    (await byTest('save-product')).click();
};
const expectedStore = () => {
    const form = ProductController.store.form() as {
        action: string;
        method: string;
    };
    const target = new URL(form.action, window.location.origin);
    return `${form.method.toLowerCase()} ${target.pathname}${target.search}`;
};
const expectedDelete = () => {
    const target = new URL(
        ProductController.deleteUnused.url('product-1'),
        window.location.origin,
    );
    return `delete ${target.pathname}${target.search}`;
};
const runFormState = async () => {
    if (state === 'form-invalid' || state === 'form-busy') {
        await fillAndSubmitCreate();
        await waitFor(() => (formRequests.length ? document.body : null));
        if (state === 'form-invalid')
            await waitFor(() =>
                document.querySelector('#name ~ div .text-destructive'),
            );
        await sleep(400);
        setEvidence('requests', formRequestsText());
        setEvidence('expected', expectedStore());
        setEvidence('match', String(formRequestsText() === expectedStore()));
    }
    if (state === 'dialog') await openDialog();
    if (state === 'dialog-close') {
        const opener = await openDialog();
        const dialog = document.querySelector<HTMLDialogElement>('dialog')!;
        dialog.close();
        await sleep(200);
        setEvidence('escape-open', String(dialog.open));
        setEvidence('escape-focus', String(document.activeElement === opener));
        await openDialog();
        (await byTest('cancel-product-action')).click();
        await sleep(200);
        setEvidence('cancel-open', String(dialog.open));
        setEvidence('cancel-focus', String(document.activeElement === opener));
        setEvidence('requests', formRequestsText() || 'none');
    }
    if (state === 'delete-busy' || state === 'delete-error') {
        await openDialog();
        (await byTest('confirm-product-action')).click();
        await waitFor(() => (formRequests.length ? document.body : null));
        if (state === 'delete-error')
            await waitFor(() => document.querySelector('[role="alert"] p'));
        await sleep(400);
        setEvidence('requests', formRequestsText());
        setEvidence('expected', expectedDelete());
        setEvidence('match', String(formRequestsText() === expectedDelete()));
    }
};

const expectedCutoffUpdate = () => {
    const form = CutoffController.update.form() as {
        action: string;
        method: string;
    };
    const target = new URL(form.action, window.location.origin);
    return `${form.method.toLowerCase()} ${target.pathname}${target.search}`;
};
const runCutoffState = async () => {
    (await byTest('save-cutoff')).click();
    await waitFor(() => (cutoffRequests.length ? document.body : null));
    if (state === 'cutoff-invalid')
        await waitFor(() =>
            document.querySelector('#time ~ div .text-destructive'),
        );
    await sleep(400);
    const requests = cutoffRequests.join(' ; ');
    setEvidence('requests', requests);
    setEvidence('expected', expectedCutoffUpdate());
    setEvidence('match', String(requests === expectedCutoffUpdate()));
};

const expectedAvailability = (product: string) => {
    const form = ProductAvailabilityController.update.form({
        cycle: 'cycle-1',
        product,
    }) as { action: string; method: string };
    const target = new URL(form.action, window.location.origin);
    return `${form.method.toLowerCase()} ${target.pathname}${target.search}`;
};
const runCycleToggle = async () => {
    // Product p-1 is available: its button offers "Marca com a no disponible" and submits `available=0`.
    const label = () =>
        document
            .querySelector(
                '[data-test="product-p-1"] [data-test="availability"]',
            )
            ?.textContent?.trim() ?? 'none';
    setEvidence('before', label());
    setEvidence(
        'sent-value',
        document.querySelector<HTMLInputElement>(
            '[data-test="product-p-1"] input[name="available"]',
        )!.value,
    );
    const button = await byTest('toggle-p-1');
    button.focus();
    button.click();
    await waitFor(() => (label() === 'No disponible' ? document.body : null));
    await sleep(150);
    setEvidence('after', label());
    setEvidence(
        'button-after',
        (await byTest('toggle-p-1')).textContent!.trim(),
    );
    const requests = availabilityRequests.join(' ; ');
    setEvidence('requests', requests);
    setEvidence('expected', expectedAvailability('p-1'));
    setEvidence('match', String(requests === expectedAvailability('p-1')));
};

const runToggle = async () => {
    // Product 3 is inactive first: reactivate it, then deactivate product 1, as a person would.
    document.body.setAttribute('data-ev-before', switchLabel('product-3'));
    await clickSwitch('reactivate', 'product-3', 'Actiu');
    document.body.setAttribute(
        'data-ev-after-reactivate',
        switchLabel('product-3'),
    );
    await clickSwitch('deactivate', 'product-1', 'Desactivat');
    document.body.setAttribute(
        'data-ev-after-deactivate',
        switchLabel('product-1'),
    );
    document.body.setAttribute('data-ev-requests', switchRequests.join(' ; '));
    const expected = [
        requestFor('product-3', false),
        requestFor('product-1', true),
    ].join(' ; ');
    document.body.setAttribute('data-ev-expected', expected);
    document.body.setAttribute(
        'data-ev-match',
        String(document.body.getAttribute('data-ev-requests') === expected),
    );
};

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: (name) => {
        if (name === 'Welcome') return null;
        return state === 'variant-off' ? RevertedLayout : AppLayout;
    },
    withApp: (app, { page }) => {
        installTranslations(app, page.props.locale);
    },
    progress: false,
}).then(async () => {
    if (state === 'nav-sheet' || state === 'nav-menu') {
        // S17 first states: Producer products (landing) -> Profile settings (unmigrated) -> products again.
        const open = state === 'nav-sheet' ? openSheet : openMenu;
        await open();
        await snapshot('s1');
        await visit('/settings/profile', 'Configuració del perfil');
        await open();
        await snapshot('s2');
        await visit('/producer/products', 'Productes');
        await open();
        await snapshot('s3');
        document.body.dataset.navRequests = requestLog.join(',');
    }
    if (state === 'toggle') await runToggle();
    if (state === 'busy' || state === 'error') {
        // First active product: its switch is clicked and the request never answers or fails.
        const input = await waitFor(() =>
            document.querySelector<HTMLElement>(
                '[data-test="deactivate-product-1"]',
            ),
        );
        input.click();
        await waitFor(() => (switchRequests.length ? document.body : null));
        await sleep(400);
        document.body.setAttribute(
            'data-ev-requests',
            switchRequests.join(' ; '),
        );
        document.body.setAttribute(
            'data-ev-expected',
            requestFor('product-1', true),
        );
    }
    if (
        pageName.startsWith('Producer/Products/') &&
        pageName !== 'Producer/Products/Index'
    )
        await runFormState();
    if (pageName === 'Producer/Cutoff' && cutoffStates.includes(state))
        await runCutoffState();
    if (pageName === 'Producer/CycleSummary' && state === 'cycle-toggle')
        await runCycleToggle();
    if (state === 'account-menu') await openMenu();
    if (query.get('drawer') === '1') await openSheet();
    await snapshot('now');
    if (
        pageName === 'Producer/Products/Index' &&
        !document.querySelector(measured.sheet) &&
        !document.querySelector(measured.menu)
    )
        recordControlContrast();
    if (
        ((pageName.startsWith('Producer/Products/') &&
            pageName !== 'Producer/Products/Index') ||
            pageName === 'Producer/Cutoff' ||
            pageName === 'Producer/CycleSummary') &&
        !document.querySelector(measured.sheet) &&
        !document.querySelector(measured.menu)
    )
        recordFormContrast();
    document.body.dataset.requests = String(requestLog.length);
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

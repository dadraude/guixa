import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import { defineComponent, h, type DefineComponent } from 'vue';
import ProducerAvailabilityController from '@/actions/App/Http/Controllers/Catalogue/ProducerAvailabilityController';
import AppLayout from '@/layouts/AppLayout.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';
import { initializeFlashToast } from '@/lib/flashToast';

/*
 * Deterministic review fixture for the Catalogue pages in the landing identity (spec 005). Only the
 * HTTP client is mocked: the real Inertia router, layouts, pages and portals run. It proves
 * presentation, never persistence or authorization. The role query parameter only picks the shell
 * navigation (catalogue, or manager for the Manager variant and the Manager-only control page).
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Catalogue/Producers/Index';
const state = query.get('state') ?? 'default';
const role = query.get('role') ?? 'catalogue';
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
const makeProducer = (id: string, name: string, active = true) => ({
    id,
    name,
    active,
    cutoffDaysBefore: 3,
    cutoffTime: '20:00',
    accountEmail: null,
    accountInvitationPending: false,
});
const producersByState: Record<string, ReturnType<typeof makeProducer>[]> = {
    default: [
        makeProducer('producer-1', 'Hort de la Vall'),
        makeProducer('producer-2', 'Formatges del Montseny'),
        makeProducer('producer-3', 'Forn de Sant Joan', false),
    ],
    // The deactivated producer comes first: the first checkpoint measures this state.
    deactivated: [
        makeProducer('producer-3', 'Forn de Sant Joan', false),
        makeProducer('producer-1', 'Hort de la Vall'),
    ],
    empty: [],
    'long-name': [
        makeProducer('producer-1', longName),
        makeProducer('producer-2', longName, false),
        makeProducer('producer-3', 'Hort de la Vall'),
    ],
};
const makeProduct = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    id,
    producerId: 'producer-1',
    name,
    unit: 'unitat',
    price: '3.50',
    variable: false,
    containersPerUnit: null,
    active: true,
    stock: false,
    used: false,
    can: { edit: true, changeClassification: true, delete: false },
    available: null as string | null,
    ...over,
});
const readOnly = {
    can: { edit: false, changeClassification: false, delete: false },
};
const productsByState: Record<string, ReturnType<typeof makeProduct>[]> = {
    default: [
        makeProduct('product-1', 'Pa de pagès', { price: '3.50' }),
        makeProduct('product-2', 'Mel de farigola', {
            stock: true,
            unit: 'pot',
            price: '8.90',
            available: '12.000',
        }),
        makeProduct('product-3', 'Brioix de l’àvia', {
            active: false,
            price: '2.25',
        }),
        makeProduct('product-4', 'Coca de recapte', { used: true }),
    ],
    // The inactive product comes first: the first checkpoint measures this state.
    checkpoint: [
        makeProduct('product-3', 'Brioix de l’àvia', {
            active: false,
            price: '2.25',
        }),
        makeProduct('product-2', 'Mel de farigola', {
            stock: true,
            unit: 'pot',
            price: '8.90',
            available: '12.000',
        }),
    ],
    empty: [],
    'long-name': [
        makeProduct('product-1', longName, {
            stock: true,
            unit: 'pot',
            price: '1234.50',
            available: '1234567.000',
        }),
        makeProduct('product-2', longName, { active: false }),
    ],
    'read-only': [
        makeProduct('product-2', 'Mel de farigola', {
            ...readOnly,
            stock: true,
            unit: 'pot',
            price: '8.90',
            available: '12.000',
        }),
        makeProduct('product-1', 'Pa de pagès', readOnly),
    ],
};
// Availability (spec 005 T04). Cycle A can still be changed, cycle B is already sent (canChange false).
const makeCycle = (
    id: string,
    pickupDate: string,
    canChange: boolean,
    products: { productId: string; name: string; available: boolean }[],
) => ({
    cycleId: id,
    pickupDate,
    canChange,
    products: products.map((product) => ({ unit: 'unitat', ...product })),
});
const toggleable = [
    { productId: 'p-1', name: 'Tomàquet de pera', available: true },
    { productId: 'p-2', name: 'Bròquil', available: false },
    { productId: 'p-3', name: 'Pebrots del piquillo', available: true },
];
const sentCycle = makeCycle('cycle-2', '2026-11-21', false, [
    { productId: 'p-1', name: 'Tomàquet de pera', available: true },
    { productId: 'p-2', name: 'Bròquil', available: false },
]);
const openCycle = (products = toggleable) =>
    makeCycle('cycle-1', '2026-11-14', true, products);
const longProducts = [
    { productId: 'p-1', name: longName, available: true },
    { productId: 'p-2', name: longName, available: false },
];
const availabilityByState: Record<
    string,
    { producerActive: boolean; producerName: string; cycles: unknown[] }
> = {
    default: {
        producerActive: true,
        producerName: 'Hort de la Vall',
        cycles: [openCycle(), sentCycle],
    },
    // The deactivated producer: danger indicator and notice are text, not only colour.
    deactivated: {
        producerActive: false,
        producerName: 'Forn de Sant Joan',
        cycles: [openCycle(), sentCycle],
    },
    // Unavailable product first: the first checkpoint measures this state.
    checkpoint: {
        producerActive: true,
        producerName: 'Hort de la Vall',
        cycles: [openCycle([toggleable[1], toggleable[0]])],
    },
    'read-only': {
        producerActive: true,
        producerName: 'Hort de la Vall',
        cycles: [sentCycle],
    },
    empty: {
        producerActive: true,
        producerName: 'Hort de la Vall',
        cycles: [],
    },
    'no-products': {
        producerActive: true,
        producerName: 'Hort de la Vall',
        cycles: [openCycle([]), sentCycle],
    },
    'long-name': {
        producerActive: false,
        producerName: longName,
        cycles: [
            openCycle(longProducts),
            makeCycle('cycle-2', '2026-11-21', false, longProducts),
        ],
    },
};
const availabilityState = state.replace(
    /^(?:variant-off|migration-off|nav-.*|toggle)$/,
    'default',
);
const availability =
    availabilityByState[availabilityState] ?? availabilityByState.default;
// Error and busy states reuse the checkpoint products; navigation and reversion states the default ones.
const productsState =
    { error: 'checkpoint', busy: 'checkpoint' }[state] ??
    state.replace(/^(?:variant-off|nav-.*)$/, 'default');
const productRows = (
    productsByState[productsState] ?? productsByState.default
).map((product) => ({ ...product }));
// --- T05 (DRA-277): the product the weekly edit form is prefilled with; long-name stresses the title and the Name input.
const weeklyProduct = {
    id: 'product-1',
    name: state === 'long-name' ? longName : 'Pa de pagès',
    unit: 'unitat',
    price: '3.50',
    variable: false,
    containersPerUnit: null,
    active: true,
};
// --- end T05
// --- T06 (DRA-278): the stock product the stock edit form is prefilled with. `used` locks unit and variable.
const stockProduct = {
    id: 'stock-1',
    producerId: 'producer-1',
    name: state === 'long-name' ? longName : 'Mel de farigola',
    unit: 'pot',
    price: '6.50',
    variable: state === 'used',
    containersPerUnit: '2.000',
    active: true,
};
// --- end T06
// --- T07 (DRA-275): return items. The inactive item comes first in `checkpoint`; `long-name` stresses wrapping. ---
const makeReturnItem = (
    id: string,
    name: string,
    amount: string,
    active = true,
) => ({ id, name, amount, active });
const returnItemsByState: Record<string, ReturnType<typeof makeReturnItem>[]> =
    {
        default: [
            makeReturnItem('item-1', 'Pot de vidre 500 ml', '0.25'),
            makeReturnItem('item-2', 'Ampolla de vidre 1 L', '0.30', false),
            makeReturnItem('item-3', 'Caixa de plàstic', '12.50'),
        ],
        checkpoint: [
            makeReturnItem('item-2', 'Ampolla de vidre 1 L', '0.30', false),
            makeReturnItem('item-1', 'Pot de vidre 500 ml', '0.25'),
        ],
        empty: [],
        'long-name': [
            makeReturnItem('item-1', longName, '1234.50'),
            makeReturnItem('item-2', longName, '0.30', false),
        ],
    };
const returnItem = makeReturnItem(
    'item-1',
    state === 'long-name' ? longName : 'Pot de vidre 500 ml',
    '0.25',
);
// Probe for the shared ActiveSwitch consumer left unmigrated: Accounts with an active and a deactivated account.
const accountsForSwitchProbe = [
    {
        accountId: 'account-a',
        name: 'Anna Puig',
        email: 'anna@example.test',
        roles: ['member'],
        active: true,
        invitationPending: false,
        householdName: 'Cal Puig',
    },
    {
        accountId: 'account-b',
        name: 'Biel Roca',
        email: 'biel@example.test',
        roles: ['member'],
        active: false,
        invitationPending: false,
        householdName: null,
    },
];
// --- end T07
const pageProps: Record<string, Record<string, unknown>> = {
    'Catalogue/Producers/Index': {
        producers: producersByState[state] ?? producersByState.default,
    },
    'Catalogue/Producers/Products': {
        producer: { id: 'producer-1', name: 'Hort de la Vall' },
        products: productRows,
        canCreateStock: state !== 'read-only',
    },
    'Catalogue/Producers/Availability': {
        producer: {
            id: 'producer-1',
            name: availability.producerName,
            active: availability.producerActive,
        },
        cycles: availability.cycles,
    },
    // --- T05 (DRA-277): weekly product forms ---
    'Catalogue/Producers/WeeklyProduct/Create': {
        producer: { id: 'producer-1', name: 'Hort de la Vall' },
    },
    'Catalogue/Producers/WeeklyProduct/Edit': {
        producer: { id: 'producer-1', name: 'Hort de la Vall' },
        product: weeklyProduct,
        used: state === 'used',
        canDelete: state !== 'used' && state !== 'read-only',
    },
    // --- end T05 ---
    // --- T06 (DRA-278): stock product forms and the unmigrated Movements neighbour ---
    'Manage/Stock/Create': {
        producer: { id: 'producer-1', name: 'Hort de la Vall' },
    },
    'Manage/Stock/Edit': {
        product: stockProduct,
        used: state === 'used',
    },
    'Manage/Stock/Movements': {
        product: { ...stockProduct, available: '12.000' },
        cycles: [],
        history: { lines: [], total: 0, page: 1, prevUrl: null, nextUrl: null },
    },
    // --- end T06 ---
    // Unmigrated consumers of the shared dialog, for the leakage probe.
    // --- T05 (DRA-277): shared-consumer probe, WeeklyProductFields consumer left unmigrated
    'Producer/Products/Create': {},
    // --- end T05
    'Producer/Products/Edit': {
        product: {
            id: 'product-1',
            name: 'Pa de pagès',
            unit: 'unitat',
            price: '3.50',
            variable: false,
            containersPerUnit: null,
            active: true,
        },
        used: false,
    },
    'Manage/Cycles/Index': {
        cycles: [],
        producers: [],
        pickupWeekday: null,
        pendingCollections: {},
    },
    'Manage/Accounts/Index': {
        accounts: state === 'accounts-switch' ? accountsForSwitchProbe : [],
        households: [],
    },
    // --- T07 (DRA-275)
    'Manage/ReturnItems/Index': {
        items: returnItemsByState[state] ?? returnItemsByState.default,
    },
    'Manage/ReturnItems/Create': {},
    'Manage/ReturnItems/Edit': { item: returnItem },
    // --- end T07
    Welcome: {},
};
const areas = [
    { role: 'producer', label: 'Producer', href: '/producer/products' },
    { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
    { role: 'catalogue', label: 'Catalogue', href: '/catalogue/producers' },
];
const urls: Record<string, string> = {
    'Catalogue/Producers/Index': '/catalogue/producers',
    'Catalogue/Producers/Products': '/catalogue/producers/producer-1/products',
    'Catalogue/Producers/Availability':
        '/catalogue/producers/producer-1/availability',
    // --- T05 (DRA-277)
    'Catalogue/Producers/WeeklyProduct/Create':
        '/catalogue/producers/producer-1/products/weekly/create',
    'Catalogue/Producers/WeeklyProduct/Edit':
        '/catalogue/producers/producer-1/products/weekly/product-1/edit',
    'Producer/Products/Create': '/producer/products/create',
    // --- end T05
    // --- T06 (DRA-278)
    'Manage/Stock/Create':
        '/catalogue/producers/producer-1/products/stock/create',
    'Manage/Stock/Edit':
        '/catalogue/producers/producer-1/products/stock/stock-1/edit',
    'Manage/Stock/Movements': '/manage/stock/stock-1/movements',
    // --- end T06
    // --- T07 (DRA-275)
    'Manage/ReturnItems/Index': '/catalogue/return-items',
    'Manage/ReturnItems/Create': '/catalogue/return-items/create',
    'Manage/ReturnItems/Edit': '/catalogue/return-items/item-1/edit',
    // --- end T07
    'Producer/Products/Edit': '/producer/products/product-1/edit',
    'Manage/Cycles/Index': '/manage/cycles',
    'Manage/Accounts/Index': '/manage/accounts',
    Welcome: '/',
};
const page = {
    component: pageName,
    url: urls[pageName] ?? '/catalogue/producers',
    version: 'catalogue-landing',
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
            roles: pageName === 'Welcome' ? [] : [role],
        },
        roleAreas:
            pageName === 'Welcome'
                ? []
                : areas.filter((area) => area.role === role),
        sidebarOpen: query.get('tooltip') !== '1',
        ...pageProps[pageName],
    },
};

// Request spy: navigation states count every request the real router sends.
const requestLog: string[] = [];
const availabilityRequests: string[] = [];
// --- T05 (DRA-277)
const weeklyRequests: string[] = [];
// --- end T05
// --- T06 (DRA-278)
const stockRequests: string[] = [];
// --- end T06
// --- T07 (DRA-275)
const returnItemRequests: string[] = [];
// --- end T07
const pagesByPath: Record<string, string> = {
    '/catalogue/producers/producer-1/availability':
        'Catalogue/Producers/Availability',
    '/catalogue/producers/producer-1/products': 'Catalogue/Producers/Products',
    '/catalogue/producers': 'Catalogue/Producers/Index',
    '/manage/accounts': 'Manage/Accounts/Index',
};
const flashMessage = 'Classificació del producte actualitzada.';
http.setClient({
    request: async (config: {
        url: string;
        method?: string;
        data?: unknown;
    }) => {
        const url = new URL(config.url, window.location.origin);
        const path = url.pathname;
        const method = (config.method ?? 'get').toLowerCase();
        requestLog.push(`${method} ${path}`);
        const reply = (body: Record<string, unknown>) => ({
            status: 200,
            data: JSON.stringify(body),
            headers: { 'x-inertia': 'true' },
        });
        // Availability toggle: the Form of the page posts the wayfinder action; the product flips.
        if (path.endsWith('/availability') && method !== 'get') {
            const body =
                config.data instanceof FormData
                    ? Object.fromEntries(config.data.entries())
                    : ((config.data ?? {}) as Record<string, unknown>);
            availabilityRequests.push(
                `${method} ${path}${url.search} available=${String(body.available)}`,
            );
            const productId = path.split('/').at(-2);
            for (const cycle of availability.cycles as ReturnType<
                typeof makeCycle
            >[])
                for (const product of cycle.products)
                    if (
                        path.includes(`/cycles/${cycle.cycleId}/`) &&
                        product.productId === productId
                    )
                        product.available = String(body.available) === '1';
            return reply({
                ...page,
                props: { ...page.props, cycles: availability.cycles },
            });
        }
        // --- T05 (DRA-277): the weekly forms post to /products/weekly. busy never answers; invalid and server-error
        // answer with the errors the real controller would return, through the real router.
        if (path.includes('/products/weekly') && method !== 'get') {
            weeklyRequests.push(`${method} ${path}`);
            if (state === 'busy') return new Promise(() => {});
            const errors =
                {
                    invalid: {
                        name: 'Ja existeix un producte setmanal amb aquest nom.',
                    },
                    'invalid-long': {
                        name: `Ja existeix un producte setmanal amb aquest nom: ${longName}.`,
                        price: 'El preu ha de ser un import positiu.',
                    },
                    'server-error': {
                        domain: 'No s’ha pogut desar el producte.',
                    },
                }[state] ?? {};
            return reply({ ...page, props: { ...page.props, errors } });
        }
        // --- end T05
        // --- T06 (DRA-278): the stock forms post to /products/stock, same contract as the weekly ones.
        if (path.includes('/products/stock') && method !== 'get') {
            stockRequests.push(`${method} ${path}`);
            if (state === 'busy') return new Promise(() => {});
            const errors =
                {
                    invalid: {
                        name: 'Ja existeix un producte d’estoc amb aquest nom.',
                    },
                    'invalid-all': {
                        name: `Ja existeix un producte d’estoc amb aquest nom: ${longName}.`,
                        unit: 'La unitat és obligatòria.',
                        price: 'El preu ha de ser un import positiu.',
                        containers_per_unit:
                            'Els envasos per unitat han de ser un número positiu.',
                    },
                    'server-error': {
                        domain: 'No s’ha pogut desar el producte.',
                    },
                }[state] ?? {};
            return reply({ ...page, props: { ...page.props, errors } });
        }
        // --- end T06
        // --- T07 (DRA-275): return item forms and the switch post to /return-items; busy never answers.
        if (path.includes('/return-items') && method !== 'get') {
            returnItemRequests.push(`${method} ${path}`);
            if (state === 'busy') return new Promise(() => {});
            const errors =
                {
                    invalid: {
                        name: 'Ja existeix un article retornable amb aquest nom.',
                    },
                    'invalid-all': {
                        name: `Ja existeix un article retornable amb aquest nom: ${longName}.`,
                        amount: 'L’import ha de ser positiu.',
                    },
                    'server-error': {
                        domain: 'No s’ha pogut desar l’article retornable.',
                    },
                }[state] ?? {};
            return reply({ ...page, props: { ...page.props, errors } });
        }
        // --- end T07
        // Busy state: the request never answers, so the page stays in `processing`.
        if (method === 'put' && state === 'busy') return new Promise(() => {});
        if (method === 'put' && state === 'error')
            return reply({
                ...page,
                props: {
                    ...page.props,
                    errors: {
                        domain: 'No s’ha pogut canviar la classificació.',
                    },
                },
            });
        if (method === 'put') {
            // Classification saved: the real router applies the page and fires the flash event.
            const product = productRows.find((row) => path.includes(row.id));
            if (product) product.stock = !product.stock;
            return reply({
                ...page,
                props: { ...page.props, products: productRows },
                flash: { toast: { type: 'success', message: flashMessage } },
            });
        }
        const component = pagesByPath[path] ?? 'Catalogue/Producers/Index';
        return reply({
            ...page,
            component,
            url: path,
            props: { ...page.props, ...pageProps[component] },
        });
    },
});
initializeFlashToast();

// Reversion fixture: the same route with the pilot theme but without the variant.
const RevertedLayout = defineComponent({
    inheritAttrs: false,
    setup:
        (_, { attrs, slots }) =>
        () =>
            h(
                AppSidebarLayout,
                { ...attrs, pilotTheme: true, managerTheme: true },
                slots,
            ),
});

// Pre-slice fixture for Availability: it was in neither list, so no pilot theme and no variant.
const UnmigratedLayout = defineComponent({
    inheritAttrs: false,
    setup:
        (_, { attrs, slots }) =>
        () =>
            h(
                AppSidebarLayout,
                { ...attrs, pilotTheme: false, managerTheme: false },
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
// Own look of the page in view: heading ink and font, and the first panel's surface and border.
const snapshotPage = async (label: string) => {
    await snapshot(label);
    const title = document.querySelector('#main-content h1:not(.sr-only)');
    if (title) {
        document.body.setAttribute(
            `data-${label}-h1-ink`,
            getComputedStyle(title).color,
        );
        document.body.setAttribute(
            `data-${label}-h1-font`,
            getComputedStyle(title).fontFamily.split(',')[0],
        );
    }
    const panel = document.querySelector(
        '#main-content .pilot-layout-panel, #main-content .pilot-list',
    );
    if (panel) {
        document.body.setAttribute(
            `data-${label}-panel-bg`,
            getComputedStyle(panel).backgroundColor,
        );
        document.body.setAttribute(
            `data-${label}-panel-border`,
            getComputedStyle(panel).borderTopColor,
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
const describeActive = () => {
    const active = document.activeElement as HTMLElement | null;
    return (
        active?.getAttribute('aria-label') ??
        active?.textContent?.trim() ??
        'none'
    );
};

// Non-text contrast (3:1) is not measured by the runner's `contrast` rule, which covers text only.
// The fixture resolves each colour through a canvas (any CSS colour syntax), composites it over the
// opaque background behind the element, and records whether the 3:1 floor holds.
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
const backdropOf = (element: Element): Rgba => {
    let backdrop: Rgba = [255, 255, 255, 1];
    const chain: Element[] = [];
    // The element's own fill is not what its border, dot edge or focus ring sits against.
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
// The runner presses Tab only after its assertions, and a scripted focus() does not match
// :focus-visible in an unfocused headless page. The keyboard focus outline is therefore resolved
// from the stylesheet: the last unlayered :focus-visible rule that applies to the link and sets a
// solid outline of at least 2px. An unlayered rule beats the layered Button utilities, so this is
// the outline the cascade gives a keyboard-focused link. The colour is resolved on the link itself.
const focusOutlineColour = (link: HTMLElement): string | null => {
    let colour: string | null = null;
    const visit = (rules: CSSRuleList, layered: boolean) => {
        for (const rule of Array.from(rules)) {
            const nestedLayered = layered || rule instanceof CSSLayerBlockRule;
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
                            return link.matches(target);
                        } catch {
                            // A selector the engine cannot evaluate here does not apply to the link.
                            return false;
                        }
                    });
                const outline = rule.style.getPropertyValue('outline');
                const match = outline.match(/^(\d+(?:\.\d+)?)px solid (.+)$/);
                if (applies && match && parseFloat(match[1]) >= 2)
                    colour = rgbOf(link, match[2]);
            }
            if ('cssRules' in rule)
                visit(rule.cssRules as CSSRuleList, nestedLayered);
        }
    };
    for (const sheet of Array.from(document.styleSheets)) {
        try {
            visit(sheet.cssRules, false);
        } catch {
            // Cross-origin sheets cannot be read; the fixture has none.
        }
    }
    return colour;
};
// Records, per graphic class of the producer list, the weakest 3:1 ratio against the backdrop
// behind it (outline-button border, status dot, keyboard focus outline). A class with no nodes
// records "false": an empty list can never pass.
const recordGraphicContrast = () => {
    const nodes = (selector: string) => [
        ...document.querySelectorAll<HTMLElement>(selector),
    ];
    const report = (name: string, ratios: number[]) => {
        const weakest = ratios.length ? Math.min(...ratios) : 0;
        document.body.setAttribute(
            `data-ev-${name}-count`,
            String(ratios.length),
        );
        document.body.setAttribute(`data-ev-${name}-ratio`, weakest.toFixed(2));
        document.body.setAttribute(
            `data-ev-${name}-ok`,
            String(ratios.length > 0 && weakest >= 3),
        );
    };
    const against = (colour: string, node: HTMLElement) =>
        ratio(over(toRgba(colour), backdropOf(node)), backdropOf(node));
    const links = nodes('#main-content .pilot-list a');
    report(
        'border',
        links.map((link) =>
            against(getComputedStyle(link).borderTopColor, link),
        ),
    );
    report(
        'dot',
        nodes('#main-content .pilot-list .material-status-dot').map((dot) =>
            against(getComputedStyle(dot).backgroundColor, dot),
        ),
    );
    report(
        'focus',
        links.flatMap((link) => {
            const colour = focusOutlineColour(link);
            return colour ? [against(colour, link)] : [];
        }),
    );
};

// Overlay probes for the products page (spec 005 T03). The classify dialog is the NativeDialog of
// ProductAdministrationActions: it renders inside the shell DOM and is promoted to the top layer.
const dialogParam = query.get('dialog');
// Each product renders its own closed dialog; the open one is the one under measurement.
const dialogElement = () =>
    document.querySelector<HTMLDialogElement>('dialog.manager-dialog[open]') ??
    document.querySelector<HTMLDialogElement>('dialog.manager-dialog');
const dataTest = (element: Element | null) =>
    element?.getAttribute('data-test') ??
    element?.tagName.toLowerCase() ??
    'none';
// A focused button is activated by Enter or Space as a click, which is what the opener receives.
const openFrom = async (selector: string, label: string) => {
    const opener = await waitFor(() =>
        document.querySelector<HTMLElement>(selector),
    );
    opener.focus();
    opener.click();
    await waitFor(() => (dialogElement()?.open ? dialogElement() : null));
    await sleep(350);
    document.body.setAttribute('data-ev-opener', label);
};
const recordDialog = () => {
    const dialog = dialogElement();
    if (!dialog) return;
    const style = getComputedStyle(dialog);
    const buttons = [...dialog.querySelectorAll('button')].map((button) => {
        const own = getComputedStyle(button);
        return [
            own.backgroundColor,
            own.color,
            own.borderTopColor,
            own.borderTopWidth,
        ].join(' | ');
    });
    const set = (name: string, value: string) =>
        document.body.setAttribute(`data-now-dialog-${name}`, value);
    set('modal', String(dialog.open && dialog.matches(':modal')));
    set('bg', style.backgroundColor);
    set('ink', style.color);
    set('border', style.borderTopColor);
    set('buttons', buttons.join(' ; '));
    const confirm = dialog.querySelector(
        '[data-test="confirm-product-action"]',
    );
    if (confirm) {
        set('confirm-bg', getComputedStyle(confirm).backgroundColor);
        set('confirm-ink', getComputedStyle(confirm).color);
    }
};
// Non-text contrast (3:1) and keyboard focus outline for the controls of the products page and of
// its dialog. Each class records its node count; a class with no nodes can never pass.
const recordControlContrast = (scope: string, prefix: string) => {
    const nodes = (selector: string) => [
        ...document.querySelectorAll<HTMLElement>(
            selector
                .split(',')
                .map((part) => `${scope} ${part.trim()}`)
                .join(','),
        ),
    ];
    const report = (name: string, ratios: number[]) => {
        const weakest = ratios.length ? Math.min(...ratios) : 0;
        document.body.setAttribute(
            `data-ev-${prefix}-${name}-count`,
            String(ratios.length),
        );
        document.body.setAttribute(
            `data-ev-${prefix}-${name}-ratio`,
            weakest.toFixed(2),
        );
        document.body.setAttribute(
            `data-ev-${prefix}-${name}-ok`,
            String(ratios.length > 0 && weakest >= 3),
        );
    };
    const against = (colour: string, node: HTMLElement) =>
        ratio(over(toRgba(colour), backdropOf(node)), backdropOf(node));
    const outlined = nodes('[data-slot="button"]').filter(
        (button) => parseFloat(getComputedStyle(button).borderTopWidth) > 0,
    );
    report(
        'border',
        outlined.map((button) =>
            against(getComputedStyle(button).borderTopColor, button),
        ),
    );
    // The status dot of a StatusIndicator (deactivated producer on Availability).
    report(
        'dot',
        nodes('.material-status-dot').map((dot) =>
            against(getComputedStyle(dot).backgroundColor, dot),
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
            return ratio(
                over(
                    toRgba(
                        getComputedStyle(toggle, '::before').backgroundColor,
                    ),
                    track,
                ),
                track,
            );
        }),
    );
    const focusable = nodes('a, button, input[type="checkbox"]').filter(
        (node) => visible(node),
    );
    report(
        'focus',
        focusable.flatMap((node) => {
            const colour = focusOutlineColour(node);
            return colour ? [against(colour, node)] : [];
        }),
    );
    document.body.setAttribute(
        `data-ev-${prefix}-focus-targets`,
        String(focusable.length),
    );
};

// --- T05 (DRA-277): weekly product forms ---------------------------------------------------------
const weeklyForms = [
    'Catalogue/Producers/WeeklyProduct/Create',
    'Catalogue/Producers/WeeklyProduct/Edit',
];
// Fills the Name, Unit and Price inputs the way a person would (events included), then submits.
const submitWeeklyForm = async () => {
    const form = await waitFor(() =>
        document.querySelector<HTMLFormElement>('#main-content form'),
    );
    const type = (name: string, value: string) => {
        const input = form.querySelector<HTMLInputElement>(
            `input[name="${name}"]`,
        )!;
        input.focus();
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    type(
        'name',
        pageName.endsWith('/Edit') ? weeklyProduct.name : 'Pa de pagès',
    );
    type('unit', 'unitat');
    type('price', '3.50');
    form.requestSubmit();
    const outcome =
        state === 'busy'
            ? '[data-test="save-product"][disabled]'
            : '#main-content form [class*="text-destructive"]:not(:empty)';
    await waitFor(() => document.querySelector(outcome));
    await sleep(300);
};
// R5: the border of every text field against the page behind it and against its own fill (3:1), and
// the error text against the page. In dark the field is `dark:bg-input/30` over the page, so the fill
// is composited like the browser does. A class with no nodes records "false".
const recordFieldContrast = () => {
    const fields = [
        ...document.querySelectorAll<HTMLInputElement>(
            '#main-content form input:not([type="hidden"]):not([type="checkbox"])',
        ),
    ];
    const against = (colour: string, backdrop: Rgba) =>
        ratio(over(toRgba(colour), backdrop), backdrop);
    const ratios = fields.flatMap((field) => {
        const style = getComputedStyle(field);
        const page = backdropOf(field);
        const fill = over(toRgba(style.backgroundColor), page);
        return [
            against(style.borderTopColor, page),
            against(style.borderTopColor, fill),
        ];
    });
    const weakest = ratios.length ? Math.min(...ratios) : 0;
    const set = (name: string, value: string) =>
        document.body.setAttribute(`data-ev-field-${name}`, value);
    set('count', String(fields.length));
    set('border-ratio', weakest.toFixed(2));
    set('border-ok', String(ratios.length > 0 && weakest >= 3));
    const first = fields[0];
    if (first) {
        const style = getComputedStyle(first);
        set('first-border', style.borderTopColor);
        set('first-border-width', style.borderTopWidth);
        set('first-bg', style.backgroundColor);
        set('first-height', String(first.getBoundingClientRect().height));
    }
};
// --- end T05 ---------------------------------------------------------------------------------------

// --- T06 (DRA-278): stock product forms. Same field contrast probe as the weekly forms (R5). ---------
const stockForms = ['Manage/Stock/Create', 'Manage/Stock/Edit'];
const submitStockForm = async () => {
    const form = await waitFor(() =>
        document.querySelector<HTMLFormElement>('#main-content form'),
    );
    const type = (name: string, value: string) => {
        const input = form.querySelector<HTMLInputElement>(
            `input[name="${name}"]`,
        )!;
        input.focus();
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    type('name', 'Mel de farigola');
    type('unit', 'pot');
    type('price', '6.50');
    form.requestSubmit();
    const outcome =
        state === 'busy'
            ? '[data-test="save-stock-product"][disabled]'
            : '#main-content form [class*="text-destructive"]:not(:empty)';
    await waitFor(() => document.querySelector(outcome));
    await sleep(300);
};
// --- end T06 -----------------------------------------------------------------------------------------

// --- T07 (DRA-275): return item forms and list switches ------------------------------------------------
const returnItemForms = [
    'Manage/ReturnItems/Create',
    'Manage/ReturnItems/Edit',
];
const submitReturnItemForm = async () => {
    const form = await waitFor(() =>
        document.querySelector<HTMLFormElement>('#main-content form'),
    );
    const type = (name: string, value: string) => {
        const input = form.querySelector<HTMLInputElement>(
            `input[name="${name}"]`,
        )!;
        input.focus();
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    type('name', 'Pot de vidre 500 ml');
    type('amount', '0.25');
    form.requestSubmit();
    const outcome =
        state === 'busy'
            ? '[data-test="save-return-item"][disabled]'
            : '#main-content form [class*="text-destructive"]:not(:empty)';
    await waitFor(() => document.querySelector(outcome));
    await sleep(300);
};
// Switch geometry as the browser lays it out: the input, and the whole label that is its hit area.
const recordSwitchGeometry = (scope: string) => {
    const labels = [
        ...document.querySelectorAll<HTMLElement>(
            `${scope} .pilot-status-switch`,
        ),
    ];
    const boxes = labels.map((label) => label.getBoundingClientRect());
    const inputs = labels.map((label) =>
        label.querySelector('input')!.getBoundingClientRect(),
    );
    const set = (name: string, value: string) =>
        document.body.setAttribute(`data-ev-switch-${name}`, value);
    set('count', String(labels.length));
    set('hit-min-height', String(Math.min(...boxes.map((b) => b.height))));
    set('hit-min-width', String(Math.min(...boxes.map((b) => b.width))));
    set('input-width', String(Math.min(...inputs.map((b) => b.width))));
    set('input-height', String(Math.min(...inputs.map((b) => b.height))));
    set(
        'label-colours',
        labels
            .map(
                (label) => getComputedStyle(label.querySelector('span')!).color,
            )
            .join(' ; '),
    );
    set(
        'label-contrast',
        labels
            .map((label) => {
                const span = label.querySelector('span')!;
                return ratio(
                    over(
                        toRgba(getComputedStyle(span).color),
                        backdropOf(span),
                    ),
                    backdropOf(span),
                ).toFixed(2);
            })
            .join(' ; '),
    );
};
const recordAmountFigures = () => {
    const cells = [
        ...document.querySelectorAll<HTMLElement>(
            '#main-content .pilot-data-table td:nth-child(3)',
        ),
    ];
    document.body.setAttribute(
        'data-ev-amount-numeric',
        cells
            .map((cell) => getComputedStyle(cell).fontVariantNumeric)
            .join(' ; '),
    );
    document.body.setAttribute(
        'data-ev-amount-align',
        cells.map((cell) => getComputedStyle(cell).textAlign).join(' ; '),
    );
};
// --- end T07 -----------------------------------------------------------------------------------------

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: (name) => {
        if (name === 'Welcome') return null;
        if (state === 'variant-off') return RevertedLayout;
        return state === 'migration-off' ? UnmigratedLayout : AppLayout;
    },
    withApp: (app, { page }) => {
        installTranslations(app, page.props.locale);
    },
    progress: false,
}).then(async () => {
    if (state === 'nav-sheet' || state === 'nav-menu') {
        // S8 first states: Producers (landing) -> Accounts (unmigrated) -> Producers again.
        const open = state === 'nav-sheet' ? openSheet : openMenu;
        await open();
        await snapshot('s1');
        await visit('/manage/accounts', 'Comptes');
        await open();
        await snapshot('s2');
        await visit('/catalogue/producers', 'Productors');
        await open();
        await snapshot('s3');
        document.body.dataset.navRequests = requestLog.join(',');
    }
    if (state === 'nav-neighbours') {
        // R2: the neighbours of Availability keep their own look. Availability (landing) -> Producers
        // (landing, previous page) -> Products (landing, next page) -> Accounts (unmigrated) -> Availability.
        await snapshotPage('s1');
        await visit('/catalogue/producers', 'Productors');
        await snapshotPage('s2');
        await visit(
            '/catalogue/producers/producer-1/products',
            'Productes de Hort de la Vall',
        );
        await snapshotPage('s3');
        await visit('/manage/accounts', 'Comptes');
        await snapshotPage('s4');
        await visit(
            '/catalogue/producers/producer-1/availability',
            'Disponibilitat de Hort de la Vall',
        );
        await snapshotPage('s5');
        document.body.dataset.navRequests = requestLog.join(',');
    }
    if (state === 'toggle') {
        // S5: marking a product unavailable and available again through the real router.
        const row = (id: string) =>
            `[data-test="product-${id}"] [data-test="availability"]`;
        const text = (selector: string) =>
            document.querySelector(selector)?.textContent?.trim() ?? 'none';
        const toggle = await waitFor(() =>
            document.querySelector<HTMLElement>('[data-test="toggle-p-1"]'),
        );
        document.body.setAttribute('data-ev-toggle-before', text(row('p-1')));
        toggle.focus();
        toggle.click();
        await waitFor(() =>
            text(row('p-1')) === 'No disponible' ? document.body : null,
        );
        await sleep(150);
        document.body.setAttribute('data-ev-toggle-after', text(row('p-1')));
        document.body.setAttribute(
            'data-ev-toggle-label',
            document
                .querySelector('[data-test="toggle-p-1"]')
                ?.textContent?.trim() ?? 'none',
        );
        document
            .querySelector<HTMLElement>('[data-test="toggle-p-1"]')
            ?.click();
        await waitFor(() =>
            text(row('p-1')) === 'Disponible' ? document.body : null,
        );
        await sleep(150);
        document.body.setAttribute('data-ev-toggle-back', text(row('p-1')));
        document.body.setAttribute(
            'data-ev-toggle-requests',
            availabilityRequests.join(' ; '),
        );
        // The same request as before: the unchanged Form posts what the wayfinder action defines.
        const expected = ProducerAvailabilityController.update.form({
            producer: 'producer-1',
            cycle: 'cycle-1',
            product: 'p-1',
        });
        const target = new URL(expected.action, window.location.origin);
        document.body.setAttribute(
            'data-ev-toggle-expected',
            [0, 1]
                .map(
                    (value) =>
                        `${expected.method} ${target.pathname}${target.search} available=${value}`,
                )
                .join(' ; '),
        );
        document.body.setAttribute(
            'data-ev-toggle-match',
            String(
                document.body.getAttribute('data-ev-toggle-requests') ===
                    document.body.getAttribute('data-ev-toggle-expected'),
            ),
        );
        document.body.setAttribute(
            'data-ev-toggle-focus',
            dataTest(document.activeElement),
        );
    }
    if (state === 'account-menu') await openMenu();
    if (query.get('tooltip') === '1') {
        // Collapsed icon sidebar: the pointer resting on a menu button opens its tooltip.
        const button = await waitFor(() =>
            [
                ...document.querySelectorAll<HTMLElement>(
                    '[data-sidebar="menu-button"]:not([data-test="sidebar-menu-button"])',
                ),
            ].find(visible),
        );
        button.dispatchEvent(
            new PointerEvent('pointermove', {
                pointerType: 'mouse',
                bubbles: true,
            }),
        );
        await waitFor(() =>
            document.querySelector('[data-slot="tooltip-content"]'),
        );
        await sleep(400);
    }
    if (query.get('drawer') === '1') await openSheet();
    if (
        dialogParam === 'classify' ||
        dialogParam === 'escape' ||
        dialogParam === 'confirm'
    )
        await openFrom(
            '[data-test="change-product-classification"]',
            'change-product-classification',
        );
    if (dialogParam === 'create')
        await openFrom('[data-test="open-create-cycle"]', 'open-create-cycle');
    if (dialogParam === 'delete')
        await openFrom(
            '[data-test="delete-unused-product"]',
            'delete-unused-product',
        );
    // --- T05: the delete dialog of WeeklyProduct/Edit closed by Escape (requestClose) or Cancel ---
    if (dialogParam === 'delete-escape' || dialogParam === 'delete-cancel') {
        await openFrom(
            '[data-test="delete-unused-product"]',
            'delete-unused-product',
        );
        recordDialog();
        if (dialogParam === 'delete-escape') dialogElement()?.requestClose();
        else
            dialogElement()
                ?.querySelector<HTMLElement>(
                    '[data-test="cancel-product-action"]',
                )
                ?.click();
        await waitFor(() => (dialogElement()?.open ? null : document.body));
        await sleep(150);
        document.body.setAttribute(
            'data-ev-t05-close-requests',
            String(requestLog.length),
        );
        document.body.setAttribute(
            'data-ev-t05-close-focus',
            dataTest(document.activeElement),
        );
    }
    // --- end T05 ---
    if (dialogParam === 'escape') {
        // requestClose() is the close request Escape sends: cancel, then close, then the focus return.
        dialogElement()?.requestClose();
        await waitFor(() => (dialogElement()?.open ? null : document.body));
        await sleep(150);
        document.body.setAttribute(
            'data-ev-escape-requests',
            String(requestLog.length),
        );
        document.body.setAttribute(
            'data-ev-escape-open',
            String(dialogElement()?.open ?? false),
        );
        document.body.setAttribute(
            'data-ev-escape-focus',
            dataTest(document.activeElement),
        );
    }
    if (dialogParam === 'confirm') {
        dialogElement()
            ?.querySelector<HTMLElement>('[data-test="confirm-product-action"]')
            ?.click();
        const outcome =
            {
                error: '#main-content div[role="alert"] p',
                busy: '[data-test="change-product-classification"][disabled]',
            }[state] ?? '[data-sonner-toast]';
        await waitFor(() => document.querySelector(outcome));
        await sleep(600);
        document.body.setAttribute(
            'data-ev-confirm-requests',
            requestLog.join(','),
        );
        document.body.setAttribute(
            'data-ev-confirm-open',
            String(dialogElement()?.open ?? false),
        );
        document.body.setAttribute(
            'data-ev-confirm-focus',
            dataTest(document.activeElement),
        );
    }
    // --- T05 ---
    if (
        weeklyForms.includes(pageName) &&
        ['invalid', 'invalid-long', 'server-error', 'busy'].includes(state)
    )
        await submitWeeklyForm();
    if (weeklyForms.includes(pageName) && !dialogParam) recordFieldContrast();
    if (weeklyForms.includes(pageName))
        document.body.setAttribute(
            'data-ev-weekly-requests',
            weeklyRequests.join(' ; '),
        );
    // --- end T05 ---
    // --- T06 ---
    if (
        stockForms.includes(pageName) &&
        ['invalid', 'invalid-all', 'server-error', 'busy'].includes(state)
    )
        await submitStockForm();
    if (stockForms.includes(pageName) && !dialogParam) recordFieldContrast();
    if (stockForms.includes(pageName))
        document.body.setAttribute(
            'data-ev-stock-requests',
            stockRequests.join(' ; '),
        );
    // --- end T06 ---
    // --- T07 ---
    if (
        returnItemForms.includes(pageName) &&
        ['invalid', 'invalid-all', 'server-error', 'busy'].includes(state)
    )
        await submitReturnItemForm();
    if (returnItemForms.includes(pageName)) {
        recordFieldContrast();
        document.body.setAttribute(
            'data-ev-return-requests',
            returnItemRequests.join(' ; '),
        );
    }
    if (
        pageName === 'Manage/ReturnItems/Index' ||
        (pageName === 'Manage/Accounts/Index' && state === 'accounts-switch')
    ) {
        recordSwitchGeometry('#main-content');
        recordControlContrast('#main-content', 'page');
    }
    if (pageName === 'Manage/ReturnItems/Index') recordAmountFigures();
    // --- end T07 ---
    recordDialog();
    await snapshot('now');
    if (
        pageName === 'Catalogue/Producers/Index' &&
        !document.querySelector(measured.sheet) &&
        !document.querySelector(measured.menu)
    )
        recordGraphicContrast();
    if (pageName === 'Catalogue/Producers/Products' && !dialogParam)
        recordControlContrast('#main-content .pilot-page', 'page');
    if (
        pageName === 'Catalogue/Producers/Availability' &&
        !document.querySelector(measured.sheet) &&
        !document.querySelector(measured.menu)
    )
        recordControlContrast('#main-content .pilot-page', 'page');
    if (dialogElement()?.open) recordControlContrast('dialog[open]', 'dialog');
    document.body.dataset.requests = String(requestLog.length);
    document.body.dataset.active = describeActive();
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

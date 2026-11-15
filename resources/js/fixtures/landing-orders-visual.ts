import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

/*
 * Deterministic review fixture for T85 (landing identity on member orders). Only the data is
 * fixed: the real Inertia router, AppLayout, pages and portals run. It proves presentation, never
 * persistence or authorization. Data mirrors dra-205-visual.ts (cycle-1, Hort de la Vall, Tomàquets).
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Orders/Edit';
const state = query.get('state') ?? 'default';
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

const cycleId = 'cycle-1';
const pickupDate = '2026-11-05';
const longProducer =
    'Cooperativa agrícola de la Vall de les Hortes i del Ganxet tradicional';
const longProduct =
    'Mongetes seques de la varietat tradicional del Ganxet cultivades en terrasses amb reg de pluja';
const makeProduct = (id: string, name: string, price = '3.20') => ({
    id,
    name,
    unit: 'kg',
    price,
    variable: true,
    containersPerUnit: '1',
    active: true,
});
const makeProducer = (
    id: string,
    name: string,
    open: boolean,
    products: ReturnType<typeof makeProduct>[],
) => ({ producerId: id, producerName: name, open, products });
const makeLine = (
    producerId: string,
    producerName: string,
    product: ReturnType<typeof makeProduct>,
    notDelivered = false,
) => ({
    productId: product.id,
    producerId,
    producerName,
    name: product.name,
    unit: product.unit,
    price: product.price,
    variable: product.variable,
    quantity: '2',
    notDelivered,
});

const tomatoes = makeProduct('product-1', 'Tomàquets');
const baseProducers = [
    makeProducer('producer-1', 'Hort de la Vall', true, [tomatoes]),
];
const baseLines = [makeLine('producer-1', 'Hort de la Vall', tomatoes)];

// G: twelve rows over four producers, one closed (with a marked line) and one empty.
const manyProducers = () => {
    const rows = (producerId: string, producerName: string, count: number) =>
        Array.from({ length: count }, (_, index) =>
            makeProduct(
                `${producerId}-p${index}`,
                `Producte ${producerId}-${index}`,
            ),
        ).map((product) => ({ producerName, producerId, product }));
    const groups = [
        { id: 'many-a', name: 'Hort de la Vall', open: true, count: 5 },
        { id: 'many-b', name: 'Forn de la plaça', open: false, count: 4 },
        { id: 'many-c', name: 'Cooperativa del Pla', open: true, count: 3 },
        {
            id: 'many-d',
            name: 'Productor sense productes',
            open: true,
            count: 0,
        },
    ];
    const producers = groups.map((group) =>
        makeProducer(
            group.id,
            group.name,
            group.open,
            rows(group.id, group.name, group.count).map((row) => row.product),
        ),
    );
    const lines = groups.flatMap((group) =>
        rows(group.id, group.name, group.count).map((row, index) =>
            makeLine(
                row.producerId,
                row.producerName,
                row.product,
                group.id === 'many-b' && index === 0,
            ),
        ),
    );
    return { producers, lines };
};

let orderProducers = baseProducers;
let orderLines = baseLines;
let cancelled = false;
let basketId: string | null = null;
let preview: { totalCents: number; amountCents: Record<string, number> } = {
    totalCents: 0,
    amountCents: {},
};
let errors: Record<string, string> = {};
let cycles = [
    { cycleId, pickupDate, open: true, cancelled: false, lineCount: 1 },
];

if (state === 'empty') {
    orderProducers = [];
    orderLines = [];
    cycles = [];
}
if (state === 'cancelled') cancelled = true;
if (state === 'basket') basketId = 'basket-1';
if (state === 'preview')
    preview = { totalCents: 640, amountCents: { 'product-1': 640 } };
if (state === 'domain-error')
    errors = { domain: 'Aquest productor ja ha tancat les comandes.' };
if (state === 'long') {
    const product = makeProduct('product-1', longProduct);
    orderProducers = [
        makeProducer('producer-1', longProducer, true, [product]),
    ];
    orderLines = [makeLine('producer-1', longProducer, product)];
    cycles = [
        { cycleId, pickupDate, open: true, cancelled: false, lineCount: 1234 },
    ];
}
if (state === 'marked') {
    // A: a marked line under a closed producer; also an open producer with a line and an empty one.
    const open = makeProduct('product-2', 'Albergínies');
    orderProducers = [
        makeProducer('producer-1', 'Hort de la Vall', false, [tomatoes]),
        makeProducer('producer-2', 'Cooperativa del Pla', true, [open]),
        makeProducer('producer-3', 'Productor sense productes', true, []),
    ];
    orderLines = [
        makeLine('producer-1', 'Hort de la Vall', tomatoes, true),
        makeLine('producer-2', 'Cooperativa del Pla', open),
    ];
}
if (state === 'many') {
    const many = manyProducers();
    orderProducers = many.producers;
    orderLines = many.lines;
}
if (state === 'index-multiple' || state === 'index-closed-cancelled') {
    cycles = [
        {
            cycleId: 'cycle-1',
            pickupDate,
            open: true,
            cancelled: false,
            lineCount: 1,
        },
        {
            cycleId: 'cycle-2',
            pickupDate: '2026-11-12',
            open: false,
            cancelled: false,
            lineCount: 3,
        },
        {
            cycleId: 'cycle-3',
            pickupDate: '2026-11-19',
            open: false,
            cancelled: true,
            lineCount: 0,
        },
    ];
}
if (state === 'index-closed-cancelled') cycles = cycles.slice(1);

const order = {
    cycleId,
    pickupDate,
    cancelled,
    producers: orderProducers,
    lines: orderLines,
    basketId,
};
const pageProps: Record<string, Record<string, unknown>> = {
    'Orders/Edit': { order, preview },
    'Orders/Index': { cycles },
    'Catalogue/Producers/Index': { producers: [] },
};
const urls: Record<string, string> = {
    'Catalogue/Producers/Index': '/catalogue/producers',
    'Orders/Edit': `/cycles/${cycleId}/order`,
    'Orders/Index': '/',
};
const page = {
    component: pageName,
    url: urls[pageName] ?? '/orders',
    version: 'landing-orders',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors,
        auth: {
            user: {
                id: 'account-1',
                name: 'Maria Soler',
                email: 'maria@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: ['member'],
        },
        roleAreas: [{ role: 'member', label: 'Member', href: '/orders' }],
        sidebarOpen: true,
        ...pageProps[pageName],
    },
};

// E: typing triggers the real preview request; answer it with the same page so nothing reloads.
if (state === 'domain-error')
    http.setClient({
        request: async () => ({
            status: 200,
            data: JSON.stringify({
                ...page,
                url: window.location.pathname + window.location.search,
            }),
            headers: { 'x-inertia': 'true' },
        }),
    });
// C: a save that never answers keeps the row in its saving state.
if (state === 'saving')
    http.setClient({ request: () => new Promise(() => {}) });

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

const fieldFor = (id: string) =>
    document.querySelector<HTMLInputElement>(`[data-test="quantity-${id}"]`);
const sleep = (ms: number) =>
    new Promise((resolve) => window.setTimeout(resolve, ms));

const visible = (element: HTMLElement) => element.getClientRects().length > 0;
const waitFor = async <T>(find: () => T | null | undefined): Promise<T> => {
    for (let attempt = 0; attempt < 120; attempt++) {
        const found = find();
        if (found) return found;
        await sleep(25);
    }
    throw new Error(`Visual target missing in state ${state}`);
};
// The shell overlays the order pages inherit: the mobile sheet and the account menu.
const openOverlay = async (overlay: string | null) => {
    if (overlay === 'sheet') {
        (
            await waitFor(() =>
                document.querySelector<HTMLElement>(
                    'button[data-sidebar="trigger"]',
                ),
            )
        ).click();
        await waitFor(() =>
            document.querySelector(
                '[data-sidebar="sidebar"][data-mobile="true"]',
            ),
        );
        await sleep(600);
    }
    if (overlay === 'menu') {
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
        await waitFor(() =>
            document.querySelector('[data-slot="dropdown-menu-content"]'),
        );
        await sleep(300);
    }
};

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: () => AppLayout,
    withApp: (app, { page }) => {
        installTranslations(app, page.props.locale);
    },
    progress: false,
}).then(async () => {
    await sleep(400);
    // E and C start from a row edited by hand, so "Save" is active.
    if (state === 'domain-error' || state === 'saving') {
        const field = fieldFor('product-1');
        if (field) {
            field.value = '4';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            // Past the 250 ms preview pause, so the pending preview cannot cancel the save.
            await sleep(350);
        }
    }
    if (state === 'saving') {
        document
            .querySelector<HTMLButtonElement>('[data-test="save-product-1"]')
            ?.click();
        await sleep(100);
    }
    await openOverlay(query.get('overlay'));
    document.body.dataset.visualReady = state;
    window.history.replaceState(window.history.state, '', fixtureUrl);
});

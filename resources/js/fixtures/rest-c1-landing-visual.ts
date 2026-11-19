import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import { financeBasketRow } from '@/fixtures/financeBasketRow';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

/*
 * Deterministic review fixture for checkpoint C1 (spec 005, slices 3-7): finance and reception pages in the
 * landing identity. Only the HTTP client is mocked: the real Inertia router, layouts, pages and portals run.
 * It proves presentation, never persistence or authorization. Each C1 task adds its pages and states here.
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Receptions/Show';
const state = query.get('state') ?? 'default';
const roles = (query.get('role') ?? 'manager,receiver').split(',');
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
const allocation = (
    householdId: string,
    householdName: string,
    quantity: string,
    byHand = false,
) => ({
    householdId,
    householdName,
    ordered: quantity,
    allocated: quantity,
    byHand,
    delivered: quantity,
    adjusted: false,
});
const product = (
    productId: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    productId,
    name,
    unit: 'kg',
    variable: false,
    nobodyOrdered: false,
    ordered: '12.000',
    received: '12.000',
    allocated: '12.000',
    delivered: '12.000',
    difference: '0.000',
    balanced: true,
    balancedForDistribution: true,
    hasManualAllocations: false,
    hasAdjustments: false,
    allocations: [
        allocation('household-1', 'Família Soler', '7.000'),
        allocation('household-2', 'Família Puig', '5.000'),
    ],
    ...over,
});
const discrepancy = {
    received: '10.000',
    allocated: '12.000',
    difference: '2.000',
    balanced: false,
    balancedForDistribution: false,
};
// Reception states: open (balanced), received (validated and distributed), discrepancy, manual allocation
// (the received-total dialog), long content. `error` and `busy` reuse the discrepancy rows.
const productsByState: Record<string, ReturnType<typeof product>[]> = {
    default: [product('tomato', 'Tomàquet de pera')],
    received: [product('tomato', 'Tomàquet de pera')],
    discrepancy: [product('tomato', 'Tomàquet de pera', discrepancy)],
    'total-dialog': [
        product('tomato', 'Tomàquet de pera', {
            hasManualAllocations: true,
            allocations: [
                allocation('household-1', 'Família Soler', '7.000', true),
                allocation('household-2', 'Família Puig', '5.000'),
            ],
        }),
    ],
    'long-content': [
        product('tomato', longName, { unit: 'safata de cartró reciclat' }),
    ],
};
const productsKey = /^(?:error|busy|select.*)$/.test(state)
    ? 'discrepancy'
    : state;
const distribution = {
    validated: state === 'received',
    distributed: state === 'received',
    settled: false,
    closed: false,
};
const households = [
    { id: 'household-1', name: 'Família Soler' },
    { id: 'household-2', name: 'Família Puig' },
    { id: 'household-3', name: 'Família Vidal' },
];
// Finance basket form (T20): `empty` has no active household, `long-content` a long household name. The
// `invalid`, `error` and `busy` states submit the form after choosing a household.
const basketHouseholds = () => {
    if (state === 'empty') return [];
    if (state === 'long-content')
        // Second place: the keyboard path (ArrowDown) chooses it, so the closed trigger shows the long name.
        return [
            households[0],
            { id: 'household-2', name: longName },
            households[2],
        ];
    return households;
};
const accounts = [
    {
        accountId: 'account-2',
        name: 'Pere Vidal',
        email: 'pere@example.test',
        roles: ['member'],
        householdId: 'household-1',
        householdName: 'Família Soler',
        active: true,
        invitationPending: false,
    },
];
// Reception list states (T18): empty, one row, many rows, long supplier name; `default` is a mixed cycle.
const listProducer = (id: string, name: string, listState: string) => ({
    producerId: id,
    producerName: name,
    state: listState,
});
const listProducers: Record<string, ReturnType<typeof listProducer>[]> = {
    default: [
        listProducer('producer-1', 'Hort de la Plana', 'sent'),
        listProducer('producer-2', 'Forn del Poble', 'validated'),
        listProducer('producer-3', 'Formatges de la Vall', 'distributed'),
    ],
    one: [listProducer('producer-1', 'Hort de la Plana', 'sent')],
    'long-content': [listProducer('producer-1', longName, 'validated')],
};
const manyCycles = Array.from({ length: 8 }, (_, index) => ({
    cycleId: `cycle-${index + 1}`,
    pickupDate: `2026-11-${String(14 - index).padStart(2, '0')}`,
    closed: index > 0,
    producers: listProducers.default,
}));
const listCycles = () => {
    if (state === 'empty') return [];
    if (state === 'many') return manyCycles;
    if (!listProducers[state]) throw new Error(`Unknown list state: ${state}`);
    return [
        {
            cycleId: 'cycle-1',
            pickupDate: '2026-11-14',
            closed: false,
            producers: listProducers[state],
        },
    ];
};
// Finance basket states (T19), lazy per page. Index: `default` mixes every status, plus pending, empty,
// long name and large amount. Show: `default` pending collection, `draft` (unsent), `collected`
// with an adjustment and a return line, long name and large amount. Amounts are asserted against these cents.
const basketSummary = (
    id: string,
    householdName: string,
    basketState: string,
    totalCents: number,
    over: Record<string, unknown> = {},
) => ({
    id,
    householdName,
    state: basketState,
    status: basketState,
    totalCents,
    createdAt: '2026-11-10 10:00',
    validatedAt: basketState === 'draft' ? null : '2026-11-12 08:15',
    pendingCollection: basketState === 'validated',
    ...over,
});
const basketLists: Record<string, ReturnType<typeof basketSummary>[]> = {
    default: [
        basketSummary('basket-1', 'Família Soler', 'draft', 1250),
        basketSummary('basket-2', 'Família Puig', 'validated', 2300),
        basketSummary('basket-3', 'Família Vidal', 'collected', 4590),
        basketSummary('basket-4', 'Família Roca', 'validated', 0, {
            status: 'reversed',
            pendingCollection: false,
        }),
        basketSummary('basket-5', 'Família Serra', 'cancelled', 800),
    ],
    'long-content': [basketSummary('basket-1', longName, 'validated', 2300)],
    'large-amount': [
        basketSummary('basket-1', 'Família Soler', 'validated', 123456789),
    ],
};
basketLists.pending = basketLists.default.filter(
    (basket) => basket.pendingCollection,
);
const basketIndexProps = () => {
    if (state === 'empty') return { baskets: [], pending: false };
    if (!basketLists[state])
        throw new Error(`Unknown basket list state: ${state}`);
    return { baskets: basketLists[state], pending: state === 'pending' };
};
const basketStockLine = (id: string, name: string, cents: number) => ({
    stockProductId: id,
    name,
    unit: 'ampolla',
    quantity: '2.000',
    unitPriceCents: cents / 2,
    amountCents: cents,
});
const basketShowProps = () => {
    const known = [
        'default',
        'draft',
        'collected',
        'weekly',
        'long-content',
        'large-amount',
    ];
    if (!known.includes(state))
        throw new Error(`Unknown basket state: ${state}`);
    const cents = state === 'large-amount' ? 123456789 : 2300;
    const settled = state === 'default' || state === 'collected';
    const rowState =
        state === 'draft'
            ? 'draft'
            : state === 'collected'
              ? 'collected'
              : 'validated';
    return {
        basket: financeBasketRow({
            id: 'basket-1',
            householdId: 'household-1',
            householdName:
                state === 'long-content' ? longName : 'Família Soler',
            state: rowState,
            kind: state === 'weekly' ? 'weekly' : 'stock',
            weeklyLines:
                state === 'weekly'
                    ? [
                          {
                              productId: 'tomato',
                              name: 'Tomàquet de pera',
                              unit: 'kg',
                              ordered: '2.000',
                              allocated: '2.000',
                              quantity: '1.500',
                              unitPriceCents: 400,
                              amountCents: 600,
                              effectiveQuantity: '1.500',
                              effectiveAmountCents: 600,
                              differsFromAllocation: true,
                          },
                      ]
                    : [],
            returnLines:
                state === 'collected'
                    ? [
                          {
                              returnItemId: 'jar',
                              name: 'Pot de vidre',
                              amountCents: 150,
                              quantity: 2,
                              creditCents: 300,
                          },
                      ]
                    : [],
            stockLines: [
                basketStockLine(
                    'stock-1',
                    state === 'long-content' ? longName : 'Oli',
                    cents,
                ),
            ],
            totalCents: cents,
            effectiveTotalCents: state === 'collected' ? cents - 300 : cents,
            adjustments:
                state === 'collected'
                    ? [
                          {
                              id: 'adjustment-1',
                              amountCents: -300,
                              reason: 'Ampolla trencada',
                              authorName: 'Maria Soler',
                              occurredAt: '2026-11-13T09:00:00+00:00',
                              lines: [],
                          },
                      ]
                    : [],
            balanceCents: -cents,
            projectedBalanceCents: null,
            validatedAt: settled ? '2026-11-12T08:15:00+00:00' : null,
            collectedAt:
                state === 'collected' ? '2026-11-13T09:00:00+00:00' : null,
        }),
        containerBalance: 3,
        stockProducts: [],
        returnItems: [],
    };
};
const pageProps: Record<string, Record<string, unknown>> = {
    // Lazy: other pages use their own states (`busy`, `received`...), which are not list states.
    'Receptions/Index': {
        get cycles() {
            return listCycles();
        },
    },
    'Finance/Baskets/Index': {
        get baskets() {
            return basketIndexProps().baskets;
        },
        get pending() {
            return basketIndexProps().pending;
        },
    },
    'Finance/Baskets/Show': {
        get basket() {
            return basketShowProps().basket;
        },
        get containerBalance() {
            return basketShowProps().containerBalance;
        },
        get stockProducts() {
            return basketShowProps().stockProducts;
        },
        get returnItems() {
            return basketShowProps().returnItems;
        },
    },
    'Finance/Baskets/Create': {
        get households() {
            return basketHouseholds();
        },
    },
    'Receptions/Show': {
        reception: {
            cycleId: 'cycle-1',
            producerId: 'producer-1',
            producerName: 'Hort de la Plana',
            pickupDate: '2026-11-14',
            products: productsByState[productsKey] ?? productsByState.default,
            unorderedOptions: [
                {
                    productId: 'pepper',
                    name: 'Pebrot',
                    unit: 'kg',
                    variable: false,
                },
                {
                    productId: 'onion',
                    name: 'Ceba tendra',
                    unit: 'manat',
                    variable: false,
                },
            ],
        },
        distribution,
        can: {
            edit: state !== 'received',
            validate: roles.includes('manager') && state !== 'received',
            distribute: roles.includes('manager') && state !== 'received',
            reopen: state === 'received',
        },
        households,
    },
    // Old-theme control: the shared select must look the same here (R23).
    'Manage/Accounts/Index': {
        accounts,
        households: households.map((h) => ({
            ...h,
            active: true,
            memberCount: 1,
        })),
    },
};
const urls: Record<string, string> = {
    'Receptions/Index': '/receptions',
    'Finance/Baskets/Index': '/finance/baskets',
    'Finance/Baskets/Show': '/finance/baskets/basket-1',
    'Finance/Baskets/Create': '/finance/baskets/create',
    'Receptions/Show': '/receptions/cycle-1/producer-1',
    'Manage/Accounts/Index': '/manage/accounts',
};
const page = {
    component: pageName,
    url: urls[pageName] ?? '/',
    version: 'rest-c1-landing',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
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
            roles,
        },
        roleAreas: [
            { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
        ],
        sidebarOpen: true,
        ...pageProps[pageName],
    },
};

// Request spy: the PUTs the real router sends, so busy and error states are proven to come from them.
const requests: string[] = [];
http.setClient({
    request: async (config: { url: string; method?: string }) => {
        const url = new URL(config.url, window.location.origin);
        const method = (config.method ?? 'get').toLowerCase();
        const reply = (body: Record<string, unknown>) => ({
            status: 200,
            data: JSON.stringify(body),
            headers: { 'x-inertia': 'true' },
        });
        if (method !== 'get') {
            requests.push(`${method} ${url.pathname}`);
            // Busy: the request never answers, so the page stays in `processing`.
            if (state === 'busy') return new Promise(() => {});
            return reply({
                ...page,
                props: {
                    ...page.props,
                    errors:
                        state === 'invalid'
                            ? {
                                  household_id:
                                      'El camp unitat de convivència és obligatori.',
                              }
                            : {
                                  domain:
                                      pageName === 'Finance/Baskets/Create'
                                          ? 'Tria una llar activa.'
                                          : 'No s’ha pogut desar la recepció.',
                              },
                },
            });
        }
        return reply({ ...page, url: url.pathname });
    },
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
const set = (key: string, value: string) =>
    document.body.setAttribute(`data-ev-${key}`, value);
const byTest = (name: string) =>
    waitFor(() => document.querySelector<HTMLElement>(`[data-test="${name}"]`));
// Colour transitions must finish before a value is recorded.
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
const key = (target: Element, name: string) =>
    target.dispatchEvent(
        new KeyboardEvent('keydown', {
            key: name,
            code: name,
            bubbles: true,
            cancelable: true,
        }),
    );
const channels = (css: string) =>
    (css.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
const luminance = (css: string) => {
    const [r, g, b] = channels(css).map((channel) => {
        const unit = channel / 255;
        return unit <= 0.03928 ? unit / 12.92 : ((unit + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const contrast = (first: string, second: string) => {
    const [high, low] = [luminance(first), luminance(second)].sort(
        (a, b) => b - a,
    );
    return (high + 0.05) / (low + 0.05);
};
const popupSelector = '[data-slot="select-content"][data-state="open"]';
// The add-product select has several options; elsewhere the first visible select is the page's own.
const firstTrigger = () =>
    document.querySelector<HTMLElement>('#unordered-product') ??
    [
        ...document.querySelectorAll<HTMLElement>(
            '#main-content [data-slot="select-trigger"]',
        ),
    ].find(visible);

// The primary action is the page's filled (default variant) button, read from its computed colour.
const recordPrimary = () => {
    const button = [
        ...document.querySelectorAll<HTMLElement>(
            '#main-content button[data-slot="button"].bg-primary:not([disabled])',
        ),
    ].find(visible);
    if (button) set('primary-bg', getComputedStyle(button).backgroundColor);
};
// gap-3 and p-3 of the product panel are a retained exception (layout-rules.spec.ts): record what they compute to.
const recordPanel = () => {
    const panel = document.querySelector<HTMLElement>(
        '#main-content section[data-test^="product-"]',
    );
    if (!panel) return;
    const style = getComputedStyle(panel);
    const rem = parseFloat(getComputedStyle(document.documentElement).fontSize);
    set('panel-padding-rem', String(parseFloat(style.paddingTop) / rem));
    set('panel-gap-rem', String(parseFloat(style.rowGap) / rem));
    const header = document.querySelector<HTMLElement>(
        '#main-content [data-test="distribution"]',
    );
    if (header)
        set(
            'header-gap-rem',
            String(parseFloat(getComputedStyle(header).rowGap) / rem),
        );
};
const recordPopup = (popup: HTMLElement) => {
    const surface = getComputedStyle(popup);
    set('popup-bg', surface.backgroundColor);
    set('popup-fg', surface.color);
    set('popup-border', surface.borderTopColor);
    set('popup-shadow', surface.boxShadow);
    set('popup-radius', surface.borderTopLeftRadius);
    // The popup is portalled outside the measured root, so the runner never bounds it: record it here.
    const box = popup.getBoundingClientRect();
    set('popup-left', String(Math.round(box.left)));
    set('popup-right', String(Math.round(box.right)));
    set(
        'popup-inside-viewport',
        String(box.left >= 0 && box.right <= window.innerWidth),
    );
    set(
        'popup-text-contrast',
        contrast(surface.color, surface.backgroundColor).toFixed(2),
    );
    const items = [
        ...popup.querySelectorAll<HTMLElement>('[data-slot="select-item"]'),
    ];
    set('item-count', String(items.length));
    set(
        'item-min-height',
        String(
            Math.min(
                ...items.map((item) => item.getBoundingClientRect().height),
            ),
        ),
    );
    // `focus:bg-accent` needs OS focus, which a headless page lacks: resolve the pair the item uses.
    const probe = document.createElement('span');
    probe.style.backgroundColor = 'var(--accent)';
    probe.style.color = 'var(--accent-foreground)';
    popup.append(probe);
    const highlight = getComputedStyle(probe);
    set('highlight-bg', highlight.backgroundColor);
    set('highlight-fg', highlight.color);
    set(
        'highlight-contrast',
        contrast(highlight.color, highlight.backgroundColor).toFixed(2),
    );
    probe.remove();
};
type OpenBy = 'Enter' | ' ' | 'pointer' | 'touch';
// Reka ignores a release close to the opening press, so the events carry the element's real position.
const pointer = (
    target: Element,
    type: string,
    pointerType: 'mouse' | 'touch' = 'mouse',
) => {
    const box = target.getBoundingClientRect();
    return target.dispatchEvent(
        new PointerEvent(type, {
            bubbles: true,
            cancelable: true,
            button: 0,
            pointerType,
            clientX: box.left + box.width / 2,
            clientY: box.top + box.height / 2,
        }),
    );
};
const openSelect = async (by: OpenBy = 'Enter') => {
    const trigger = await waitFor(firstTrigger);
    trigger.focus();
    if (by === 'pointer') pointer(trigger, 'pointerdown');
    else if (by === 'touch') {
        // Reka ignores a touch press on the trigger and opens on the release.
        pointer(trigger, 'pointerdown', 'touch');
        pointer(trigger, 'pointerup', 'touch');
        trigger.click();
    } else key(trigger, by);
    const popup = await waitFor(() =>
        document.querySelector<HTMLElement>(popupSelector),
    );
    await settle();
    await sleep(200);
    return { trigger, popup };
};
const closedPopup = () =>
    waitFor(() =>
        document.querySelector(popupSelector) ? null : document.body,
    );
// The highlight of the item the keyboard reached: its own computed pair. When the headless page has no
// OS focus the `focus:` utilities do not match, so the token pair is recorded instead and says so.
const recordHighlight = (item: HTMLElement) => {
    const own = getComputedStyle(item);
    const transparent = own.backgroundColor === 'rgba(0, 0, 0, 0)';
    const probe = document.createElement('span');
    probe.style.backgroundColor = 'var(--accent)';
    probe.style.color = 'var(--accent-foreground)';
    item.append(probe);
    const resolved = getComputedStyle(probe);
    const bg = transparent ? resolved.backgroundColor : own.backgroundColor;
    const fg = transparent ? resolved.color : own.color;
    probe.remove();
    set('active-highlight-source', transparent ? 'token-fallback' : 'item');
    set('active-highlight-bg', bg);
    set('active-highlight-fg', fg);
    set('active-highlight-contrast', contrast(fg, bg).toFixed(2));
};
// Keyboard model: Enter or Space opens, ArrowDown moves, Enter chooses, Escape closes and focus returns.
// The pointer variant opens with a mouse press and chooses by pressing and releasing on the item.
const runSelectKeyboard = async (by: OpenBy) => {
    const trigger = await waitFor(firstTrigger);
    const before = trigger.textContent?.trim() ?? '';
    const { popup } = await openSelect(by);
    set('opened-by', by === ' ' ? 'Space' : by);
    let arrowed: string;
    if (by === 'pointer' || by === 'touch') {
        const touch = by === 'touch' ? 'touch' : 'mouse';
        const items = [
            ...popup.querySelectorAll<HTMLElement>('[data-slot="select-item"]'),
        ];
        const target = items[items.length - 1];
        arrowed = target.textContent?.trim() ?? '';
        pointer(target, 'pointermove', touch);
        pointer(target, 'pointerdown', touch);
        pointer(target, 'pointerup', touch);
        // A release is followed by a click; Reka selects a touch item on it.
        target.click();
    } else {
        // The key goes to the focused item and bubbles to the popup, as from a keyboard.
        key(document.activeElement ?? popup, 'ArrowDown');
        await sleep(150);
        const active = document.activeElement as HTMLElement;
        arrowed = active.textContent?.trim() ?? 'none';
        recordHighlight(active);
        key(active, 'Enter');
    }
    await closedPopup();
    await sleep(200);
    const chosen = trigger.textContent?.trim() ?? '';
    set('arrowed', arrowed);
    set('chosen', chosen);
    set('chosen-matches', String(chosen === arrowed));
    set('changed', String(chosen !== before));
    set(
        'focus-returned-after-choice',
        String(document.activeElement === trigger),
    );
    const reopened = await openSelect(by);
    key(reopened.popup, 'Escape');
    await closedPopup();
    await sleep(200);
    set('escape-closed', 'true');
    set(
        'focus-returned-after-escape',
        String(document.activeElement === trigger),
    );
};
const dialogOpen = () =>
    document.querySelector<HTMLElement>('[role="dialog"][data-state="open"]');
// Opens by keyboard (a click on the focused button is what Enter/Space produce), traps focus, and Escape
// closes it and returns focus to the button that opened it.
const runTotalDialog = async () => {
    const save = await byTest('save-received-tomato');
    save.focus();
    save.click();
    const dialog = await waitFor(dialogOpen);
    await settle();
    await sleep(200);
    set('dialog-landing', String(dialog.classList.contains('landing')));
    set('dialog-bg', getComputedStyle(dialog).backgroundColor);
    set('dialog-focus-inside', String(dialog.contains(document.activeElement)));
    const confirm = await byTest('confirm-overwrite-tomato');
    set('dialog-destructive-bg', getComputedStyle(confirm).backgroundColor);
    set(
        'dialog-destructive-contrast',
        contrast(
            getComputedStyle(confirm).color,
            getComputedStyle(confirm).backgroundColor,
        ).toFixed(2),
    );
    key(dialog, 'Escape');
    await waitFor(() => (dialogOpen() ? null : document.body));
    await sleep(300);
    set('dialog-escape-closed', 'true');
    set('dialog-focus-returned', String(document.activeElement === save));
};
const runToast = async () => {
    toast('Recepció desada');
    await waitFor(() => document.querySelector('[data-sonner-toaster]'));
    await sleep(300);
    set(
        'toaster-landing',
        String(
            document
                .querySelector('[data-sonner-toaster]')!
                .classList.contains('landing'),
        ),
    );
};
const runNavSheet = async () => {
    (
        await waitFor(() =>
            document.querySelector<HTMLElement>(
                'button[data-sidebar="trigger"]',
            ),
        )
    ).click();
    const sheet = await waitFor(() =>
        document.querySelector<HTMLElement>(
            '[data-sidebar="sidebar"][data-mobile="true"]',
        ),
    );
    await sleep(600);
    set('sheet-landing', String(sheet.classList.contains('landing')));
};
const runAccountMenu = async () => {
    const trigger = await waitFor(() =>
        [
            ...document.querySelectorAll<HTMLElement>(
                '[data-test="sidebar-menu-button"]',
            ),
        ].find(visible),
    );
    key(trigger, 'ArrowDown');
    const menu = await waitFor(() =>
        document.querySelector<HTMLElement>(
            '[data-slot="dropdown-menu-content"]',
        ),
    );
    await sleep(300);
    set('menu-landing', String(menu.classList.contains('landing')));
};
const runBusyOrError = async () => {
    const basketForm = pageName === 'Finance/Baskets/Create';
    // A required select blocks the submit until a household is chosen: use the real keyboard path first.
    if (basketForm) await runSelectKeyboard('Enter');
    const save = await byTest(
        basketForm ? 'create-basket' : 'save-received-tomato',
    );
    save.click();
    await waitFor(() => (requests.length ? document.body : null));
    if (basketForm && state !== 'busy')
        await waitFor(() =>
            document.querySelector('#main-content .text-destructive'),
        );
    else if (state === 'error')
        await waitFor(() =>
            document.querySelector('[data-test="domain-error"]:not(:empty)'),
        );
    await sleep(400);
    set('requests', requests.join(' ; '));
};

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: () => AppLayout,
    withApp: (app, { page: initial }) => {
        installTranslations(app, initial.props.locale);
    },
    progress: false,
}).then(async () => {
    await settle();
    try {
        if (state === 'select') {
            const { popup } = await openSelect();
            recordPopup(popup);
            set(
                'popup-carries-landing',
                String(popup.classList.contains('landing')),
            );
        }
        if (state === 'select-keyboard') await runSelectKeyboard('Enter');
        if (state === 'long-content' && pageName === 'Finance/Baskets/Create') {
            await runSelectKeyboard('Enter');
            recordPopup((await openSelect()).popup);
        }
        if (state === 'select-space') await runSelectKeyboard(' ');
        if (state === 'select-pointer') await runSelectKeyboard('pointer');
        if (state === 'select-touch') await runSelectKeyboard('touch');
        if (state === 'total-dialog') await runTotalDialog();
        if (state === 'toast') await runToast();
        if (state === 'account-menu') await runAccountMenu();
        if (state === 'nav-sheet') await runNavSheet();
        if (['busy', 'error', 'invalid'].includes(state))
            await runBusyOrError();
    } catch (error) {
        set('error', String(error));
    }
    recordPanel();
    recordPrimary();
    document.body.dataset.requests = String(requests.length);
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

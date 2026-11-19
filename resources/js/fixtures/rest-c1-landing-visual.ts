import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
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
const pageProps: Record<string, Record<string, unknown>> = {
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
                    errors: { domain: 'No s’ha pogut desar la recepció.' },
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
const recordPopup = (popup: HTMLElement) => {
    const surface = getComputedStyle(popup);
    set('popup-bg', surface.backgroundColor);
    set('popup-fg', surface.color);
    set('popup-border', surface.borderTopColor);
    set('popup-shadow', surface.boxShadow);
    set('popup-radius', surface.borderTopLeftRadius);
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
const openSelect = async () => {
    const trigger = await waitFor(firstTrigger);
    trigger.focus();
    key(trigger, 'Enter');
    const popup = await waitFor(() =>
        document.querySelector<HTMLElement>(popupSelector),
    );
    await settle();
    await sleep(200);
    return { trigger, popup };
};
// Keyboard model: Enter opens, ArrowDown moves, Enter chooses, Escape closes and focus returns.
const runSelectKeyboard = async () => {
    const trigger = await waitFor(firstTrigger);
    const before = trigger.textContent?.trim() ?? '';
    const { popup } = await openSelect();
    set('opened-by-enter', 'true');
    // The key goes to the focused item and bubbles to the popup, as from a keyboard.
    key(document.activeElement ?? popup, 'ArrowDown');
    await sleep(150);
    set(
        'active-after-arrow',
        document.activeElement?.textContent?.trim() ?? 'none',
    );
    // The focused item receives Enter, as it does from a keyboard.
    key(document.activeElement!, 'Enter');
    await waitFor(() =>
        document.querySelector(popupSelector) ? null : document.body,
    );
    await sleep(200);
    set('chosen', trigger.textContent?.trim() ?? '');
    set('changed', String((trigger.textContent?.trim() ?? '') !== before));
    set(
        'focus-returned-after-choice',
        String(document.activeElement === trigger),
    );
    const reopened = await openSelect();
    key(reopened.popup, 'Escape');
    await waitFor(() =>
        document.querySelector(popupSelector) ? null : document.body,
    );
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
    const save = await byTest('save-received-tomato');
    save.click();
    await waitFor(() => (requests.length ? document.body : null));
    if (state === 'error')
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
        if (state === 'select-keyboard') await runSelectKeyboard();
        if (state === 'total-dialog') await runTotalDialog();
        if (state === 'toast') await runToast();
        if (state === 'account-menu') await runAccountMenu();
        if (state === 'nav-sheet') await runNavSheet();
        if (state === 'busy' || state === 'error') await runBusyOrError();
    } catch (error) {
        set('error', String(error));
    }
    recordPrimary();
    document.body.dataset.requests = String(requests.length);
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

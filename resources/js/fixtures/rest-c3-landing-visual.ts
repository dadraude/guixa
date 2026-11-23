import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

/*
 * Deterministic review fixture for checkpoint C3 (spec 005, slices 3-7): the wallet statement, the member baskets and the stock list in the landing identity.
 * Only the HTTP client is mocked: the real Inertia router, layouts, pages and portals run. It proves presentation, never
 * persistence or authorization. Each C3 task adds its pages and states here.
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Wallet/Statement';
const state = query.get('state') ?? 'default';
const roles = (query.get('role') ?? 'manager').split(',');
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
const longNote = `Ingrés per transferència bancària pendent de conciliar amb l'extracte de ${longName}`;

// A member reads their own household wallet (`/wallet`, no household, no forms); a manager or finance user reads any
// household's statement with the receipt and correction forms (R25: the audience changes the shell, not the statement).
const showsForms = roles.some((role) => ['manager', 'finance'].includes(role));
const walletUrl = showsForms
    ? '/finance/households/household-1/wallet'
    : '/wallet';

const movement = (
    id: string,
    type: string,
    amount: string,
    occurredAt: string,
    over: Record<string, unknown> = {},
) => ({
    id,
    type,
    amount,
    occurredAt,
    note: null as string | null,
    cycleId: null as string | null,
    basketId: null as string | null,
    sourceMovementId: null as string | null,
    authorName: 'Marta Soler',
    ...over,
});

// `default` mixes every movement type and source (receipt, basket charge and credit, correction of a receipt, cycle);
// `empty` is a household with no movements, `negative` an unpaid balance, `long-content` long note, name and amount (R25 cents).
const walletProps = () => {
    if (state === 'empty') return { balance: '0.00', movements: [] };
    if (state === 'negative')
        return {
            balance: '-12.50',
            movements: [
                movement('m-2', 'charge', '-62.50', '2026-11-12 10:00', {
                    basketId: 'basket-1',
                    note: 'Cistella setmanal',
                }),
                movement('m-1', 'top_up', '50.00', '2026-11-10 09:00', {
                    note: 'Ingrés',
                }),
            ],
        };
    if (state === 'long-content')
        return {
            balance: '-1234567.88',
            movements: [
                movement('m-2', 'charge', '-1234567.89', '2026-11-12 10:00', {
                    basketId: 'basket-with-a-very-long-identifier-1234567890',
                    note: longNote,
                    authorName: longName,
                }),
                movement('m-1', 'top_up', '0.01', '2026-11-10 09:00', {
                    note: longNote,
                }),
            ],
        };
    return {
        balance: '13.05',
        movements: [
            movement('m-6', 'correction', '-5.00', '2026-11-13 12:00', {
                note: 'Ingrés duplicat',
                sourceMovementId: 'm-1',
            }),
            movement('m-5', 'credit', '3.50', '2026-11-12 18:30', {
                basketId: 'basket-2',
            }),
            movement('m-4', 'charge', '-12.45', '2026-11-12 10:00', {
                basketId: 'basket-1',
                note: 'Cistella setmanal',
            }),
            movement('m-3', 'charge', '-8.00', '2026-11-05 10:00', {
                cycleId: 'cycle-1',
            }),
            movement('m-1', 'top_up', '20.00', '2026-10-30 09:00', {
                note: 'Ingrés',
            }),
            movement('m-2', 'top_up', '15.00', '2026-10-29 10:00', {
                note: 'Ingrés',
            }),
        ],
    };
};
const statementProps = () => ({
    wallet: walletProps(),
    ...(state === 'status' ? { status: 'Ingrés registrat.' } : {}),
    household: showsForms
        ? {
              id: 'household-1',
              name: state === 'long-content' ? longName : 'Família Soler',
          }
        : null,
    ...(showsForms
        ? {
              canManageReceipts: true,
              householdReturnHref: roles.includes('manager')
                  ? '/manage/households/household-1'
                  : undefined,
              receiptAction: '/finance/households/household-1/wallet/top-ups',
              correctionAction:
                  '/finance/households/household-1/wallet/corrections',
              correctionId: 'correction-1',
          }
        : {}),
});

// Member baskets (`/baskets`): `default` mixes an unsent (draft) basket, one pending collection (validated, a debt) and a collected one;
// `empty` has none; `long-content` a long household name and a seven-digit total (R22, R25 cents).
const basketSummary = (
    id: string,
    status: string,
    totalCents: number,
    over: Record<string, unknown> = {},
) => ({
    id,
    householdName: 'Família Soler',
    state: status,
    status,
    totalCents,
    createdAt: '2026-11-10 09:00',
    validatedAt: null as string | null,
    pendingCollection: status === 'validated',
    ...over,
});
// The producers (`BasketSummaryRow`, `ListMemberPendingCollections`): a validated or collected basket has its validation instant,
// the pending-collections variant (`/baskets?pending=1`, linked from the dashboard) lists validated baskets only.
const basketListProps = () => {
    if (state === 'pending-empty') return { baskets: [], pending: true };
    if (state === 'pending')
        return {
            baskets: [
                basketSummary('basket-2', 'validated', -100, {
                    createdAt: '2026-11-05 10:00',
                    validatedAt: '2026-11-06 10:00',
                }),
                basketSummary('basket-4', 'validated', 4580, {
                    householdName: longName,
                    createdAt: '2026-11-07 10:00',
                    validatedAt: '2026-11-08 18:30',
                }),
            ],
            pending: true,
        };
    if (state === 'empty') return { baskets: [], pending: false };
    if (state === 'long-content')
        return {
            baskets: [
                basketSummary('basket-1', 'validated', -123456789, {
                    householdName: longName,
                    validatedAt: '2026-11-12 10:00',
                }),
                basketSummary('basket-2', 'draft', 123456789, {
                    householdName: longName,
                }),
            ],
            pending: false,
        };
    return {
        baskets: [
            basketSummary('basket-1', 'draft', 2300),
            basketSummary('basket-2', 'validated', -100, {
                createdAt: '2026-11-05 10:00',
                validatedAt: '2026-11-06 10:00',
            }),
            basketSummary('basket-3', 'collected', 1245, {
                createdAt: '2026-10-29 10:00',
                validatedAt: '2026-10-30 10:00',
            }),
        ],
        pending: false,
    };
};
const stockLine = (
    name: string,
    quantity: string,
    unitPriceCents: number,
    amountCents: number,
) => ({
    stockProductId: `stock-${name}`,
    name,
    unit: 'kg',
    quantity,
    unitPriceCents,
    amountCents,
    effectiveQuantity: quantity,
    effectiveAmountCents: amountCents,
});
const weeklyLine = (name: string, ordered: string, allocated: string) => ({
    productId: `weekly-${name}`,
    name,
    unit: 'kg',
    ordered,
    allocated,
    quantity: allocated,
    unitPriceCents: 320,
    amountCents: 960,
    effectiveQuantity: allocated,
    effectiveAmountCents: 960,
    differsFromAllocation: false,
});
// Basket detail as `BasketData` and `BasketActions::forMember` produce it. `default` is an unsent draft; `validated` (and its
// `validated-busy` / `validated-error` request states) waits for collection: `settled` is true for validated and collected
// (`BasketState::isSettled`), the member may mark it collected (`markCollected` is `isPendingCollection`) and no collected event
// exists yet; `collected` has the one `collected` event that collecting appends; `weekly` is a weekly draft; `long-content` has a long
// household and product name and a seven-digit total. Quantities have three decimals, dates are ATOM strings, kind is `stock` or `weekly`.
const basketProps = () => {
    const status = state.startsWith('validated')
        ? 'validated'
        : state === 'collected'
          ? 'collected'
          : 'draft';
    const long = state === 'long-content';
    const weekly = state === 'weekly';
    const total = long ? 123456789 : weekly ? 960 : 1350;
    const unitPriceCents = long ? 41152263 : 450;
    return {
        basket: {
            id: 'basket-1',
            householdId: 'household-1',
            householdName: long ? longName : 'Família Soler',
            state: status,
            stockLines: weekly
                ? []
                : [
                      stockLine(
                          long ? longName : 'Mongetes',
                          '3.000',
                          unitPriceCents,
                          total,
                      ),
                  ],
            returnLines: [],
            totalCents: total,
            balanceCents: long ? -123456789 : -1250,
            projectedBalanceCents: status === 'draft' ? -2600 : null,
            validatedAt:
                status === 'draft' ? null : '2026-11-06T10:00:00+01:00',
            collectedAt:
                status === 'collected' ? '2026-11-07T10:00:00+01:00' : null,
            effectiveTotalCents: total,
            adjustments: [],
            collectionEvents:
                status === 'collected'
                    ? [
                          {
                              id: 'event-1',
                              type: 'collected',
                              authorName: 'Maria Soler',
                              occurredAt: '2026-11-07T10:00:00+01:00',
                              reason: null,
                          },
                      ]
                    : [],
            pendingCollection: status === 'validated',
            can: {
                edit: false,
                validate: false,
                cancel: false,
                markCollected: status === 'validated',
                correct: false,
                correctCollection: false,
            },
            status,
            settled: status !== 'draft',
            collected: status === 'collected',
            weekly,
            weeklyLines: weekly
                ? [
                      weeklyLine('Cols', '3.000', '3.000'),
                      weeklyLine(longName, '3.000', '3.000'),
                  ]
                : [],
            kind: weekly ? 'weekly' : 'stock',
            late: false,
        },
    };
};
const stockOffer = (
    id: string,
    name: string,
    available: string,
    over: Record<string, unknown> = {},
) => ({
    id,
    producerId: 'producer-1',
    producerName: 'Cal Pagès',
    name,
    unit: 'kg',
    price: '4.50',
    available,
    soldOut: Number(available) <= 0,
    ...over,
});
// Stock list (`/stock`): `default` has offers and a sold-out one; `empty` none; `long-content` long names and a seven-digit price.
const stockProps = () => {
    if (state === 'empty') return { products: [] };
    if (state === 'long-content')
        return {
            products: [
                stockOffer('stock-1', longName, '1234567.500', {
                    producerName: longName,
                    price: '1234567.89',
                }),
                stockOffer('stock-2', longName, '0.000'),
            ],
        };
    return {
        products: [
            stockOffer('stock-1', 'Mongetes', '12.000'),
            stockOffer('stock-2', 'Oli d’oliva', '0.000', { unit: 'l' }),
            stockOffer('stock-3', 'Llenties', '3.500'),
        ],
    };
};

const pageProps: Record<string, Record<string, unknown>> = {
    'Orders/Index': { cycles: [] },
    'Member/Baskets/Index': basketListProps(),
    'Member/Baskets/Show': basketProps(),
    'Stock/Index': stockProps(),
    'Wallet/Statement': statementProps(),
    // Unmigrated page of a later checkpoint (C4): the shell must go back to the old identity on it and return (S26).
    'settings/Profile': { verificationLinkSent: false },
};
const urls: Record<string, string> = {
    'Orders/Index': '/orders',
    'Member/Baskets/Index': '/baskets',
    'Member/Baskets/Show': '/baskets/basket-1',
    'Stock/Index': '/stock',
    'Wallet/Statement': walletUrl,
    'settings/Profile': '/settings/profile',
};
const roleAreas = [
    { role: 'member', label: 'Llar', href: '/orders' },
    { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
    { role: 'finance', label: 'Economia', href: '/finance/baskets' },
].filter((area) => roles.includes(area.role));
const shared = {
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
    roleAreas,
    sidebarOpen: true,
};
const buildPage = (name: string) => ({
    component: name,
    url: urls[name] ?? '/',
    version: 'rest-c3-landing',
    props: { ...shared, ...pageProps[name] },
});
const page = buildPage(pageName);
const pagesByPath = Object.fromEntries(
    Object.entries(urls).map(([name, path]) => [path, name]),
);

// Request spy: the requests the real router sends, so busy and error states are proven to come from them.
const requests: string[] = [];
const navigation: string[] = [];
const invalidErrors = {
    amount: 'El camp import és obligatori.',
    note: 'El camp nota és obligatori.',
    source_movement_id: 'El camp ingrés original és obligatori.',
};
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
            if (state === 'busy' || state === 'validated-busy')
                return new Promise(() => {});
            return reply({
                ...page,
                props: {
                    ...page.props,
                    errors: state.startsWith('invalid')
                        ? invalidErrors
                        : {
                              domain:
                                  pageName === 'Member/Baskets/Show'
                                      ? 'Només es poden recollir cistelles validades.'
                                      : 'No s’ha pogut registrar l’ingrés.',
                          },
                },
            });
        }
        navigation.push(`get ${url.pathname}`);
        const target = pagesByPath[url.pathname];
        return reply(
            target ? buildPage(target) : { ...page, url: url.pathname },
        );
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
const set = (key: string, value: string) =>
    document.body.setAttribute(`data-ev-${key}`, value);
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

// The identity a page shows: the colours its tokens resolve to inside the page, so a green page and a blue one differ.
const recordIdentity = () => {
    const probe = document.createElement('span');
    probe.style.color = 'var(--primary)';
    probe.style.backgroundColor = 'var(--background)';
    document.querySelector('#main-content')!.append(probe);
    const resolved = getComputedStyle(probe);
    set('primary', resolved.color);
    set('background', resolved.backgroundColor);
    probe.remove();
};

const visible = (element: HTMLElement) => element.getClientRects().length > 0;
const heading = () => document.querySelector('#main-content h1')?.textContent;
const visit = async (path: string, title: string) => {
    router.visit(path);
    await waitFor(() => (heading() === title ? document.body : null));
    await sleep(150);
};
// What the shell shows on the page the user is on: the landing class and the primary the tokens resolve to.
const snapshot = (label: string) => {
    set(
        `${label}-shell-landing`,
        String(!!document.querySelector('.reference-style.landing')),
    );
    const probe = document.createElement('span');
    probe.style.color = 'var(--primary)';
    document.querySelector('#main-content')!.append(probe);
    set(`${label}-primary`, getComputedStyle(probe).color);
    probe.remove();
};
// S26 start: the statement (landing) -> Profile (unmigrated, keeps its own look) -> the statement again, for each audience.
const runNavigation = async () => {
    const title = heading() ?? '';
    snapshot('s1');
    await visit('/settings/profile', 'Configuració del perfil');
    snapshot('s2');
    await visit(walletUrl, title);
    snapshot('s3');
    set('nav-requests', navigation.join(','));
};

// S23: the member journey Orders -> baskets -> a basket -> the statement -> stock; each step reads the landing shell and its primary.
const runJourney = async () => {
    snapshot('j0');
    const steps = ['/baskets', '/baskets/basket-1', '/wallet', '/stock'];
    for (const [index, path] of steps.entries()) {
        const previous = heading();
        router.visit(path);
        await waitFor(() =>
            heading() && heading() !== previous ? document.body : null,
        );
        await sleep(150);
        snapshot(`j${index + 1}`);
        set(`j${index + 1}-title`, heading() ?? '');
    }
    set('nav-requests', navigation.join(','));
};

// The member's only write action: the real button sends the real PUT, so busy and the server error come from the request.
const runCollect = async () => {
    document
        .querySelector<HTMLElement>('[data-test="collect-basket"]')!
        .click();
    await waitFor(() => (requests.length ? document.body : null));
    if (state === 'validated-error')
        await waitFor(() =>
            document.querySelector('[data-test="domain-error"] p'),
        );
    await sleep(400);
    set('requests', requests.join(' ; '));
};

// Submits the top-up form (or, for `invalid-correction`, the correction form) so busy, error and invalid come from the request the page sends.
const fill = (input: HTMLInputElement, value: string) => {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
};
const runFormRequest = async () => {
    if (state === 'invalid-correction') {
        // The receipt is chosen through the real select, then the correction form is submitted.
        const { popup } = await openSelect();
        key(popup.querySelector('[data-slot="select-item"]')!, 'Enter');
        await closedPopup();
        fill(
            await waitFor(() =>
                document.querySelector<HTMLInputElement>('#correction-amount'),
            ),
            '-5',
        );
        fill(
            document.querySelector<HTMLInputElement>('#correction-note')!,
            'x',
        );
        document
            .querySelector<HTMLElement>('[data-test="record-correction"]')!
            .click();
    } else {
        fill(
            await waitFor(() =>
                document.querySelector<HTMLInputElement>('#top-up-amount'),
            ),
            '5',
        );
        document
            .querySelector<HTMLElement>('[data-test="record-top-up"]')!
            .click();
    }
    await waitFor(() => (requests.length ? document.body : null));
    if (state !== 'busy')
        await waitFor(() =>
            document.querySelector(
                '#main-content .text-destructive:not(:empty)',
            ),
        );
    await sleep(400);
    set('requests', requests.join(' ; '));
};

const popupSelector = '[data-slot="select-content"][data-state="open"]';
const firstTrigger = () =>
    [
        ...document.querySelectorAll<HTMLElement>(
            '#main-content [data-slot="select-trigger"]',
        ),
    ].find(visible);
const recordPopup = (popup: HTMLElement) => {
    const surface = getComputedStyle(popup);
    set('popup-carries-landing', String(popup.classList.contains('landing')));
    set('popup-bg', surface.backgroundColor);
    set('popup-fg', surface.color);
    set('popup-border', surface.borderTopColor);
    // The popup is portalled outside the measured root, so the runner never bounds it: record it here.
    const box = popup.getBoundingClientRect();
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
const closedPopup = () =>
    waitFor(() =>
        document.querySelector(popupSelector) ? null : document.body,
    );
// Keyboard model: Enter opens, ArrowDown moves, Enter chooses, Escape closes and focus returns.
const runSelectKeyboard = async () => {
    const { trigger } = await openSelect();
    // The key goes to the focused item and bubbles to the popup, as from a keyboard.
    key(document.activeElement ?? trigger, 'ArrowDown');
    await sleep(150);
    const active = document.activeElement as HTMLElement;
    set('arrowed', active.textContent?.trim() ?? 'none');
    key(active, 'Enter');
    await closedPopup();
    await sleep(200);
    const chosen = trigger.textContent?.trim() ?? '';
    set('chosen', chosen);
    set('chosen-matches', String(chosen === active.textContent?.trim()));
    set(
        'focus-returned-after-choice',
        String(document.activeElement === trigger),
    );
    const reopened = await openSelect();
    key(reopened.popup, 'Escape');
    await closedPopup();
    await sleep(200);
    set('escape-closed', 'true');
    set(
        'focus-returned-after-escape',
        String(document.activeElement === trigger),
    );
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
        if (state === 'navigate') await runNavigation();
        if (state === 'journey') await runJourney();
        if (['validated-busy', 'validated-error'].includes(state))
            await runCollect();
        else if (
            ['busy', 'error', 'invalid', 'invalid-correction'].includes(state)
        )
            await runFormRequest();
        if (state === 'select') recordPopup((await openSelect()).popup);
        if (state === 'select-keyboard') await runSelectKeyboard();
    } catch (error) {
        set('error', String(error));
    }
    recordIdentity();
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

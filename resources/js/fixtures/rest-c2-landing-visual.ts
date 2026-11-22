import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

/*
 * Deterministic review fixture for checkpoint C2 (spec 005, slices 3-7): manager administration pages in the
 * landing identity. Only the HTTP client is mocked: the real Inertia router, layouts, pages and portals run.
 * It proves presentation, never persistence or authorization. Each C2 task adds its pages and states here.
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Manage/Roles/Index';
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
const longEmail =
    'cooperativa.agricola.vall.hortes.ganxet.tradicional@exemple-molt-llarg.test';

// Role groups (Manage/Roles/Index). `default` shows a held role, a deactivated and a pending holder, a refused
// removal with its reason, and a role nobody holds ("role with no holder", R22).
const holder = (
    accountId: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    accountId,
    name,
    email: `${accountId}@example.test`,
    status: 'active',
    isSelf: false,
    canRemove: true,
    removeBlockedReason: null,
    ...over,
});
const roleGroups = () => [
    {
        role: 'manager',
        holders: [
            holder('maria', 'Maria Soler', { isSelf: true }),
            holder('pau', 'Pau Vidal', {
                canRemove: false,
                removeBlockedReason:
                    'Cal que quedi com a mínim un gestor actiu.',
            }),
        ],
    },
    {
        role: 'finance',
        holders: [
            holder(
                'anna',
                state === 'long-content' ? longName : 'Anna Puig',
                state === 'long-content' ? { email: longEmail } : {},
            ),
            holder('joan', 'Joan Ferrer', { status: 'deactivated' }),
            holder('laia', 'Laia Roca', { status: 'invitation_pending' }),
        ],
    },
    { role: 'catalogue', holders: [] },
    { role: 'receiver', holders: [holder('biel', 'Biel Serra')] },
];

// Accounts (Manage/Accounts/Index): `default` mixes an active member with a household, a deactivated account, an
// invitation still pending and an active account with no household; `empty` and `long-content` are the other states.
const account = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    accountId: id,
    name,
    email: `${id}@example.test`,
    roles: ['member'],
    householdId: 'household-1',
    householdName: 'Família Soler',
    active: true,
    invitationPending: false,
    ...over,
});
const accountRows = () => {
    if (state === 'empty') return [];
    if (state === 'long-content')
        return [
            account('account-1', longName, {
                email: longEmail,
                roles: ['member', 'manager', 'finance', 'catalogue'],
                householdId: 'household-3',
                householdName: longName,
            }),
        ];
    return [
        account('account-1', 'Pere Vidal'),
        account('account-2', 'Edu Puig', {
            roles: [],
            householdId: 'household-2',
            householdName: 'Família Puig',
            active: false,
        }),
        account('account-3', 'Marta Roca', {
            roles: ['member', 'finance'],
            householdId: null,
            householdName: null,
            invitationPending: true,
        }),
    ];
};
const households = [
    { id: 'household-1', name: 'Família Soler' },
    { id: 'household-2', name: 'Família Puig' },
    { id: 'household-3', name: longName },
].map((h) => ({ ...h, active: true, memberCount: 1 }));

// Households (Manage/Households/{Index,Show}): `default` mixes an active household, a deactivated one and a long name;
// the to-settle list shows an unpaid household (negative balance, open baskets) and a settled one (zero, none).
// Show's `inactive`, `settled` and `long-content` states cover reactivation, the empty settlement and long text.
const householdRows = [
    { id: 'household-1', name: 'Família Soler', memberCount: 3, active: true },
    { id: 'household-2', name: 'Família Puig', memberCount: 1, active: false },
    { id: 'household-3', name: longName, memberCount: 12, active: true },
];
const unpaidSettlement = {
    householdId: 'household-1',
    householdName: 'Família Soler',
    balanceCents: -1250,
    openBaskets: [
        { basketId: 'basket-1', date: '2026-11-12', status: 'draft' },
        { basketId: 'basket-2', date: '2026-11-19', status: 'validated' },
    ],
};
const settledSettlement = {
    householdId: 'household-2',
    householdName: 'Família Puig',
    balanceCents: 0,
    openBaskets: [],
};
const householdIndexProps = () => {
    if (state === 'empty') return { households: [], toSettle: [] };
    if (state === 'long-content')
        return {
            households: [householdRows[2]],
            toSettle: [
                {
                    ...unpaidSettlement,
                    householdId: 'household-3',
                    householdName: longName,
                },
            ],
        };
    return {
        households: householdRows,
        toSettle: [unpaidSettlement, settledSettlement],
    };
};
const member = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    accountId: id,
    name,
    email: `${id}@example.test`,
    invitationPending: false,
    invitationExpired: false,
    invitationExpiresAt: null,
    ...over,
});
const householdShowProps = () => {
    const long = state === 'long-content';
    return {
        household: {
            id: 'household-1',
            name: long ? longName : 'Família Soler',
            active: state !== 'inactive',
            members: [
                member(
                    'anna',
                    long ? longName : 'Anna Soler',
                    long ? { email: longEmail } : {},
                ),
                member('pau', 'Pau Soler', {
                    invitationPending: true,
                    invitationExpiresAt: '2026-12-01T10:00:00Z',
                }),
            ],
        },
        otherHouseholds: householdRows.filter((h) => h.id !== 'household-1'),
        deactivationWarning:
            state === 'settled' ? settledSettlement : unpaidSettlement,
    };
};

// Producers (Manage/Producers/Index): `default` mixes an active producer with an active account, one with a pending
// invitation, a deactivated one and one without an account (the invitation form); `empty` and `long-content` are the others.
const producer = (id: string, over: Record<string, unknown> = {}) => ({
    id,
    name: `Productor ${id}`,
    active: true,
    cutoffDaysBefore: 3,
    cutoffTime: '20:00',
    accountEmail: null,
    accountInvitationPending: false,
    ...over,
});
const producerRows = () => {
    if (state === 'empty') return [];
    if (state === 'long-content')
        return [
            producer('long', {
                name: longName,
                accountEmail: longEmail,
                accountInvitationPending: true,
            }),
            producer('long-open', { name: longName }),
        ];
    return [
        producer('hortalisses', {
            name: 'Hortalisses Can Roca',
            accountEmail: 'canroca@example.test',
        }),
        producer('formatges', {
            name: 'Formatges de la Vall',
            cutoffDaysBefore: 1,
            accountEmail: 'vall@example.test',
            accountInvitationPending: true,
        }),
        producer('ous', { name: 'Ous del Pla', active: false }),
        producer('mel', { name: 'Mel de Muntanya', cutoffDaysBefore: 5 }),
    ];
};

// Cycles (Manage/Cycles/Index and Show). `default` mixes an open cycle with an unsent and a sent producer order, a closed
// cycle with a pending collection and a cancelled one; `empty` has none and `long-content` long producer names.
const cycleParticipant = (
    producerId: string,
    producerName: string,
    over: Record<string, unknown> = {},
) => ({
    producerId,
    producerName,
    participating: true,
    cutoff: '2026-11-04T20:00:00',
    open: true,
    sent: false,
    canSendManually: true,
    ...over,
});
const cycleProducers = [
    { id: 'baker', name: 'Forn de Pa' },
    { id: 'dairy', name: 'Formatges de la Vall' },
    { id: 'honey', name: 'Mel de Muntanya' },
];
const cycleRows = () => {
    if (state === 'empty') return [];
    if (state === 'long-content')
        return [
            {
                id: 'cycle-1',
                pickupDate: '2026-11-07',
                cancelled: false,
                closed: false,
                settled: false,
                participants: [
                    cycleParticipant('long', longName, { cutoff: null }),
                ],
            },
        ];
    return [
        {
            id: 'cycle-1',
            pickupDate: '2026-11-07',
            cancelled: false,
            closed: false,
            settled: false,
            participants: [
                cycleParticipant('baker', 'Forn de Pa'),
                cycleParticipant('dairy', 'Formatges de la Vall', {
                    sent: true,
                    canSendManually: false,
                }),
            ],
        },
        {
            id: 'cycle-2',
            pickupDate: '2026-10-31',
            cancelled: false,
            closed: true,
            settled: true,
            participants: [
                cycleParticipant('baker', 'Forn de Pa', {
                    open: false,
                    sent: true,
                    canSendManually: false,
                }),
            ],
        },
        {
            id: 'cycle-3',
            pickupDate: '2026-10-24',
            cancelled: true,
            closed: false,
            settled: false,
            participants: [],
        },
    ];
};
const cycleIndexProps = () => ({
    cycles: cycleRows(),
    producers: state === 'empty' ? [] : cycleProducers,
    pickupWeekday: state === 'create-weekday' ? null : 4,
    pendingCollections: state === 'empty' ? {} : { 'cycle-2': 3 },
});
const cycleShowProps = () => ({
    summary: {
        cycleId: 'cycle-1',
        pickupDate: '2026-11-07',
        cancelled: state === 'cancelled',
        producers:
            state === 'empty' || state === 'cancelled'
                ? []
                : [
                      {
                          producerId: 'baker',
                          producerName: 'Forn de Pa',
                          products: [
                              {
                                  productId: 'bread',
                                  name:
                                      state === 'long-content'
                                          ? longName
                                          : 'Pa de pagès',
                                  unit: 'unitat',
                                  quantity: '7.00',
                                  households: [
                                      {
                                          householdId: 'household-1',
                                          householdName: 'Família Soler',
                                          quantity: '4.00',
                                      },
                                      {
                                          householdId: 'household-2',
                                          householdName: 'Família Puig',
                                          quantity: '3.00',
                                      },
                                  ],
                              },
                          ],
                      },
                  ],
    },
    containers: {
        total: state === 'empty' ? 0 : 5,
        households:
            state === 'empty'
                ? []
                : [
                      {
                          householdId: 'household-1',
                          householdName: 'Família Soler',
                          count: 3,
                          balance: 1,
                      },
                      {
                          householdId: 'household-2',
                          householdName: 'Família Puig',
                          count: 2,
                          balance: 0,
                      },
                  ],
    },
    pendingCollections:
        state === 'empty'
            ? { count: 0, href: null }
            : { count: 3, href: '/finance/baskets?pending=1&cycle=cycle-1' },
});

// Stock (Manage/Stock/Movements and the StockEditDialog opened from Manage/Stock/Index). `default` is a short history,
// `paged` the second page of 51 movements (the slice 2 seeder size), `long-content` seven-figure quantities and long names.
const stockProduct = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    id,
    producerId: 'producer-1',
    name,
    unit: 'pot',
    price: '4.50',
    available: '12.000',
    active: true,
    ...over,
});
const stockName = state === 'long-content' ? longName : 'Mel de farigola';
const stockRows = () => [
    stockProduct('stock-1', stockName),
    stockProduct('stock-2', 'Melmelada de figa', {
        active: false,
        available: '0.000',
        price: '3.80',
    }),
    stockProduct('stock-3', 'Oli d’oliva verge extra', {
        unit: 'ampolla',
        price: '9.90',
        available: '40.500',
    }),
];
const movementLine = (
    id: string,
    type: string,
    quantity: string,
    balanceAfter: string,
    over: Record<string, unknown> = {},
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
const movementHistory = () => {
    const empty = {
        lines: [],
        total: 0,
        page: 1,
        prevUrl: null,
        nextUrl: null,
    };
    if (state === 'empty') return empty;
    if (state === 'long-content')
        return {
            ...empty,
            total: 2,
            lines: [
                movementLine(
                    'line-1',
                    'adjustment',
                    '-1234567.000',
                    '1234567.500',
                    {
                        note: `Ajust per inventari complet del magatzem de ${longName}`,
                        authorName: longName,
                    },
                ),
                movementLine('line-2', 'entry', '1234567.000', '2469134.500', {
                    basketId: 'basket-1',
                }),
            ],
        };
    if (state === 'paged')
        return {
            lines: Array.from({ length: 10 }, (_, index) =>
                movementLine(
                    `line-${index}`,
                    'sale',
                    '-1.000',
                    `${40 - index}.000`,
                    { basketId: `basket-${index}` },
                ),
            ),
            total: 51,
            page: 2,
            prevUrl: '/manage/stock/stock-1/movements?page=1',
            nextUrl: '/manage/stock/stock-1/movements?page=3',
        };
    return {
        ...empty,
        total: 4,
        lines: [
            movementLine('line-1', 'sale', '-3.000', '12.000', {
                basketId: 'basket-1',
            }),
            movementLine('line-2', 'entry', '10.500', '15.000', {
                note: 'Recepció setmanal',
            }),
            movementLine('line-3', 'adjustment', '-1.250', '4.500', {
                note: 'Pot trencat',
                pickupDate: null,
                cycleId: null,
            }),
            movementLine('line-4', 'correction', '2.000', '5.750'),
        ],
    };
};
const stockMovementsProps = () => ({
    product: stockProduct('stock-1', stockName, {
        available: state === 'long-content' ? '1234567.500' : '12.000',
    }),
    cycles: [
        { id: 'cycle-1', pickupDate: '2026-11-14' },
        { id: 'cycle-2', pickupDate: '2026-11-07' },
    ],
    history: movementHistory(),
});

const pageProps: Record<string, Record<string, unknown>> = {
    'Manage/Stock/Index': { products: stockRows() },
    'Manage/Stock/Movements': stockMovementsProps(),
    'Manage/Cycles/Index': cycleIndexProps(),
    'Manage/Cycles/Show': cycleShowProps(),
    'Manage/Producers/Index': { producers: producerRows() },
    // `empty` is the weekday not yet set (the notice and the placeholder).
    'Manage/Settings': { pickupWeekday: state === 'empty' ? null : 4 },
    'Manage/Households/Index': householdIndexProps(),
    'Manage/Households/Show': householdShowProps(),
    'Manage/Roles/Index': { groups: roleGroups() },
    'Manage/Accounts/Index': { accounts: accountRows(), households },
    // Unmigrated control page (settings/Profile stays old-theme until C4).
    'settings/Profile': { verificationLinkSent: false },
};
const urls: Record<string, string> = {
    'Manage/Roles/Index': '/manage/roles',
    'Manage/Accounts/Index': '/manage/accounts',
    'Manage/Households/Index': '/manage/households',
    'Manage/Households/Show': '/manage/households/household-1',
    'Manage/Producers/Index': '/manage/producers',
    'Manage/Settings': '/manage/settings',
    'Manage/Cycles/Index': '/manage/cycles',
    'Manage/Cycles/Show': '/manage/cycles/cycle-1',
    'Manage/Stock/Index': '/manage/stock',
    'Manage/Stock/Movements': '/manage/stock/stock-1/movements',
    'settings/Profile': '/settings/profile',
};
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
    roleAreas: [
        { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
    ],
    sidebarOpen: true,
};
const buildPage = (name: string) => ({
    component: name,
    url: urls[name] ?? '/',
    version: 'rest-c2-landing',
    props: { ...shared, ...pageProps[name] },
});
const page = buildPage(pageName);
const pagesByPath = Object.fromEntries(
    Object.entries(urls).map(([name, path]) => [path, name]),
);

const domainErrors: Record<string, string> = {
    default: 'No s’ha pogut canviar el rol.',
    'Manage/Producers/Index': 'No s’ha pogut crear el productor.',
    'Manage/Settings': 'No s’ha pogut desar el dia de recollida.',
    'Manage/Cycles/Index': 'No s’ha pogut obrir el cicle.',
    'Manage/Stock/Movements': 'No s’ha pogut registrar l’entrada.',
};
const invalidErrors: Record<string, Record<string, string>> = {
    default: {
        household_id: 'El camp unitat de convivència és obligatori.',
    },
    'Manage/Producers/Index': { name: 'El camp nom és obligatori.' },
    'Manage/Settings': {
        pickup_weekday: 'El dia de recollida no és vàlid.',
    },
    'Manage/Cycles/Index': { pickup_date: 'El camp data és obligatori.' },
    'Manage/Stock/Movements': { quantity: 'El camp quantitat és obligatori.' },
};

// Request spy: the requests the real router sends, so busy and error states are proven to come from them.
const requests: string[] = [];
const navigation: string[] = [];
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
                            ? (invalidErrors[pageName] ?? invalidErrors.default)
                            : {
                                  domain:
                                      domainErrors[pageName] ??
                                      domainErrors.default,
                              },
                },
            });
        }
        // Navigation states: a GET answers with the page the path belongs to, as the real router would.
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

const dialogOpen = () =>
    document.querySelector<HTMLElement>('[role="dialog"][data-state="open"]');
const visible = (element: HTMLElement) => element.getClientRects().length > 0;
const removeButton = (label: string) =>
    waitFor(() =>
        document.querySelector<HTMLElement>(`button[aria-label="${label}"]`),
    );
const confirmButton = (dialog: HTMLElement) =>
    [...dialog.querySelectorAll('button')].find(
        (button) => button.textContent?.trim() === 'Retira',
    )!;
// Opens by keyboard (a click on the focused button is what Enter/Space produce), traps focus, and Escape
// closes it with no request and returns focus to the button that opened it (S22).
const runRemoveDialog = async (label: string) => {
    const opener = await removeButton(label);
    opener.focus();
    opener.click();
    const dialog = await waitFor(dialogOpen);
    await settle();
    await sleep(200);
    set('dialog-landing', String(dialog.classList.contains('landing')));
    set('dialog-bg', getComputedStyle(dialog).backgroundColor);
    set('dialog-focus-inside', String(dialog.contains(document.activeElement)));
    set('dialog-text', dialog.querySelector('p')?.textContent?.trim() ?? '');
    const confirmStyle = getComputedStyle(confirmButton(dialog));
    set('dialog-destructive-bg', confirmStyle.backgroundColor);
    set(
        'dialog-destructive-contrast',
        contrast(confirmStyle.color, confirmStyle.backgroundColor).toFixed(2),
    );
    key(dialog, 'Escape');
    await waitFor(() => (dialogOpen() ? null : document.body));
    await sleep(300);
    set('dialog-escape-closed', 'true');
    set('dialog-requests', String(requests.length));
    set('dialog-focus-returned', String(document.activeElement === opener));
};

// Waits for the request the page sent and, when an answer is expected, for the error text it renders (InputError always
// renders its element, so the text must be non-empty).
const awaitRequestOutcome = async (expectError: boolean) => {
    await waitFor(() => (requests.length ? document.body : null));
    if (expectError)
        await waitFor(() =>
            document.querySelector(
                '#main-content .text-destructive:not(:empty)',
            ),
        );
    await sleep(400);
    set('requests', requests.join(' ; '));
};

// Confirms a removal through the real dialog, so busy and error states come from the request the page sends.
const runRoleRequest = async () => {
    (await removeButton('Retira el rol «Economia» a Anna Puig')).click();
    confirmButton(await waitFor(dialogOpen)).click();
    await awaitRequestOutcome(state === 'error');
};
// Submits the household move of the first account (its select has a default), proving busy and error from the request.
const runAccountRequest = async () => {
    const move = await waitFor(() =>
        [
            ...document.querySelectorAll<HTMLElement>(
                '#main-content button[type="submit"]',
            ),
        ].find((button) => button.textContent?.trim() === 'Mou'),
    );
    move.click();
    await awaitRequestOutcome(state !== 'busy');
};

// Settlement details (S22): the open-basket links are native anchors. Records how many are visible, that focus() lands
// on the first one, and its height; the manifest's focus list reaches them with real Tab presses.
const runSettlementKeyboard = async () => {
    const links = [
        ...document.querySelectorAll<HTMLAnchorElement>(
            '#main-content dl a[href]',
        ),
    ].filter(visible);
    set('settlement-links', String(links.length));
    links[0]?.focus();
    set('settlement-focused', String(document.activeElement === links[0]));
    set(
        'settlement-link-height',
        String(links[0]?.getBoundingClientRect().height),
    );
};

// Submits the new-producer form with a name, proving busy and error from the request the page sends.
const runProducerRequest = async () => {
    const name = await waitFor(() =>
        document.querySelector<HTMLInputElement>('#producer-name'),
    );
    name.value = 'Productor nou';
    document
        .querySelector<HTMLElement>('[data-test="create-producer"]')!
        .click();
    await awaitRequestOutcome(state !== 'busy');
};
// Saves the pickup weekday (the select has a default), proving busy and error from the request.
const runSettingsRequest = async () => {
    (
        await waitFor(() =>
            document.querySelector<HTMLElement>(
                '[data-test="save-pickup-weekday"]',
            ),
        )
    ).click();
    await awaitRequestOutcome(state !== 'busy');
};

// Cycle dialogs (S22): opened from the focused trigger as Enter/Space would. The dialog is modal with focus inside and its
// surface is recorded; `close` also sends a native close request (Escape) and records that nothing was sent and that
// focus returned to the opener. Without `close` it stays open so the runner measures and tabs inside it.
const cycleDialog = () =>
    document.querySelector<HTMLDialogElement>('dialog.manager-dialog[open]');
const runCycleDialog = async (openerSelector: string, close: boolean) => {
    const opener = await waitFor(() =>
        document.querySelector<HTMLElement>(openerSelector),
    );
    opener.focus();
    opener.click();
    const dialog = await waitFor(cycleDialog);
    await settle();
    await sleep(350);
    const style = getComputedStyle(dialog);
    set('dialog-modal', String(dialog.matches(':modal')));
    set('dialog-bg', style.backgroundColor);
    set('dialog-ink', style.color);
    set('dialog-border', style.borderTopColor);
    set('dialog-focus-inside', String(dialog.contains(document.activeElement)));
    // A modal dialog makes the page behind it inert: the opener cannot take focus while the dialog is open (the focus trap).
    const inside = document.activeElement;
    opener.focus();
    set('dialog-outside-inert', String(document.activeElement !== opener));
    (inside as HTMLElement | null)?.focus();
    set('dialog-requests', String(requests.length));
    if (!close) return;
    dialog.requestClose();
    await waitFor(() => (cycleDialog() ? null : document.body));
    await sleep(200);
    set('dialog-escape-closed', 'true');
    set('dialog-requests', String(requests.length));
    set('dialog-focus-returned', String(document.activeElement === opener));
};
// Submits the create dialog with a date, so busy (button disabled) and error come from the request the page sends.
const runCycleRequest = async () => {
    (
        await waitFor(() =>
            document.querySelector<HTMLElement>(
                '[data-test="open-create-cycle"]',
            ),
        )
    ).click();
    const date = await waitFor(() =>
        document.querySelector<HTMLInputElement>('#pickup-date'),
    );
    date.value = '2026-11-14';
    document.querySelector<HTMLElement>('[data-test="open-cycle"]')!.click();
    await awaitRequestOutcome(state !== 'busy');
};

// Stock edit dialog (S22): opens from the product row, then `status` opens the status section and asks to deactivate, so the
// danger confirmation is measured, and `error` submits a count the field pattern lets through but the page refuses, so the text error is measured.
const runStockDialog = async () => {
    // The row button (narrow) and the table's pencil button (wide) both open it; the layout shows one of them.
    const opener = await waitFor(() =>
        [
            ...document.querySelectorAll<HTMLElement>(
                'button[aria-label="Edita Mel de farigola"], button[aria-label="Edita: Mel de farigola"]',
            ),
        ].find(visible),
    );
    opener.dataset.visualOpener = 'true';
    await runCycleDialog('[data-visual-opener]', state.endsWith('-escape'));
    if (state === 'edit-status') {
        const dialog = cycleDialog()!;
        dialog.querySelector<HTMLElement>('details summary')!.click();
        const deactivate = await waitFor(() =>
            [...dialog.querySelectorAll<HTMLElement>('details button')].find(
                visible,
            ),
        );
        deactivate.click();
        const confirm = await waitFor(() =>
            dialog.querySelector<HTMLElement>(
                '[data-test="confirm-status-stock-1"]',
            ),
        );
        await settle();
        // The confirmation is the destructive action (S22): it must resolve to --destructive, not to the primary.
        set('dialog-destructive-bg', getComputedStyle(confirm).backgroundColor);
    }
    if (state === 'edit-error') {
        const dialog = cycleDialog()!;
        const count =
            dialog.querySelector<HTMLInputElement>('#stock-available')!;
        count.value = '12345678';
        count.dispatchEvent(new Event('input', { bubbles: true }));
        // The input's passive v-model reaches the page on the next tick; saving before it would read the old count.
        await sleep(100);
        dialog
            .querySelector<HTMLElement>('[data-test="save-available"]')!
            .click();
        await waitFor(() =>
            dialog.querySelector('.text-destructive:not(:empty)'),
        );
        await settle();
    }
};
// Records an entry through the real form, so busy and error come from the request the movements page sends.
const runStockEntryRequest = async () => {
    const quantity = await waitFor(() =>
        document.querySelector<HTMLInputElement>('#entry-quantity'),
    );
    quantity.value = '5';
    document.querySelector<HTMLElement>('[data-test="record-entry"]')!.click();
    await awaitRequestOutcome(state !== 'busy');
};

const requestRunners: Record<string, () => Promise<void>> = {
    'Manage/Stock/Movements': runStockEntryRequest,
    'Manage/Cycles/Index': runCycleRequest,
    'Manage/Roles/Index': runRoleRequest,
    'Manage/Producers/Index': runProducerRequest,
    'Manage/Settings': runSettingsRequest,
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

const heading = () => document.querySelector('#main-content h1')?.textContent;
const visit = async (path: string, title: string) => {
    router.visit(path);
    await waitFor(() => (heading() === title ? document.body : null));
    await sleep(150);
};
const sheetSelector = '[data-sidebar="sidebar"][data-mobile="true"]';
const openSheet = async () => {
    if (!document.querySelector(sheetSelector))
        (
            await waitFor(() =>
                document.querySelector<HTMLElement>(
                    'button[data-sidebar="trigger"]',
                ),
            )
        ).click();
    const sheet = await waitFor(() =>
        document.querySelector<HTMLElement>(sheetSelector),
    );
    await sleep(600);
    return sheet;
};
// What the sheet and the shell show on the page the manager is on: the landing class and the sheet's own surface.
const snapshot = async (label: string) => {
    const sheet = await openSheet();
    await settle();
    set(
        `${label}-shell-landing`,
        String(!!document.querySelector('.reference-style.landing')),
    );
    set(`${label}-sheet-landing`, String(sheet.classList.contains('landing')));
    set(`${label}-sheet-bg`, getComputedStyle(sheet).backgroundColor);
};
// S26 start: Roles (landing) -> Profile settings (unmigrated, keeps its own look) -> Roles again, sheet open each time.
const runNavSheet = async () => {
    await snapshot('s1');
    await visit('/settings/profile', 'Configuració del perfil');
    await snapshot('s2');
    await visit('/manage/roles', 'Rols');
    await snapshot('s3');
    set('nav-requests', navigation.join(','));
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
    set('menu-bg', getComputedStyle(menu).backgroundColor);
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
        if (state === 'remove-dialog')
            await runRemoveDialog('Retira el rol «Economia» a Anna Puig');
        if (state === 'own-manager-dialog')
            await runRemoveDialog('Retira el rol «Gestor» a Maria Soler');
        if (state.startsWith('create-'))
            await runCycleDialog(
                '[data-test="open-create-cycle"]',
                state.endsWith('-escape'),
            );
        if (state.startsWith('manage-'))
            await runCycleDialog(
                `[data-test="${state.startsWith('manage-producer') ? 'manage-cycle-1-baker' : 'manage-cycle-1'}"]`,
                state.endsWith('-escape'),
            );
        if (state.startsWith('edit-')) await runStockDialog();
        if (state === 'nav-sheet') await runNavSheet();
        if (state === 'account-menu') await runAccountMenu();
        if (state === 'select') recordPopup((await openSelect()).popup);
        if (state === 'select-keyboard') await runSelectKeyboard();
        if (['busy', 'error', 'invalid'].includes(state))
            await (requestRunners[pageName] ?? runAccountRequest)();
        if (state === 'settlement-keyboard') await runSettlementKeyboard();
    } catch (error) {
        set('error', String(error));
    }
    recordIdentity();
    document.body.dataset.requests = String(requests.length);
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

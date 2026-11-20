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

const pageProps: Record<string, Record<string, unknown>> = {
    'Manage/Roles/Index': { groups: roleGroups() },
    'Manage/Accounts/Index': { accounts: accountRows(), households },
    // Unmigrated leakage probe: Wallet/Statement stays old-theme until C3 and carries the select control.
    'Wallet/Statement': {
        wallet: {
            balance: '35.00',
            movements: [
                {
                    id: 'movement-1',
                    type: 'top_up',
                    amount: '20.00',
                    occurredAt: '2026-10-30 09:00',
                    note: 'Ingrés',
                    cycleId: null,
                    basketId: null,
                    sourceMovementId: null,
                    authorName: 'Marta Soler',
                },
                {
                    id: 'movement-2',
                    type: 'top_up',
                    amount: '15.00',
                    occurredAt: '2026-10-29 10:00',
                    note: 'Ingrés',
                    cycleId: null,
                    basketId: null,
                    sourceMovementId: null,
                    authorName: 'Marta Soler',
                },
            ],
        },
        household: { id: 'household-1', name: 'Família Soler' },
        canManageReceipts: true,
        receiptAction: '/finance/households/household-1/wallet/receipts',
        correctionAction: '/finance/households/household-1/wallet/corrections',
        correctionId: 'correction-1',
    },
};
const urls: Record<string, string> = {
    'Manage/Roles/Index': '/manage/roles',
    'Manage/Accounts/Index': '/manage/accounts',
    'Wallet/Statement': '/finance/households/household-1/wallet',
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
                            ? {
                                  household_id:
                                      'El camp unitat de convivència és obligatori.',
                              }
                            : { domain: 'No s’ha pogut canviar el rol.' },
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

// Confirms a removal through the real dialog, so busy and error states come from the request the page sends.
const runRoleRequest = async () => {
    (await removeButton('Retira el rol «Economia» a Anna Puig')).click();
    confirmButton(await waitFor(dialogOpen)).click();
    await waitFor(() => (requests.length ? document.body : null));
    if (state === 'error')
        await waitFor(() =>
            document.querySelector('#main-content .text-destructive'),
        );
    await sleep(400);
    set('requests', requests.join(' ; '));
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
    await waitFor(() => (requests.length ? document.body : null));
    if (state !== 'busy')
        await waitFor(() =>
            document.querySelector('#main-content .text-destructive'),
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
// S26 start: Roles (landing) -> Wallet statement (unmigrated, keeps its own look) -> Roles again, sheet open each time.
const runNavSheet = async () => {
    await snapshot('s1');
    await visit(
        '/finance/households/household-1/wallet',
        'Moneder de Família Soler',
    );
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
        if (state === 'nav-sheet') await runNavSheet();
        if (state === 'account-menu') await runAccountMenu();
        if (state === 'select') recordPopup((await openSelect()).popup);
        if (state === 'select-keyboard') await runSelectKeyboard();
        if (['busy', 'error', 'invalid'].includes(state))
            await (pageName === 'Manage/Roles/Index'
                ? runRoleRequest()
                : runAccountRequest());
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

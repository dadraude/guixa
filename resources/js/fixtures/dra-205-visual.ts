import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { installTranslations } from '@/lib/i18n';
import { trans } from 'laravel-vue-i18n';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { resolveVisualIdentity } from './reference-gates';

const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Dashboard';
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
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const pickupDate = '2026-11-05';
const cycleId = 'cycle-1';
const producerId = 'producer-1';
const householdId = 'household-1';
const productId = 'product-1';
const stockId = 'stock-1';
const accountId = 'account-1';
const household = {
    id: householdId,
    name: 'La Figuera',
    active: true,
    memberCount: 1,
};
const member = {
    accountId,
    name: 'Maria Soler',
    email: 'maria@example.test',
    invitationPending: false,
    invitationExpiresAt: null,
    invitationExpired: false,
};
const producer = {
    id: producerId,
    name: 'Hort de la Vall',
    active: true,
    cutoffDaysBefore: 3,
    cutoffTime: '20:00',
    accountEmail: null,
    accountInvitationPending: false,
};
const product = {
    id: productId,
    name: 'Tomàquets',
    unit: 'kg',
    price: '3.20',
    variable: true,
    containersPerUnit: '1',
    active: true,
};
const stockProduct = {
    id: stockId,
    producerId,
    name: 'Arròs',
    unit: 'kg',
    price: '2.50',
    variable: false,
    containersPerUnit: null,
    active: true,
    available: '12',
};
const returnItem = {
    id: 'return-1',
    name: 'Pot de vidre',
    amount: '0.50',
    active: true,
    amountCents: 50,
};
const cycle = {
    id: cycleId,
    pickupDate,
    cancelled: false,
    closed: false,
    participants: [
        {
            producerId,
            producerName: producer.name,
            participating: true,
            cutoff: '2026-11-03T20:00:00+01:00',
            open: true,
        },
    ],
};
const settlement = {
    householdId,
    householdName: household.name,
    balanceCents: 2000,
    openBaskets: [{ basketId: 'basket-1', status: 'draft', date: pickupDate }],
};
const reception = {
    cycleId,
    producerId,
    producerName: producer.name,
    pickupDate,
    unorderedOptions: [],
    products: [
        {
            productId,
            name: product.name,
            unit: product.unit,
            variable: product.variable,
            nobodyOrdered: false,
            ordered: '2',
            received: '2',
            allocated: '2',
            delivered: '2',
            difference: '0',
            balanced: true,
            balancedForDistribution: true,
            hasManualAllocations: false,
            hasAdjustments: false,
            allocations: [
                {
                    householdId,
                    householdName: household.name,
                    ordered: '2',
                    allocated: '2',
                    byHand: false,
                    delivered: '2',
                    adjusted: false,
                },
            ],
        },
    ],
};
const order = {
    cycleId,
    pickupDate,
    cancelled: false,
    producers: [
        {
            producerId,
            producerName: producer.name,
            open: true,
            products: [product],
        },
    ],
    lines: [
        {
            productId,
            producerId,
            producerName: producer.name,
            name: product.name,
            unit: product.unit,
            price: product.price,
            variable: product.variable,
            quantity: '2',
            notDelivered: false,
        },
    ],
    basketId: null,
};

const pageProps: Record<string, Record<string, unknown>> = {
    Dashboard: {
        roles: ['manager', 'member', 'producer'],
        isManager: true,
        isMember: true,
        nextPickup: pickupDate,
        openCycles: 1,
        balance: 2000,
        opportunities: [],
        attention: [
            {
                key: 'collect_basket:cycle:visual-cycle',
                action: 'collect_basket',
                cycleId: 'visual-cycle',
                scheduledDate: pickupDate,
                count: 3,
                href: '/finance/baskets?pending=1',
                producerName: null,
                allocationDifferenceCount: 0,
            },
            {
                key: 'collect_basket:no-cycle',
                action: 'collect_basket',
                cycleId: null,
                scheduledDate: null,
                count: 1,
                href: '/baskets/visual-stock-basket',
                producerName: null,
                allocationDifferenceCount: 0,
            },
        ],
        managerSummary: {
            cycles: 1,
            activeCycles: 1,
            households: 1,
            activeHouseholds: 1,
            accounts: 1,
            pendingInvitations: 0,
            producers: 1,
            activeProducers: 1,
        },
    },
    'Manage/Accounts/Index': {
        accounts: [
            {
                accountId,
                name: member.name,
                email: member.email,
                roles: ['member'],
                householdId,
                householdName: household.name,
                active: true,
                invitationPending: false,
            },
        ],
        households: [household],
    },
    'Manage/Cycles/Index': {
        cycles: [cycle],
        producers: [{ id: producerId, name: producer.name }],
        pickupWeekday: 4,
        pendingCollections: { [cycleId]: 2 },
    },
    'Manage/Cycles/Show': {
        pendingCollections: {
            count: 2,
            href: `/finance/baskets?pending=1&cycle=${cycleId}`,
        },
        summary: {
            cycleId,
            pickupDate,
            cancelled: false,
            producers: [
                {
                    producerId,
                    producerName: producer.name,
                    products: [
                        {
                            productId,
                            name: product.name,
                            unit: product.unit,
                            quantity: '2',
                            households: [
                                {
                                    householdId,
                                    householdName: household.name,
                                    quantity: '2',
                                },
                            ],
                        },
                    ],
                },
            ],
        },
        containers: {
            total: 2,
            households: [
                {
                    householdId,
                    householdName: household.name,
                    count: 2,
                    balance: 3,
                },
            ],
        },
    },
    'Manage/Households/Index': {
        households: [household],
        toSettle: [settlement],
    },
    'Manage/Households/Show': {
        household: {
            id: householdId,
            name: household.name,
            active: true,
            members: [member],
        },
        otherHouseholds: [],
        deactivationWarning: settlement,
    },
    'Manage/Producers/Index': { producers: [producer] },
    'Receptions/Show': {
        reception,
        can: { edit: true, validate: true, distribute: false, reopen: false },
        distribution: {
            validated: false,
            distributed: false,
            settled: false,
            closed: false,
        },
        households: [household],
    },
    'Manage/ReturnItems/Edit': { item: returnItem },
    'Manage/ReturnItems/Index': { items: [returnItem] },
    'Manage/Settings': { pickupWeekday: 4 },
    'Manage/Stock/Create': { producer },
    'Manage/Stock/Edit': { product: stockProduct },
    'Manage/Stock/Index': { products: [stockProduct] },
    'Manage/Stock/Movements': {
        product: stockProduct,
        cycles: [{ id: cycleId, pickupDate }],
        history: { lines: [], total: 0, page: 1, prevUrl: null, nextUrl: null },
    },
    'Orders/Edit': { order, preview: { totalCents: 0, amountCents: {} } },
    'Orders/Index': {
        cycles: [
            { cycleId, pickupDate, open: true, cancelled: false, lineCount: 1 },
        ],
    },
    'Producer/Cutoff': { cutoff: { daysBefore: 3, time: '20:00' } },
    'Producer/CycleSummary': {
        summary: {
            cycleId,
            pickupDate,
            sent: true,
            products: [
                {
                    productId,
                    name: product.name,
                    unit: product.unit,
                    available: true,
                    quantity: '2',
                },
            ],
        },
    },
    'Producer/Products/Edit': { product },
    'Producer/Products/Index': { products: [product] },
    'Wallet/Statement': {
        wallet: {
            balance: '20.00',
            movements: [
                {
                    id: 'movement-1',
                    type: 'top_up',
                    amount: '20.00',
                    occurredAt: '2026-10-27 12:00',
                    note: null,
                    cycleId: null,
                    authorName: 'Maria Soler',
                },
                {
                    id: 'movement-2',
                    type: 'charge',
                    amount: '-25.00',
                    occurredAt: '2026-10-28 12:00',
                    note: null,
                    basketId: '019a0000-0000-7000-8000-000000000099',
                    cycleId: null,
                    authorName: 'Maria Soler',
                },
                {
                    id: 'movement-3',
                    type: 'credit',
                    amount: '25.00',
                    occurredAt: '2026-10-29 12:00',
                    note: 'Ajust',
                    cycleId: null,
                    authorName: 'Maria Soler',
                },
            ],
        },
        household: { id: householdId, name: household.name },
    },
    'auth/AcceptInvitation': {
        token: 'visual-token',
        passwordRules: 'minlength:12',
    },
    'auth/Login': { canResetPassword: true },
    'auth/ResetPassword': {
        token: 'visual-token',
        email: member.email,
        passwordRules: 'minlength:12',
    },
    'settings/Security': {
        passwordRules: 'minlength:12',
        canManagePasskeys: true,
        passkeys: [],
        canManageTwoFactor: true,
        requiresConfirmation: false,
        twoFactorEnabled: false,
    },
};

const authPage = pageName.startsWith('auth/');
const welcomePage = pageName === 'Welcome';
const fallbackPageUrl = welcomePage
    ? '/'
    : authPage
      ? `/${pageName.split('/').at(-1)?.toLowerCase()}`
      : pageName === 'Dashboard'
        ? '/dashboard'
        : `/${pageName
              .replace(/\/Index$/, '')
              .replace(/\/Show$/, '/item')
              .toLowerCase()}`;
const { role, pageUrl, welcomeAuthenticated } = resolveVisualIdentity(
    pageName,
    query,
    fallbackPageUrl,
);
const page = {
    component: pageName,
    url: pageUrl,
    version: 'dra-205',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user:
                authPage || (welcomePage && !welcomeAuthenticated)
                    ? null
                    : {
                          id: accountId,
                          name: member.name,
                          email: member.email,
                          avatar: '',
                          email_verified_at: '2026-10-01T12:00:00Z',
                          created_at: '2026-10-01T12:00:00Z',
                          updated_at: '2026-10-01T12:00:00Z',
                      },
            roles:
                authPage || (welcomePage && !welcomeAuthenticated)
                    ? []
                    : [role],
        },
        roleAreas:
            authPage || (welcomePage && !welcomeAuthenticated)
                ? []
                : [
                      {
                          role: 'manager',
                          label: 'Cooperative',
                          href: '/manage/cycles',
                      },
                      {
                          role: 'finance',
                          label: 'Finance',
                          href: '/finance/baskets',
                      },
                      { role: 'member', label: 'Member', href: '/orders' },
                      {
                          role: 'catalogue',
                          label: 'Catalogue',
                          href: '/catalogue/producers',
                      },
                      {
                          role: 'producer',
                          label: 'Producer',
                          href: '/producer/products',
                      },
                  ].filter((area) => area.role === role),
        sidebarOpen: true,
        ...pageProps[pageName],
    },
};

const fixtureErrors = {
    domain: 'No s’ha pogut desar el canvi. Torna-ho a provar.',
    name: 'El nom és obligatori.',
    email: 'Introdueix una adreça electrònica vàlida.',
    amount: 'Introdueix un import vàlid.',
    quantity: 'Introdueix una quantitat vàlida.',
    password: 'La contrasenya és obligatòria.',
    code: 'Introdueix un codi vàlid.',
    product_id: 'Selecciona un producte.',
    pickup_weekday: 'Selecciona un dia de recollida.',
    top_up: 'Introdueix un import vàlid.',
};
if (state === 'error' || state === 'disabled') {
    http.setClient({
        request: async () => {
            if (state === 'disabled') return new Promise(() => {});
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    url: window.location.pathname + window.location.search,
                    props: { ...page.props, errors: fixtureErrors },
                }),
                headers: { 'x-inertia': 'true' },
            };
        },
    });
}
if (
    [
        'delete-error',
        'delete-pending',
        'passkey-remove-error',
        'passkey-remove-pending',
    ].includes(state)
) {
    http.setClient({
        request: async () => {
            document.body.dataset.visualRequest = state.endsWith('pending')
                ? 'pending'
                : 'error';
            if (state.endsWith('pending')) return new Promise(() => {});
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    url: window.location.pathname + window.location.search,
                    props: {
                        ...page.props,
                        errors:
                            state === 'delete-error'
                                ? { password: 'La contrasenya és incorrecta.' }
                                : {
                                      domain: 'No s’ha pogut eliminar la clau d’accés.',
                                  },
                    },
                }),
                headers: { 'x-inertia': 'true' },
            };
        },
    });
}

const visualProps = page.props as Record<string, any>;
if (pageName === 'Dashboard') {
    visualProps.roles = [role];
    visualProps.isManager = role === 'manager';
    visualProps.isMember = role === 'member';
    visualProps.managerSummary =
        role === 'manager' ? visualProps.managerSummary : null;
    visualProps.attention =
        role === 'member'
            ? visualProps.attention.map((group: Record<string, unknown>) => ({
                  ...group,
                  count: 1,
                  href: '/baskets/visual-basket',
              }))
            : role === 'manager' || role === 'finance'
              ? visualProps.attention
              : [];
    visualProps.nextPickup = role === 'member' ? visualProps.nextPickup : null;
    visualProps.openCycles = role === 'member' ? visualProps.openCycles : 0;
    visualProps.balance = role === 'member' ? visualProps.balance : null;
    if (state === 'baskets') {
        // The server's order: dated groups first, then undated; validation before collection within a date.
        const [datedCollection, ...undatedCollection] = visualProps.attention;
        visualProps.attention = [
            {
                key: 'validate_basket:cycle:visual-cycle',
                action: 'validate_basket',
                cycleId: 'visual-cycle',
                scheduledDate: pickupDate,
                count: 2,
                href: '/finance/baskets',
                producerName: null,
                allocationDifferenceCount: 0,
            },
            datedCollection,
            {
                key: 'validate_basket:no-cycle',
                action: 'validate_basket',
                cycleId: null,
                scheduledDate: null,
                count: 1,
                href: '/finance/baskets/visual-stock-draft',
                producerName: null,
                allocationDifferenceCount: 0,
            },
            ...undatedCollection,
        ];
    }
    if (state === 'opportunities' || state === 'opportunities-repeated') {
        // Below the attention list and never counted as pending: ordering with cutoffs, a sent-order review, product maintenance.
        const opportunities: Record<string, Record<string, unknown>> = {
            member: {
                key: 'member_order:cycle:visual-cycle',
                kind: 'member_order',
                scheduledDate: pickupDate,
                href: '/cycles/visual-cycle/order',
                cutoffs: [
                    {
                        producerName: 'Forn del Barri',
                        closesAt: '2026-11-02 20:00',
                    },
                    {
                        producerName: 'La Figuera del barri de la Vall',
                        closesAt: '2026-11-03 12:00',
                    },
                ],
            },
            producer: {
                key: 'producer_summary:cycle:visual-cycle',
                kind: 'producer_summary',
                scheduledDate: pickupDate,
                href: '/producer/cycles/visual-cycle',
                cutoffs: [],
            },
            catalogue: {
                key: 'catalogue_maintenance',
                kind: 'catalogue_maintenance',
                scheduledDate: null,
                href: '/catalogue/producers',
                cutoffs: [],
            },
        };
        visualProps.opportunities = opportunities[role]
            ? [opportunities[role]]
            : [];
        if (state === 'opportunities-repeated')
            visualProps.opportunities.push({
                ...opportunities[role],
                key: 'producer_summary:cycle:visual-cycle-2',
                scheduledDate: '2026-11-12',
                href: '/producer/cycles/visual-cycle-2',
            });
    }
    if (state === 'manual-send') {
        // The server's order for one pickup date: sending, then reception, then the baskets already listed. Only a manager is offered the send.
        visualProps.attention = [
            ...(role === 'manager'
                ? [
                      {
                          key: 'send_order:cycle:visual-cycle',
                          action: 'send_order',
                          cycleId: 'visual-cycle',
                          scheduledDate: pickupDate,
                          count: 1,
                          href: '/manage/cycles',
                          producerName: 'Forn del Barri',
                          allocationDifferenceCount: 0,
                      },
                  ]
                : []),
            {
                key: 'check_delivery_and_allocations:cycle:visual-cycle',
                action: 'check_delivery_and_allocations',
                cycleId: 'visual-cycle',
                scheduledDate: pickupDate,
                count: 1,
                href: '/receptions/visual-cycle/visual-producer',
                producerName: 'Hort de la Vall',
                allocationDifferenceCount: 0,
            },
            ...visualProps.attention,
        ];
    }
    if (state === 'reception' || state === 'reception-long-name') {
        const longName = state === 'reception-long-name';
        visualProps.attention = [
            {
                key: 'check_delivery_and_allocations:cycle:visual-cycle',
                action: 'check_delivery_and_allocations',
                cycleId: 'visual-cycle',
                scheduledDate: pickupDate,
                count: 1,
                href: '/receptions/visual-cycle/visual-producer',
                producerName: longName
                    ? 'Cooperativa Agrícola i Ramadera de la Figuera del barri de la Vall'
                    : 'Forn del Barri',
                allocationDifferenceCount: 1,
            },
            {
                key: 'mark_distributed:cycle:visual-cycle',
                action: 'mark_distributed',
                cycleId: 'visual-cycle',
                scheduledDate: pickupDate,
                count: 2,
                href: '/receptions',
                producerName: null,
                allocationDifferenceCount: 0,
            },
            ...visualProps.attention,
        ];
    }
}
if (pageName === 'Dashboard') {
    // Every dashboard state counts requests, so "no idle request" and "no write request" are measured, not assumed (R15).
    const freshProps = {
        readFailed: false,
        lastUpdatedAt: '2026-11-12T09:30:00+01:00',
    };
    Object.assign(visualProps, freshProps);
    document.body.dataset.visualRequests = '0';
    document.body.dataset.visualWrites = '0';
    if (state === 'initial-error') {
        Object.assign(visualProps, {
            readFailed: true,
            lastUpdatedAt: null,
            attention: null,
            opportunities: null,
        });
    }
    http.setClient({
        request: async (config: { method?: string; url?: unknown }) => {
            const log = ((
                window as unknown as Record<string, unknown>
            ).visualRequestLog ??= []) as string[];
            log.push(`${config.method ?? 'get'} ${String(config.url)}`);
            const requests = Number(document.body.dataset.visualRequests) + 1;
            document.body.dataset.visualRequests = String(requests);
            if ((config.method ?? 'get').toLowerCase() !== 'get')
                document.body.dataset.visualWrites = String(
                    Number(document.body.dataset.visualWrites) + 1,
                );
            if (state === 'history-probe') {
                // In-app history probe: a visit to /settings/appearance, and a dashboard read that advances the time.
                const toAppearance = !String(config.url).includes('dra-205');
                return {
                    status: 200,
                    data: JSON.stringify({
                        ...page,
                        component: toAppearance
                            ? 'settings/Appearance'
                            : 'Dashboard',
                        url: toAppearance
                            ? '/settings/appearance'
                            : window.location.pathname + window.location.search,
                        props: {
                            ...page.props,
                            lastUpdatedAt: '2026-11-12T09:45:00+01:00',
                        },
                    }),
                    headers: { 'x-inertia': 'true' },
                };
            }
            if (state !== 'stale-error') return new Promise(() => {});
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    url: window.location.pathname + window.location.search,
                    props: {
                        ...page.props,
                        readFailed: true,
                        lastUpdatedAt: null,
                        attention: null,
                        opportunities: null,
                    },
                }),
                headers: { 'x-inertia': 'true' },
            };
        },
    });
}
if (pageName === 'Wallet/Statement') {
    visualProps.household = role === 'manager' ? household : null;
}

if (state === 'empty') {
    const collection = (
        {
            'Manage/Accounts/Index': 'accounts',
            'Manage/Cycles/Index': 'cycles',
            'Manage/Households/Index': 'households',
            'Manage/Producers/Index': 'producers',
            'Manage/ReturnItems/Index': 'items',
            'Manage/Stock/Index': 'products',
            'Orders/Index': 'cycles',
            'Producer/Products/Index': 'products',
        } as Record<string, string>
    )[pageName];
    if (collection) visualProps[collection] = [];
    if (pageName === 'Manage/Households/Index') visualProps.toSettle = [];
    if (pageName === 'Dashboard') {
        visualProps.nextPickup = null;
        visualProps.openCycles = 0;
        visualProps.attention = [];
        if (role === 'manager') {
            visualProps.managerSummary = {
                cycles: 0,
                activeCycles: 0,
                households: 0,
                activeHouseholds: 0,
                accounts: 0,
                pendingInvitations: 0,
                producers: 0,
                activeProducers: 0,
            };
        }
    }
    if (pageName === 'Manage/Cycles/Show') visualProps.summary.producers = [];
    if (pageName === 'Receptions/Show') visualProps.reception.products = [];
    if (pageName === 'Manage/Stock/Movements') visualProps.cycles = [];
    if (pageName === 'Orders/Edit') {
        visualProps.order.lines = [];
        visualProps.order.producers = [];
    }
    if (pageName === 'Producer/CycleSummary') visualProps.summary.products = [];
    if (pageName === 'Wallet/Statement')
        visualProps.wallet = { balance: '0.00', movements: [] };
}

if (state === 'long-content') {
    const longName =
        'La Figuera del barri de la Vall de les Hortes i de la Cooperativa';
    const visit = (value: any): void => {
        if (!value || typeof value !== 'object') return;
        if (Array.isArray(value)) {
            value.forEach(visit);
            return;
        }
        for (const [key, item] of Object.entries(value)) {
            if (
                ['name', 'householdName', 'producerName'].includes(key) &&
                typeof item === 'string' &&
                item !== 'Guixa'
            )
                value[key] = longName;
            else visit(item);
        }
    };
    visit(visualProps);
    visit(visualProps.auth);
    if (pageName === 'Orders/Index')
        visualProps.cycles = Array.from({ length: 6 }, (_, index) => ({
            ...visualProps.cycles[0],
            cycleId: `cycle-${index + 1}`,
        }));
    if (pageName === 'Wallet/Statement')
        visualProps.wallet.movements[0].note = longName;
}

if (state === 'error') {
    visualProps.errors = fixtureErrors;
}
if (state === 'pending-invitation' && pageName === 'Manage/Accounts/Index') {
    visualProps.accounts[0].invitationPending = true;
}
if (state === 'pending-invitation' && pageName === 'Manage/Producers/Index') {
    visualProps.producers[0].accountEmail = 'hort@example.test';
    visualProps.producers[0].accountInvitationPending = true;
}
if (state === 'inactive-household' && pageName === 'Manage/Households/Show') {
    visualProps.household.active = false;
}
if (state === 'settlement-details' && pageName === 'Manage/Households/Index') {
    visualProps.toSettle[0].openBaskets = [
        { basketId: 'basket-1', status: 'draft', date: pickupDate },
        { basketId: 'basket-2', status: 'validated', date: '2026-11-12' },
    ];
    visualProps.toSettle[0].balanceCents = -1200;
}
if (state === 'status') {
    visualProps.status = 'verification-link-sent';
    if (pageName === 'auth/VerifyEmail') {
        visualProps.verificationLinkSent = true;
    }
    if (pageName === 'settings/Profile') {
        visualProps.mustVerifyEmail = true;
        visualProps.verificationLinkSent = true;
        visualProps.auth.user.email_verified_at = null;
    }
}
if (pageName === 'Receptions/Show') {
    if (query.get('receptionBranch') === 'unordered') {
        visualProps.reception.products[0].nobodyOrdered = true;
        visualProps.reception.products[0].ordered = '0';
        visualProps.reception.products[0].allocations = [];
    }
    if (query.get('receptionBranch') === 'unbalanced') {
        visualProps.reception.products[0].balanced = false;
        visualProps.reception.products[0].difference = '12345.000';
    }
    if (query.get('receptionBranch') === 'extra-household') {
        visualProps.households.push({
            id: 'household-2',
            name: 'El Roure',
        });
    }
}
if (query.get('adjusted') === '1' && pageName === 'Receptions/Show')
    visualProps.reception.products[0].hasAdjustments = true;
if (state === 'manual-allocation' && pageName === 'Receptions/Show') {
    visualProps.reception.products[0].hasManualAllocations = true;
    visualProps.reception.products[0].allocations[0].byHand = true;
}
if (pageName === 'Receptions/Show' && state === 'received-overwrite') {
    visualProps.reception.products[0].name =
        'Arròs integral de la cooperativa de la Vall de les Hortes';
    visualProps.reception.products[0].allocations[0].householdName =
        'Unitat de convivència de la Figuera del barri de la Vall de les Hortes';
    visualProps.reception.products[0].hasManualAllocations =
        state === 'received-overwrite';
    visualProps.reception.products[0].allocations[0].byHand =
        state === 'received-overwrite';
}
if (pageName === 'Manage/Cycles/Index' && state === 'create-no-weekday')
    visualProps.pickupWeekday = null;
if (
    pageName === 'Manage/Cycles/Index' &&
    ['closed-cycle', 'cancelled-cycle'].includes(state)
) {
    visualProps.cycles[0].closed = state === 'closed-cycle';
    visualProps.cycles[0].cancelled = state === 'cancelled-cycle';
}
if (pageName === 'Manage/Cycles/Index' && state === 'no-pending')
    visualProps.pendingCollections = {};
if (pageName === 'Manage/Cycles/Show' && state === 'no-pending')
    visualProps.pendingCollections = { count: 0, href: null };
if (pageName === 'Manage/Cycles/Show' && state === 'cancelled-cycle')
    visualProps.summary.cancelled = true;
if (pageName === 'Orders/Index' && state === 'closed-and-cancelled')
    visualProps.cycles = [
        { cycleId, pickupDate, open: false, cancelled: false, lineCount: 1 },
        {
            cycleId: 'cycle-2',
            pickupDate: '2026-11-12',
            open: false,
            cancelled: true,
            lineCount: 0,
        },
    ];
if (pageName === 'Orders/Edit' && state === 'cancelled-cycle')
    visualProps.order.cancelled = true;
if (pageName === 'Manage/Stock/Index' && state === 'reactivate-confirm')
    visualProps.products[0].active = false;
if (
    pageName === 'Manage/Households/Show' &&
    ['invitation-pending', 'invitation-expired'].includes(state)
) {
    const invite = visualProps.household.members[0];
    invite.invitationPending = true;
    invite.invitationExpired = state === 'invitation-expired';
    invite.invitationExpiresAt = invite.invitationExpired
        ? '2026-10-01T12:00:00Z'
        : '2026-11-05T12:00:00Z';
}
if (pageName === 'Producer/CycleSummary' && state === 'unsent-availability') {
    visualProps.summary.sent = false;
    visualProps.summary.products.push({
        ...visualProps.summary.products[0],
        productId: 'product-2',
        name: 'Tomàquets',
        available: false,
    });
}
if (pageName === 'settings/Security' && state.startsWith('passkey-')) {
    visualProps.passkeys = [
        {
            id: 1,
            name:
                state === 'passkey-long'
                    ? 'Clau d’accés de la Figuera del barri de la Vall de les Hortes i de la Cooperativa'
                    : 'MacBook de Maria',
            authenticator: 'platform',
            created_at_diff: 'fa dos dies',
            last_used_at_diff: 'fa una hora',
        },
    ];
}
if (pageName === 'settings/Security' && state === 'two-factor-enabled') {
    visualProps.twoFactorEnabled = true;
    useTwoFactorAuth().recoveryCodesList.value = [
        'recovery-one',
        'recovery-two',
    ];
}
if (pageName === 'settings/Security' && state === 'two-factor-verify') {
    const twoFactor = useTwoFactorAuth();
    twoFactor.qrCodeSvg.value =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="black"/></svg>';
    twoFactor.manualSetupKey.value = 'JBSWY3DPEHPK3PXP';
    visualProps.requiresConfirmation = true;
}
if (
    pageName === 'settings/Security' &&
    ['two-factor-setup', 'recovery-codes'].includes(state)
) {
    const twoFactor = useTwoFactorAuth();
    if (state === 'two-factor-setup') {
        twoFactor.qrCodeSvg.value =
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="black"/></svg>';
        twoFactor.manualSetupKey.value = 'JBSWY3DPEHPK3PXP';
        visualProps.requiresConfirmation = true;
    } else {
        visualProps.twoFactorEnabled = true;
        twoFactor.recoveryCodesList.value = ['recovery-one', 'recovery-two'];
    }
}

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: (name) => {
        if (name === 'Welcome') return null;
        if (name.startsWith('auth/')) return AuthLayout;
        if (name.startsWith('settings/')) return [AppLayout, SettingsLayout];
        return AppLayout;
    },
    withApp: (app, { page }) => {
        installTranslations(app, page.props.locale);
        app.directive('focus', {
            mounted: (element: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) element.focus();
            },
        });
    },
    progress: false,
}).then(async () => {
    const waitForElement = async (selector: string): Promise<HTMLElement> => {
        for (let attempt = 0; attempt < 100; attempt++) {
            const element = document.querySelector<HTMLElement>(selector);
            if (element) return element;
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
        throw new Error(`Visual interaction target missing: ${selector}`);
    };
    const clickText = (phrase: string): boolean => {
        const button = [
            ...document.querySelectorAll<HTMLButtonElement>('button'),
        ].find((candidate) => candidate.textContent?.includes(phrase));
        button?.click();
        return !!button;
    };
    if (
        ['error', 'disabled'].includes(state) &&
        !['Receptions/Show', 'Orders/Edit'].includes(pageName)
    ) {
        const forms = [
            ...document.querySelectorAll<HTMLFormElement>(
                '#main-content form, .reference-style-auth form',
            ),
        ];
        const form =
            forms.find((candidate) =>
                candidate.querySelector('[name][required]'),
            ) ?? forms[0];
        if (form) {
            for (const input of form.querySelectorAll<HTMLInputElement>(
                'input[required]',
            )) {
                if (
                    input.type === 'checkbox' ||
                    input.type === 'radio' ||
                    input.type === 'hidden'
                )
                    continue;
                input.value =
                    input.type === 'email'
                        ? 'test@example.test'
                        : input.type === 'number'
                          ? '1'
                          : input.type === 'time'
                            ? '20:00'
                            : input.type === 'date'
                              ? '2026-11-05'
                              : input.type === 'password'
                                ? 'ExamplePassword123!'
                                : 'Exemple';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            form.requestSubmit();
        }
    }
    if (state === 'recovery-code' && pageName === 'auth/TwoFactorChallenge') {
        clickText('codi de recuperació');
    }
    if (state === 'create-dialog' && pageName === 'Manage/Cycles/Index')
        document
            .querySelector<HTMLButtonElement>('[data-test="open-create-cycle"]')
            ?.click();
    if (state === 'manage-dialog' && pageName === 'Manage/Cycles/Index')
        document
            .querySelector<HTMLButtonElement>('[data-test="manage-cycle-1"]')
            ?.click();
    if (state === 'edit-dialog' && pageName === 'Manage/Stock/Index')
        document
            .querySelector<HTMLButtonElement>(
                'button[aria-label="Edita Arròs"]',
            )
            ?.click();
    if (state === 'appearance-menu' && pageName === 'settings/Appearance')
        document
            .querySelector<HTMLButtonElement>(
                '[data-test="appearance-trigger"]',
            )
            ?.click();
    if (state === 'two-factor-setup' && pageName === 'settings/Security')
        clickText('Continua la configuració');
    if (state === 'recovery-codes' && pageName === 'settings/Security')
        clickText('Mostra els codis de recuperació');
    if (state === 'passkey-form' && pageName === 'settings/Security')
        clickText('Afegeix una clau d’accés');
    if (pageName === 'settings/Profile' && state.startsWith('delete-')) {
        (await waitForElement('[data-test="delete-user-button"]')).click();
        if (['delete-error', 'delete-pending'].includes(state)) {
            const form = (await waitForElement(
                '[role="dialog"] form',
            )) as HTMLFormElement;
            const password = form.querySelector<HTMLInputElement>(
                'input[name="password"]',
            );
            if (!password)
                throw new Error('Delete confirmation password input missing');
            password.value = 'ExamplePassword123!';
            password.dispatchEvent(new Event('input', { bubbles: true }));
            form.requestSubmit();
        }
    }
    if (
        pageName === 'settings/Security' &&
        [
            'passkey-remove',
            'passkey-remove-pending',
            'passkey-remove-error',
        ].includes(state)
    ) {
        (await waitForElement('button[aria-label="Elimina"]')).click();
        if (state !== 'passkey-remove') {
            (
                await waitForElement(
                    '[role="dialog"] button[data-variant="destructive"]',
                )
            ).click();
        }
    }
    if (pageName === 'Receptions/Show' && state === 'received-overwrite') {
        const form = (await waitForElement(
            '[data-test="received-form-product-1"]',
        )) as HTMLFormElement;
        const input = form.querySelector<HTMLInputElement>(
            '#received-product-1',
        );
        if (!input) throw new Error('Received total input missing');
        input.value = '3.125';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        form.requestSubmit();
    }
    if (pageName === 'Manage/Cycles/Index' && state === 'create-no-weekday')
        (await waitForElement('[data-test="open-create-cycle"]')).click();
    if (
        pageName === 'Manage/Cycles/Index' &&
        [
            'manage-cutoff',
            'manage-close-confirm',
            'manage-cancel-confirm',
        ].includes(state)
    ) {
        const participant = state !== 'manage-cancel-confirm';
        (
            await waitForElement(
                participant
                    ? '[data-test="manage-cycle-1-producer-1"]'
                    : '[data-test="manage-cycle-1"]',
            )
        ).click();
        if (state !== 'manage-cutoff') {
            const dialog = await waitForElement('dialog[open]');
            dialog.querySelector<HTMLElement>('details summary')?.click();
            const phrase =
                state === 'manage-close-confirm'
                    ? trans('Close now')
                    : trans('Cancel cycle');
            const action = [
                ...dialog.querySelectorAll<HTMLButtonElement>('button'),
            ].find((button) => button.textContent?.includes(phrase));
            if (!action) throw new Error(`Cycle action missing: ${phrase}`);
            action.click();
        }
    }
    if (
        pageName === 'Manage/Stock/Index' &&
        ['deactivate-confirm', 'reactivate-confirm'].includes(state)
    ) {
        (await waitForElement('button[aria-label="Edita Arròs"]')).click();
        const dialog = await waitForElement('dialog[open]');
        dialog.querySelector<HTMLElement>('details summary')?.click();
        const phrase =
            state === 'deactivate-confirm'
                ? trans('Deactivate')
                : trans('Reactivate');
        const action = [
            ...dialog.querySelectorAll<HTMLButtonElement>('button'),
        ].find((button) => button.textContent?.trim() === phrase);
        if (!action) throw new Error(`Stock action missing: ${phrase}`);
        action.click();
    }
    if (pageName === 'settings/Security' && state === 'two-factor-verify') {
        clickText(trans('Continue setup'));
        const dialog = await waitForElement('[role="dialog"]');
        const action = [
            ...dialog.querySelectorAll<HTMLButtonElement>('button'),
        ].find((button) => button.textContent?.trim() === trans('Continue'));
        if (!action) throw new Error('Two-factor next step missing');
        action.click();
    }
    const emptyMessage = (
        {
            'Manage/Accounts/Index': 'No accounts yet.',
            'Manage/Cycles/Index': 'No cycles yet.',
            'Manage/Cycles/Show': 'Nobody has ordered yet.',
            'Manage/Households/Index': 'There are no households yet.',
            'Manage/Producers/Index': 'No producers yet.',
            'Receptions/Show': 'Nothing was ordered from this producer.',
            'Manage/ReturnItems/Index': 'No return items yet.',
            'Manage/Stock/Index': 'No stock products yet.',
            'Orders/Edit': 'No producer takes part in this cycle yet.',
            'Orders/Index': 'There are no cycles yet.',
            'Producer/CycleSummary': 'Nothing to deliver.',
            'Producer/Products/Index': 'No products yet.',
            'Wallet/Statement': 'No movements yet.',
        } as Record<string, string>
    )[pageName];
    const visible = (selector: string) =>
        [...document.querySelectorAll<HTMLElement>(selector)].some(
            (element) =>
                element.getClientRects().length > 0 &&
                getComputedStyle(element).display !== 'none',
        );
    const mainText = () =>
        (
            document.querySelector('#main-content') ??
            document.querySelector('.reference-style-auth')
        )?.textContent ?? '';
    const stateReady = (): boolean => {
        switch (state) {
            case 'default':
                return true;
            case 'empty':
                if (pageName === 'Dashboard' && role === 'finance')
                    return (
                        mainText().length > 0 &&
                        !visible('[data-test="attention-list"]')
                    );
                if (pageName === 'Dashboard')
                    return role === 'manager'
                        ? mainText().includes('0') && !mainText().includes('1')
                        : mainText().includes(trans('None'));
                return (
                    !!emptyMessage && mainText().includes(trans(emptyMessage))
                );
            case 'history-probe':
                return document.body.dataset.visualReturnReads !== undefined;
            case 'refresh-busy':
                return (
                    document.body.dataset.visualRequests === '1' &&
                    visible('[data-test="refresh-busy"]') &&
                    mainText().includes(trans('Updating pending work…')) &&
                    document.querySelector(
                        '[data-test="refresh-button"][aria-disabled="true"]',
                    ) !== null
                );
            case 'stale-error':
                return (
                    visible('[data-test="freshness-stale"]') &&
                    visible('[role="alert"]') &&
                    visible('[data-test="attention-group"]')
                );
            case 'initial-error':
                return (
                    visible('[role="alert"]') &&
                    !visible('[data-test="attention-group"]')
                );
            case 'opportunities':
            case 'opportunities-repeated':
                return visible('[data-test="opportunity"]');
            case 'baskets':
            case 'manual-send':
                return visible('[data-test="attention-group"]');
            case 'reception':
            case 'reception-long-name':
                return visible('[data-test="attention-blocker"]');
            case 'long-content':
                return pageName === 'Orders/Index'
                    ? document.querySelectorAll('#main-content li').length >= 6
                    : mainText().includes('La Figuera del barri de la Vall');
            case 'error':
                return visible(
                    '#main-content p.text-destructive, .reference-style-auth p.text-destructive, [data-test="domain-error"] p',
                );
            case 'disabled':
                return visible(
                    '#main-content form button:disabled, .reference-style-auth form button:disabled',
                );
            case 'completed':
                return visible('[data-test="completed"]');
            case 'stock-lines':
                return visible('[data-test="stock-line-stock-1"]');
            case 'return-lines':
                return visible('[data-test="return-line-return-1"]');
            case 'manual-allocation':
                return mainText().includes(trans('· Set by hand'));
            case 'pending-invitation':
                return (
                    mainText().includes(trans('Invitation pending')) ||
                    mainText().includes(trans('invitation pending'))
                );
            case 'inactive-household':
                return mainText().includes(trans('Reactivate household'));
            case 'settlement-details':
                return (
                    mainText().includes('-12,00') &&
                    mainText().includes('12/11/2026')
                );
            case 'status':
                return visible('.pilot-status');
            case 'recovery-code':
                return visible('input[name="recovery_code"]');
            case 'create-dialog':
            case 'manage-dialog':
            case 'edit-dialog':
                return visible('dialog[open]');
            case 'appearance-menu':
                return (
                    visible('[data-slot="dropdown-menu-content"]') &&
                    document.activeElement?.matches(
                        '[role="menuitemradio"]',
                    ) === true &&
                    !!document.activeElement.closest(
                        '[data-slot="dropdown-menu-content"]',
                    ) &&
                    parseFloat(
                        getComputedStyle(document.activeElement).minHeight,
                    ) >= 44 &&
                    document.activeElement.getBoundingClientRect().height >= 44
                );
            case 'two-factor-setup':
                return visible('[role="dialog"]');
            case 'recovery-codes':
                return mainText().includes('recovery-one');
            case 'passkey-form':
                return visible('#passkey-name');
            case 'delete-open':
                return (
                    visible('[role="dialog"] input[name="password"]') &&
                    document
                        .querySelector('[role="dialog"]')
                        ?.textContent?.includes(
                            trans(
                                'Are you sure you want to delete your account?',
                            ),
                        ) === true
                );
            case 'delete-error':
                return (
                    document.body.dataset.visualRequest === 'error' &&
                    visible('[role="dialog"] p.text-destructive')
                );
            case 'delete-pending':
                return (
                    document.body.dataset.visualRequest === 'pending' &&
                    document.querySelector(
                        '[data-test="confirm-delete-user-button"]:disabled',
                    ) !== null
                );
            case 'passkey-row':
            case 'passkey-long':
                return mainText().includes(visualProps.passkeys[0].name);
            case 'passkey-remove':
                return (
                    visible('[role="dialog"]') &&
                    document
                        .querySelector('[role="dialog"]')
                        ?.textContent?.includes(trans('Remove passkey')) ===
                        true
                );
            case 'passkey-remove-pending':
                return (
                    document.body.dataset.visualRequest === 'pending' &&
                    document.querySelector(
                        '[role="dialog"] button[data-variant="destructive"]:disabled',
                    ) !== null
                );
            case 'passkey-remove-error':
                return (
                    document.body.dataset.visualRequest === 'error' &&
                    document.querySelector(
                        '[role="dialog"] button[data-variant="destructive"]:not(:disabled)',
                    ) !== null
                );
            case 'received-overwrite':
                return (
                    visible('[role="dialog"]') &&
                    document
                        .querySelector('[role="dialog"]')
                        ?.textContent?.includes(
                            trans('Replace the manual allocations?'),
                        ) === true
                );
            case 'create-no-weekday':
                return (
                    visible('dialog[open] input[name="permanent"]') &&
                    visible('dialog[open] [name="pickup_weekday"]')
                );
            case 'manage-cutoff':
                return (
                    visible('dialog[open] #cutoff-date') &&
                    visible('dialog[open] #cutoff-time')
                );
            case 'manage-close-confirm':
                return (
                    visible('dialog[open] [data-test="confirm-close"]') &&
                    document
                        .querySelector('dialog[open]')
                        ?.textContent?.includes(
                            trans("This producer's orders will close now."),
                        ) === true
                );
            case 'manage-cancel-confirm':
                return (
                    visible(
                        'dialog[open] [data-test="confirm-cancel-cycle-1"]',
                    ) &&
                    document
                        .querySelector('dialog[open]')
                        ?.textContent?.includes(
                            trans(
                                'All orders in the cycle will be cancelled. This cannot be undone.',
                            ),
                        ) === true
                );
            case 'deactivate-confirm':
            case 'reactivate-confirm':
                return (
                    visible(
                        'dialog[open] [data-test="confirm-status-stock-1"]',
                    ) &&
                    document
                        .querySelector('dialog[open]')
                        ?.textContent?.includes(
                            state === 'deactivate-confirm'
                                ? trans(
                                      'Deactivate this product? It will no longer be available for new pickups.',
                                  )
                                : trans('Reactivate this product?'),
                        ) === true
                );
            case 'invitation-pending':
                return (
                    mainText().includes(
                        trans('Link valid until :date', {
                            date: '5/11/2026',
                        }).split('5/11/2026')[0],
                    ) && mainText().includes(trans('Change email'))
                );
            case 'invitation-expired':
                return (
                    mainText().includes(
                        trans('Link expired on :date; resend it', {
                            date: '1/10/2026',
                        }).split('1/10/2026')[0],
                    ) && mainText().includes(trans('Resend invitation'))
                );
            case 'unsent-availability':
                return (
                    visible('[data-test="toggle-product-1"]') &&
                    visible('[data-test="toggle-product-2"]') &&
                    mainText().includes(trans('Mark available')) &&
                    mainText().includes(trans('Mark unavailable'))
                );
            case 'two-factor-verify':
                return (
                    visible('[role="dialog"] #otp') &&
                    document
                        .querySelector('[role="dialog"]')
                        ?.textContent?.includes(
                            trans('Verify authentication code'),
                        ) === true
                );
            case 'two-factor-enabled':
                return (
                    mainText().includes(trans('Disable 2FA')) &&
                    mainText().includes(trans('2FA recovery codes'))
                );
            case 'no-pending':
                return (
                    visible(
                        pageName === 'Manage/Cycles/Index'
                            ? '[data-test="cycle-cycle-1"]'
                            : '[data-test="container-counts"]',
                    ) && !visible('[data-test="pending-collections"]')
                );
            case 'closed-cycle':
                return (
                    visible('[data-test="cycle-cycle-1"]') &&
                    mainText().includes(trans('Closed')) &&
                    !visible('[data-test="manage-cycle-1"]')
                );
            case 'cancelled-cycle':
                if (pageName === 'Manage/Cycles/Index')
                    return (
                        visible('[data-test="cancelled-cycle-1"]') &&
                        !visible('[data-test="manage-cycle-1"]')
                    );
                if (pageName === 'Manage/Cycles/Show')
                    return (
                        visible('[data-test="cancelled-notice"]') &&
                        !visible('[data-test="container-counts"]')
                    );
                return (
                    visible('[data-test="order-cancelled"]') &&
                    !visible('[data-test^="save-"]')
                );
            case 'closed-and-cancelled':
                return (
                    mainText().includes(trans('Closed to orders')) &&
                    mainText().includes(trans('Cancelled')) &&
                    document.querySelectorAll(
                        '#main-content [data-test^="cycle-"]',
                    ).length >= 2
                );
            default:
                return false;
        }
    };
    if (state === 'appearance-menu') {
        let menu = document.querySelector<HTMLElement>(
            '[data-slot="dropdown-menu-content"]',
        );
        for (let attempt = 0; !menu && attempt < 100; attempt++) {
            await new Promise((resolve) => window.setTimeout(resolve, 25));
            menu = document.querySelector<HTMLElement>(
                '[data-slot="dropdown-menu-content"]',
            );
        }
        menu?.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'ArrowDown',
                code: 'ArrowDown',
                bubbles: true,
                cancelable: true,
            }),
        );
    }
    if (
        pageName === 'Dashboard' &&
        ['refresh-busy', 'stale-error'].includes(state)
    ) {
        // The person's own explicit refresh: the only request these states make.
        for (let attempt = 0; attempt < 100; attempt++) {
            const refresh = document.querySelector<HTMLElement>(
                '[data-test="refresh-button"]',
            );
            if (refresh) {
                refresh.click();
                break;
            }
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
    }
    if (pageName === 'Dashboard' && state === 'history-probe') {
        // Drives the real Inertia router: leave through an in-app link, then browser Back. Only the HTTP client is mocked.
        const wait = async (until: () => boolean) => {
            for (let attempt = 0; !until() && attempt < 100; attempt++)
                await new Promise((resolve) => window.setTimeout(resolve, 25));
        };
        const dashboardReads = () =>
            (
                (window as unknown as Record<string, string[]>)
                    .visualRequestLog ?? []
            ).filter((entry) => entry.includes('dra-205')).length;
        await wait(
            () => !!document.querySelector('[data-test="freshness-time"]'),
        );
        await new Promise((resolve) => window.setTimeout(resolve, 500));
        document.body.dataset.visualIdleReads = String(dashboardReads());
        document
            .querySelector<HTMLElement>('[data-test="attention-group"] a')
            ?.click();
        await wait(() => window.location.pathname === '/settings/appearance');
        window.history.back();
        await wait(
            () =>
                document
                    .querySelector('[data-test="freshness-time"]')
                    ?.textContent?.includes('09:45') === true,
        );
        await new Promise((resolve) => window.setTimeout(resolve, 500));
        document.body.dataset.visualReturnReads = String(dashboardReads());
        document.body.dataset.visualReturnTime =
            document
                .querySelector('[data-test="freshness-time"]')
                ?.textContent?.trim() ?? '';
    }
    if (state !== 'default') {
        let ready = stateReady();
        for (let attempt = 0; !ready && attempt < 100; attempt++) {
            await new Promise((resolve) => window.setTimeout(resolve, 25));
            ready = stateReady();
        }
        if (!ready)
            throw new Error(
                `Visual state did not render: ${pageName}/${state}`,
            );
        document.body.dataset.visualReady = state;
    }
    if (pageName === 'Wallet/Statement') {
        const header = document.querySelector<HTMLElement>(
            '.pilot-wallet-table thead',
        );
        if (
            header &&
            getComputedStyle(header).clipPath === 'none' &&
            header.getBoundingClientRect().width > 1
        )
            header.querySelectorAll('th').forEach((cell) => {
                cell.dataset.visualText = 'visible-table-header';
            });
    }
    const markFocusableEdges = (root: Element | null, prefix: string) => {
        if (!root) return;
        const controls = [
            ...root.querySelectorAll<HTMLElement>(
                'a[href], button, input, select, textarea, summary, [tabindex]',
            ),
        ].filter((control) => {
            const rect = control.getBoundingClientRect();
            const style = getComputedStyle(control);
            return (
                rect.width > 0 &&
                rect.height > 0 &&
                style.display !== 'none' &&
                style.visibility === 'visible' &&
                control.tabIndex >= 0 &&
                !control.matches(':disabled') &&
                !control.closest('[data-slot="breadcrumb"]') &&
                (!control.closest('details:not([open])') ||
                    control.matches('summary')) &&
                !control.closest('[hidden], [inert], [aria-hidden="true"]')
            );
        });
        controls[0]?.setAttribute(`data-visual-${prefix}-first`, '');
        controls.at(-1)?.setAttribute(`data-visual-${prefix}-last`, '');
    };
    markFocusableEdges(
        document.querySelector(
            pageName.startsWith('auth/')
                ? '.reference-style-auth form'
                : '#main-content',
        ),
        'page-focus',
    );
    if (
        [
            'create-dialog',
            'manage-dialog',
            'edit-dialog',
            'create-no-weekday',
            'manage-cutoff',
            'manage-close-confirm',
            'manage-cancel-confirm',
            'deactivate-confirm',
            'reactivate-confirm',
        ].includes(state)
    )
        markFocusableEdges(
            document.querySelector('dialog[open]'),
            'overlay-focus',
        );
    if (
        [
            'two-factor-setup',
            'two-factor-verify',
            'delete-open',
            'delete-error',
            'delete-pending',
            'passkey-remove',
            'passkey-remove-pending',
            'passkey-remove-error',
            'received-overwrite',
        ].includes(state)
    )
        markFocusableEdges(
            document.querySelector('[role="dialog"]'),
            'overlay-focus',
        );
    if (query.get('drawer') === '1') {
        const trigger = await waitForElement('button[data-sidebar="trigger"]');
        trigger.click();
        const drawer = await waitForElement(
            '[data-sidebar="sidebar"][data-mobile="true"]',
        );
        markFocusableEdges(drawer, 'drawer-focus');
        document.body.dataset.visualReady = 'drawer-open';
    }
    const restoreFixtureUrl = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restoreFixtureUrl();
    window.setTimeout(restoreFixtureUrl, 100);
    window.setTimeout(restoreFixtureUrl, 500);
});

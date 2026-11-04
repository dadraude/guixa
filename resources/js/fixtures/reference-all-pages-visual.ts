import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Dashboard';
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
    pendingPickups: [{ cycleId, pickupDate }],
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
        pendingPickups: [{ cycleId, pickupDate }],
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
    },
    'Manage/Cycles/Show': {
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
                { householdId, householdName: household.name, count: 2 },
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
    'Manage/Pickups/Index': {
        cycleId,
        closed: false,
        pickups: [
            {
                basketId: 'basket',
                householdId,
                householdName: household.name,
                status: 'draft',
                totalCents: 640,
            },
        ],
    },
    'Manage/Producers/Index': { producers: [producer] },
    'Manage/Reception/Show': {
        reception,
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
    'Manage/Stock/Edit': { product: stockProduct },
    'Manage/Stock/Index': { products: [stockProduct] },
    'Manage/Stock/Movements': {
        product: stockProduct,
        cycles: [{ id: cycleId, pickupDate }],
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
                    occurredAt: '2026-10-27T12:00:00Z',
                    note: null,
                    cycleId: null,
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
const pageUrl = welcomePage
    ? '/'
    : authPage
      ? `/${pageName.split('/').at(-1)?.toLowerCase()}`
      : pageName === 'Dashboard'
        ? '/dashboard'
        : `/${pageName
              .replace(/\/Index$/, '')
              .replace(/\/Show$/, '/item')
              .toLowerCase()}`;
const page = {
    component: pageName,
    url: pageUrl,
    version: 'reference-all-pages',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user:
                authPage || welcomePage
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
                authPage || welcomePage
                    ? []
                    : ['manager', 'member', 'producer'],
        },
        roleAreas:
            authPage || welcomePage
                ? []
                : [
                      {
                          role: 'manager',
                          label: 'Cooperative',
                          href: '/manage/cycles',
                      },
                      { role: 'member', label: 'Member', href: '/orders' },
                      {
                          role: 'producer',
                          label: 'Producer',
                          href: '/producer/products',
                      },
                  ],
        sidebarOpen: true,
        ...pageProps[pageName],
    },
};

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
}).then(() => {
    const restoreFixtureUrl = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restoreFixtureUrl();
    window.setTimeout(restoreFixtureUrl, 100);
    window.setTimeout(restoreFixtureUrl, 500);
});

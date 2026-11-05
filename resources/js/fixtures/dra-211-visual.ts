import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { financeBasketRow } from './financeBasketRow';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const pageName = query.get('page') ?? 'Finance/Baskets/Show';
const state = query.get('state') ?? 'populated';
const role =
    pageName === 'Manage/Accounts/Index'
        ? 'manager'
        : (query.get('role') ?? 'finance');
const fixtureUrl = window.location.href;
const fixturePath = window.location.pathname + window.location.search;
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
if (!components[`../pages/${pageName}.vue`]) {
    throw new Error(`Unknown T56 visual page: ${pageName}`);
}

const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const accountId = '0198f0a0-0000-7000-8000-000000000031';
const householdId = '0198f0a0-0000-7000-8000-000000000032';
const basketId = '0198f0a0-0000-7000-8000-000000000033';
const productId = '0198f0a0-0000-7000-8000-000000000034';
const householdName =
    state === 'long'
        ? 'La llar de la Vall de les terres altes amb un nom extraordinàriament llarg'
        : 'La Figuera';
const productName =
    state === 'long'
        ? 'Mel artesanal de romaní de la Vall de les terres altes'
        : 'Mel de romaní';
const basketState =
    state === 'validated' || state === 'collected' ? state : 'draft';
const basket = financeBasketRow({
    id: basketId,
    householdId,
    householdName,
    state: basketState,
    stockLines:
        state === 'empty'
            ? []
            : [
                  {
                      stockProductId: productId,
                      name: productName,
                      unit: 'pot',
                      quantity: '2.000',
                      unitPriceCents: 650,
                      amountCents: 1300,
                  },
              ],
    totalCents: state === 'empty' ? 0 : 1300,
    balanceCents: basketState === 'draft' ? 500 : -800,
    projectedBalanceCents: basketState === 'draft' ? -800 : -800,
    validatedAt: basketState === 'draft' ? null : '2026-10-30T09:00:00+01:00',
    collectedAt:
        basketState === 'collected' ? '2026-10-30T10:00:00+01:00' : null,
});
const stock = {
    id: productId,
    producerId: 'producer-one',
    name: productName,
    unit: 'pot',
    price: '6.50',
    variable: false,
    containersPerUnit: '2.000',
    active: true,
    available: '12.000',
};
const summaries =
    state === 'empty'
        ? []
        : [
              {
                  id: basketId,
                  householdName,
                  state: 'validated',
                  totalCents: 1300,
                  createdAt: '2026-10-30 09:00',
              },
          ];
const wallet = {
    balance: state === 'empty' ? '0.00' : '-8.00',
    movements:
        state === 'empty'
            ? []
            : [
                  {
                      id: 'movement-one',
                      type: 'charge',
                      amount: '-13.00',
                      occurredAt: '2026-10-30 09:00',
                      note:
                          state === 'long'
                              ? `Compra de cistella per ${householdName}`
                              : 'Compra de cistella',
                      cycleId: null,
                      basketId,
                  },
              ],
};
const propsByPage: Record<string, Record<string, unknown>> = {
    'Manage/Accounts/Index': {
        accounts: [
            {
                accountId,
                name: 'Marta Soler',
                email: 'marta@example.test',
                roles: state === 'granted' ? ['finance'] : [],
                householdId: null,
                householdName: null,
                active: state !== 'inactive',
                invitationPending: false,
            },
        ],
        households: [{ id: householdId, name: householdName }],
    },
    'Finance/Baskets/Index': { baskets: summaries },
    'Finance/Baskets/Create': {
        households:
            state === 'empty' ? [] : [{ id: householdId, name: householdName }],
    },
    'Finance/Baskets/Show': {
        basket,
        containerBalance: 3,
        stockProducts: basketState === 'draft' ? [stock] : [],
    },
    'Wallet/Statement': {
        wallet,
        household:
            role === 'member' ? null : { id: householdId, name: householdName },
        canManageReceipts: role === 'manager',
    },
};
const page = {
    component: pageName,
    url: fixturePath,
    version: 'dra-211',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors:
            state === 'error'
                ? {
                      domain: 'No hi ha prou estoc disponible per validar la cistella.',
                  }
                : {},
        auth: {
            user: {
                id: accountId,
                name: 'Marta Soler',
                email: 'marta@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: [role],
        },
        roleAreas:
            role === 'finance'
                ? [
                      {
                          role: 'finance',
                          label: 'Finance',
                          href: '/finance/baskets',
                      },
                  ]
                : role === 'member'
                  ? [{ role: 'member', label: 'Orders', href: '/' }]
                  : [
                        {
                            role: 'manager',
                            label: 'Management',
                            href: '/manage/cycles',
                        },
                    ],
        ...propsByPage[pageName],
    },
};

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: () => AppLayout,
    withApp: (app, { page }) => installTranslations(app, page.props.locale),
    progress: false,
}).then(async () => {
    for (let attempt = 0; attempt < 100; attempt++) {
        const main = document.querySelector<HTMLElement>('#main-content');
        if (
            main &&
            main.getClientRects().length > 0 &&
            getComputedStyle(main).pointerEvents !== 'none'
        )
            break;
        await new Promise((resolve) => window.setTimeout(resolve, 25));
    }
    const focusable = [
        ...document.querySelectorAll<HTMLElement>(
            '#main-content a[href],#main-content button,#main-content input,#main-content select',
        ),
    ].filter(
        (element) =>
            element.getClientRects().length > 0 &&
            element.tabIndex >= 0 &&
            !element.matches(':disabled') &&
            !element.matches('[data-slot="breadcrumb-link"]') &&
            getComputedStyle(element).pointerEvents !== 'none',
    );
    if (
        focusable.length === 0 &&
        !(
            pageName === 'Wallet/Statement' &&
            (state === 'empty' || role === 'member')
        )
    ) {
        throw new Error(`No focusable T56 controls: ${pageName}/${state}`);
    }
    for (const element of document.querySelectorAll<HTMLElement>(
        '[data-visual-page-focus-first],[data-visual-page-focus-last]',
    )) {
        element.removeAttribute('data-visual-page-focus-first');
        element.removeAttribute('data-visual-page-focus-last');
    }
    if (focusable.length > 0) {
        focusable[0].dataset.visualPageFocusFirst = '';
        focusable.at(-1)!.dataset.visualPageFocusLast = '';
    }
    document.body.dataset.visualReady = state;
    window.history.replaceState(window.history.state, '', fixtureUrl);
});

import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(location.search);
const screen = query.get('screen') ?? 'finance';
const role = query.get('role') ?? (screen === 'member' ? 'member' : 'finance');
const state = query.get('state') ?? 'draft-diff';
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const ids = {
    basket: '0198f0a0-0000-7000-8000-000000000214',
    household: '0198f0a0-0000-7000-8000-000000000215',
    cycle: '0198f0a0-0000-7000-8000-000000000216',
    producer: '0198f0a0-0000-7000-8000-000000000217',
    bread: '0198f0a0-0000-7000-8000-000000000218',
    beef: '0198f0a0-0000-7000-8000-000000000219',
    cherries: '0198f0a0-0000-7000-8000-000000000233',
};
const adjusted = ['validated-adjusted', 'collected'].includes(state);
const nothing = state === 'nothing';
const late = state === 'late-draft';
const differs = ['draft-diff', 'validated-adjusted', 'collected'].includes(
    state,
);
const lateLines = [
    {
        productId: ids.cherries,
        name: 'Cireres',
        unit: 'kg',
        ordered: '1.000',
        allocated: '1.000',
        quantity: '1.000',
        unitPriceCents: 800,
        amountCents: 800,
        effectiveQuantity: '1.000',
        effectiveAmountCents: 800,
        differsFromAllocation: false,
    },
];
const weeklyLines = nothing
    ? []
    : late
      ? lateLines
      : [
            {
                productId: ids.bread,
                name: 'Pa de pagès amb un nom força llarg per comprovar el sagnat',
                unit: 'unitat',
                ordered: '5.000',
                allocated: '4.000',
                quantity: differs ? '3.000' : '4.000',
                unitPriceCents: 350,
                amountCents: differs ? 1050 : 1400,
                effectiveQuantity: adjusted
                    ? '4.000'
                    : differs
                      ? '3.000'
                      : '4.000',
                effectiveAmountCents: adjusted ? 1400 : differs ? 1050 : 1400,
                differsFromAllocation: differs,
            },
            {
                productId: ids.beef,
                name: 'Vedella',
                unit: 'kg',
                ordered: '1.000',
                allocated: '1.120',
                quantity: state === 'draft-diff' ? '0.000' : '1.120',
                unitPriceCents: 2000,
                amountCents: state === 'draft-diff' ? 0 : 2240,
                effectiveQuantity: state === 'draft-diff' ? '0.000' : '1.120',
                effectiveAmountCents: state === 'draft-diff' ? 0 : 2240,
                differsFromAllocation: state === 'draft-diff',
            },
        ];
const basketState = nothing
    ? 'nothing_to_collect'
    : state.startsWith('draft') || late
      ? 'draft'
      : state === 'collected'
        ? 'collected'
        : 'validated';
const draft = basketState === 'draft';
const total = nothing ? 0 : late ? 800 : differs ? 1050 : 3640;
const basket = {
    id: ids.basket,
    householdId: ids.household,
    householdName: 'La Figuera',
    state: basketState,
    stockLines: [],
    returnLines: [],
    totalCents: total,
    balanceCents: 2000,
    projectedBalanceCents: 2000 - total,
    validatedAt: draft || nothing ? null : '2026-11-05T09:00:00+00:00',
    collectedAt: state === 'collected' ? '2026-11-05T12:00:00+00:00' : null,
    effectiveTotalCents: adjusted ? total + 350 : total,
    adjustments: adjusted
        ? [
              {
                  id: '0198f0a0-0000-7000-8000-000000000220',
                  amountCents: -350,
                  reason: 'Correction: Pa de pagès',
                  recordedBy: 'Gestora',
                  occurredAt: '2026-11-05T11:00:00+00:00',
                  lines: [
                      { stockProductId: ids.bread, quantityDelta: '1.000' },
                  ],
              },
          ]
        : [],
    collectionEvents:
        state === 'collected'
            ? [
                  {
                      id: '0198f0a0-0000-7000-8000-000000000221',
                      type: 'collected',
                      recordedBy: 'Anna',
                      occurredAt: '2026-11-05T12:00:00+00:00',
                      reason: null,
                  },
              ]
            : [],
    pendingCollection: basketState === 'validated',
    weeklyLines,
    kind: 'weekly',
    late,
};
// The text of errors.RedistributionBlockedByValidatedBasket in lang/ca/errors.php.
const rejection =
    'Un cistell ja validat conté aquest producte, així que no es pot redistribuir. Corregeix aquest cistell des de les seves correccions.';
const household = { id: ids.household, name: 'La Figuera', active: true };
const reception = {
    cycleId: ids.cycle,
    producerId: ids.producer,
    producerName: 'Forn del Poble',
    pickupDate: '2026-11-05',
    unorderedOptions: [],
    products: [
        {
            productId: ids.beef,
            name: 'Vedella',
            unit: 'kg',
            variable: true,
            nobodyOrdered: false,
            ordered: '1.000',
            received: '1.000',
            allocated: '1.000',
            delivered: '1.120',
            difference: '-0.120',
            balanced: false,
            balancedForDistribution: true,
            hasManualAllocations: false,
            hasAdjustments: true,
            allocations: [
                {
                    householdId: ids.household,
                    householdName: 'La Figuera',
                    ordered: '1.000',
                    allocated: '1.000',
                    byHand: false,
                    delivered: '1.120',
                    adjusted: true,
                },
            ],
        },
    ],
};
const pickups = (
    state === 'late-closed'
        ? [
              ['validated', 'La Figuera', 3640, false],
              ['draft', 'La Figuera', 800, true],
              [
                  'collected',
                  'Mas Roig amb un nom de llar prou llarg per comprovar el sagnat',
                  1400,
                  false,
              ],
              ['nothing_to_collect', 'Cal Pere', 0, false],
          ]
        : [
              ['draft', 'Casa Alba', 1050, false],
              ['validated', 'La Figuera', 3640, false],
              ['collected', 'Mas Roig', 1400, false],
              ['nothing_to_collect', 'Cal Pere', 0, false],
          ]
).map(([status, name, totalCents, late], index) => ({
    basketId: `basket-${index}`,
    householdId: late ? 'household-0' : `household-${index}`,
    householdName: name,
    status,
    totalCents,
    late,
}));
const order = {
    cycleId: ids.cycle,
    pickupDate: '2026-11-05',
    cancelled: false,
    producers: [
        {
            producerId: ids.producer,
            producerName: 'Forn del Poble',
            open: false,
            products: [],
        },
    ],
    lines: [],
    basketId: ids.basket,
};

const pages: Record<string, { component: string; props: object }> = {
    finance: {
        component: 'Finance/Baskets/Show',
        props: {
            basket,
            containerBalance: 3,
            stockProducts: [],
            returnItems: [],
        },
    },
    member: { component: 'Member/Baskets/Show', props: { basket } },
    reception: {
        component: 'Manage/Reception/Show',
        props: {
            reception,
            distribution: {
                validated: false,
                distributed: false,
                settled: true,
                closed: false,
            },
            households: [household],
        },
    },
    pickups: {
        component: 'Manage/Pickups/Index',
        props: { cycleId: ids.cycle, closed: state === 'late-closed', pickups },
    },
    orders: {
        component: 'Orders/Edit',
        props: { order, preview: { totalCents: 0, amountCents: {} } },
    },
};
const selected = pages[screen];
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const areas: Record<string, { label: string; href: string }> = {
    member: { label: 'Orders', href: '/' },
    manager: { label: 'Manage', href: '/manage/cycles' },
    finance: { label: 'Finance', href: '/finance/baskets' },
};
const page = {
    component: selected.component,
    url: location.pathname + location.search,
    version: 'dra-214',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors: state === 'rejected' ? { domain: rejection } : {},
        auth: {
            user: {
                id: '0198f0a0-0000-7000-8000-000000000232',
                name: 'Anna Soler',
                email: 'anna@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: [role],
        },
        roleAreas: [{ role, ...areas[role] }],
        ...selected.props,
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
}).then(() => {
    document.body.dataset.visualReady = `${screen}-${state}`;
});

import { createApp, defineComponent, h } from 'vue';
import { page } from './inertia';
import AppLayout from '@/layouts/AppLayout.vue';
import Accounts from '@/pages/Manage/Accounts/Index.vue';
import Households from '@/pages/Manage/Households/Index.vue';
import Household from '@/pages/Manage/Households/Show.vue';
import Producers from '@/pages/Manage/Producers/Index.vue';
import Cycles from '@/pages/Manage/Cycles/Index.vue';
import Cycle from '@/pages/Manage/Cycles/Show.vue';
import Settings from '@/pages/Manage/Settings.vue';
import Stock from '@/pages/Manage/Stock/Index.vue';
import StockCreate from '@/pages/Manage/Stock/Create.vue';
import StockEdit from '@/pages/Manage/Stock/Edit.vue';
import Movements from '@/pages/Manage/Stock/Movements.vue';
import ReturnItems from '@/pages/Manage/ReturnItems/Index.vue';
import ReturnCreate from '@/pages/Manage/ReturnItems/Create.vue';
import ReturnEdit from '@/pages/Manage/ReturnItems/Edit.vue';
import Wallet from '@/pages/Wallet/Statement.vue';
import { installTranslations } from '@/lib/i18n';
import { updateTheme } from '@/composables/useAppearance';
import '../../resources/css/app.css';

const query = new URLSearchParams(location.search);
const scenario = query.get('scenario') ?? 'accounts';
const isEmpty = query.get('empty') === '1';
const isCancelled = query.get('cancelled') === '1';
const isInactive = query.get('inactive') === '1';
const manyProducts = query.get('many') === '1';
const longName = query.get('long') === '1';
const name = longName ? 'H'.repeat(59) : 'Casa Riera';
const householdSummary = {
    id: 'home',
    name,
    active: !isInactive,
    memberCount: 1,
};
const settlement = {
    householdId: 'home',
    householdName: name,
    balanceCents: 1250,
    pendingPickups: [],
};
const member = {
    accountId: 'member',
    name: 'Anna Riera',
    email: 'anna@example.test',
    invitationPending: true,
    invitationExpiresAt: '2026-11-01T10:00:00Z',
    invitationExpired: false,
};
const stockProduct = {
    id: 'honey',
    name: longName ? 'P'.repeat(59) : 'Mel de muntanya',
    unit: 'jar',
    price: '6.50',
    variable: false,
    containersPerUnit: '1',
    active: !isInactive,
    available: '9',
};
const returnItem = {
    id: 'jar',
    name: longName ? 'R'.repeat(59) : 'Glass jar',
    amount: '0.50',
    amountCents: 50,
    active: !isInactive,
};
const cycle = {
    id: 'cycle',
    pickupDate: '2026-11-07',
    cancelled: isCancelled,
    closed: false,
    participants: [
        {
            producerId: 'grower',
            producerName: 'Grower',
            participating: true,
            cutoff: '2026-11-06 20:00',
            open: true,
        },
    ],
};
const summary = {
    cycleId: 'cycle',
    pickupDate: cycle.pickupDate,
    cancelled: isCancelled,
    producers:
        isCancelled || isEmpty
            ? []
            : [
                  {
                      producerId: 'grower',
                      producerName: 'Grower',
                      products: [
                          {
                              productId: 'bread',
                              name: 'Pa de pagès',
                              quantity: '5.000',
                              unit: 'unit',
                              households: [
                                  {
                                      householdId: 'home',
                                      householdName: name,
                                      quantity: '5.000',
                                  },
                              ],
                          },
                      ],
                  },
              ],
};
const wallet = {
    balance: '12.50',
    movements: [
        {
            id: 'topup',
            type: 'top_up',
            amount: '12.50',
            occurredAt: '2026-10-24 12:30',
            note: 'Transfer',
            cycleId: null,
        },
    ],
};

const views: Record<
    string,
    { component: string; view: unknown; props: Record<string, unknown> }
> = {
    accounts: {
        component: 'Manage/Accounts/Index',
        view: Accounts,
        props: {
            accounts: isEmpty
                ? []
                : [
                      {
                          accountId: 'member',
                          name: member.name,
                          email: member.email,
                          roles: ['manager'],
                          householdId: 'home',
                          householdName: name,
                          active: true,
                          invitationPending: false,
                      },
                  ],
            households: [householdSummary],
        },
    },
    households: {
        component: 'Manage/Households/Index',
        view: Households,
        props: {
            households: isEmpty ? [] : [householdSummary],
            toSettle: isEmpty ? [] : [settlement],
        },
    },
    household: {
        component: 'Manage/Households/Show',
        view: Household,
        props: {
            household: {
                id: 'home',
                name,
                active: !isInactive,
                members: isInactive ? [] : [member],
            },
            otherHouseholds: [
                {
                    id: 'other',
                    name: 'Casa Alzina',
                    active: true,
                    memberCount: 0,
                },
            ],
            deactivationWarning: settlement,
        },
    },
    producers: {
        component: 'Manage/Producers/Index',
        view: Producers,
        props: {
            producers: isEmpty
                ? []
                : [
                      {
                          id: 'grower',
                          name: 'Grower',
                          active: !isInactive,
                          cutoffDaysBefore: 3,
                          cutoffTime: '20:00',
                          accountEmail: null,
                          accountInvitationPending: false,
                      },
                  ],
        },
    },
    cycles: {
        component: 'Manage/Cycles/Index',
        view: Cycles,
        props: {
            cycles: isEmpty ? [] : [cycle],
            producers: [{ id: 'grower', name: 'Grower' }],
            pickupWeekday: 6,
        },
    },
    cycle: {
        component: 'Manage/Cycles/Show',
        view: Cycle,
        props: {
            summary,
            containers: {
                total: isCancelled ? 0 : 5,
                households: isCancelled
                    ? []
                    : [{ householdId: 'home', householdName: name, count: 5 }],
            },
        },
    },
    settings: {
        component: 'Manage/Settings',
        view: Settings,
        props: { pickupWeekday: isEmpty ? null : 6 },
    },
    stock: {
        component: 'Manage/Stock/Index',
        view: Stock,
        props: {
            products: isEmpty
                ? []
                : manyProducts
                  ? Array.from({ length: 20 }, (_, index) => ({
                        ...stockProduct,
                        id: `stock-${index + 1}`,
                        name: `Product ${index + 1}`,
                    }))
                  : [stockProduct],
        },
    },
    'stock-create': {
        component: 'Manage/Stock/Create',
        view: StockCreate,
        props: {},
    },
    'stock-edit': {
        component: 'Manage/Stock/Edit',
        view: StockEdit,
        props: { product: stockProduct },
    },
    movements: {
        component: 'Manage/Stock/Movements',
        view: Movements,
        props: {
            product: stockProduct,
            cycles: [{ id: 'cycle', pickupDate: cycle.pickupDate }],
        },
    },
    returns: {
        component: 'Manage/ReturnItems/Index',
        view: ReturnItems,
        props: { items: isEmpty ? [] : [returnItem] },
    },
    'return-create': {
        component: 'Manage/ReturnItems/Create',
        view: ReturnCreate,
        props: {},
    },
    'return-edit': {
        component: 'Manage/ReturnItems/Edit',
        view: ReturnEdit,
        props: { item: returnItem },
    },
    wallet: {
        component: 'Wallet/Statement',
        view: Wallet,
        props: { wallet, household: { id: 'home', name } },
    },
};
const selected = views[scenario] ?? views.accounts;
page.component = selected.component;
page.props.roleAreas = [
    { role: 'manager', label: 'Manager', href: '/manage/cycles' },
];
page.url = '/' + scenario;
updateTheme(
    query.get('appearance') === 'system'
        ? 'system'
        : query.get('mode') === 'dark'
          ? 'dark'
          : query.get('mode') === 'system'
            ? 'system'
            : 'light',
);
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '200%' : '100%';

const Root = defineComponent({
    setup() {
        return () =>
            h(AppLayout, null, {
                default: () => h(selected.view as never, selected.props),
            });
    },
});

const app = createApp(Root);
app.config.globalProperties.$page = page;
installTranslations(app);
app.mount('#app');
document.documentElement.dataset.fixtureQuery = location.search;

function setVisualFocusTarget(): void {
    const controls = [
        ...document.querySelectorAll<HTMLElement>(
            '.pilot-page a[href], .pilot-page button:not([disabled]), .pilot-page input:not([disabled]):not([type="hidden"]), .pilot-page select:not([disabled])',
        ),
    ].filter((control) => control.getClientRects().length > 0);
    controls.at(-1)?.setAttribute('data-visual-focus-last', 'true');
}

function prepareVisualState(): void {
    if (query.get('filled') === '1') {
        for (const [kind, value] of [
            ['date', '2026-11-06'],
            ['time', '20:00'],
        ]) {
            const input = document.querySelector<HTMLInputElement>(
                `[data-test="cutoff-${kind}-cycle-grower"]`,
            );
            if (!input) continue;
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    if (query.get('selected') === '1') {
        const select =
            document.querySelector<HTMLSelectElement>('#adjustment-cycle');
        if (select) {
            select.value = 'cycle';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    if (
        query.get('dialog') === '1' &&
        !document.querySelector('[data-slot="dialog-content"]')
    ) {
        document
            .querySelector<HTMLElement>('[data-test="cancel-cycle"]')
            ?.click();
    }

    setVisualFocusTarget();
    document.documentElement.dataset.visualReady = 'true';
}

requestAnimationFrame(() => requestAnimationFrame(prepareVisualState));

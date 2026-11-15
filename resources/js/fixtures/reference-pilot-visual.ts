import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CyclesIndex from '@/pages/Manage/Cycles/Index.vue';
import ProducersIndex from '@/pages/Manage/Producers/Index.vue';
import CatalogueDashboard from '@/pages/Dashboard.vue';
import CatalogueProducersIndex from '@/pages/Catalogue/Producers/Index.vue';
import CatalogueProducts from '@/pages/Catalogue/Producers/Products.vue';
import CatalogueWeeklyCreate from '@/pages/Catalogue/Producers/WeeklyProduct/Create.vue';
import CatalogueWeeklyEdit from '@/pages/Catalogue/Producers/WeeklyProduct/Edit.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const requested = query.get('screen') ?? '';
const catalogueScreens = [
    'catalogue-producers',
    'catalogue-products',
    'catalogue-weekly-create',
    'catalogue-weekly-edit',
    'catalogue-dashboard',
];
const isCatalogue = catalogueScreens.includes(requested);
const screen = requested === 'producers' ? 'producers' : 'cycles';
const catalogueRole = query.get('role') === 'manager' ? 'manager' : 'catalogue';
const emptyState = query.get('state') === 'empty';
const longName = query.get('state') === 'long';
const weekly = (id: string, name: string, extra = {}) => ({
    id,
    producerId: 'producer-1',
    name,
    unit: 'kg',
    price: '3.80',
    variable: false,
    containersPerUnit: null,
    active: true,
    stock: false,
    used: false,
    can: {
        edit: true,
        changeClassification: catalogueRole === 'manager',
        delete: true,
    },
    ...extra,
});
const catalogueProps: Record<string, Record<string, unknown>> = {
    'catalogue-producers': {
        producers: [
            {
                id: 'producer-1',
                name: longName
                    ? 'Cooperativa de pagesia agroecològica de la vall del riu i les muntanyes'
                    : 'La Figuera',
                active: true,
                cutoffDaysBefore: 3,
                cutoffTime: '20:00',
                accountEmail: null,
                accountInvitationPending: false,
            },
            {
                id: 'producer-2',
                name: 'Closed dairy',
                active: false,
                cutoffDaysBefore: 2,
                cutoffTime: '12:00',
                accountEmail: null,
                accountInvitationPending: false,
            },
        ],
    },
    'catalogue-products': {
        producer: { id: 'producer-1', name: 'La Figuera' },
        canCreateStock: catalogueRole === 'manager',
        products: emptyState
            ? []
            : [
                  weekly(
                      'w-1',
                      longName
                          ? 'Tomàquet de penjar de la varietat antiga de la vall'
                          : 'Tomàquet',
                  ),
                  weekly('w-2', 'Bread', { active: false }),
                  weekly('s-1', 'Honey', {
                      stock: true,
                      can: {
                          edit: catalogueRole === 'manager',
                          changeClassification: catalogueRole === 'manager',
                          delete: catalogueRole === 'manager',
                      },
                  }),
              ],
    },
    'catalogue-weekly-create': {
        producer: { id: 'producer-1', name: 'La Figuera' },
    },
    'catalogue-weekly-edit': {
        producer: { id: 'producer-1', name: 'La Figuera' },
        product: {
            id: 'w-1',
            name: 'Tomàquet',
            unit: 'kg',
            price: '3.80',
            variable: false,
            containersPerUnit: null,
            active: true,
        },
        used: false,
        canDelete: true,
    },
    'catalogue-dashboard': {
        roles: ['catalogue'],
        isManager: false,
        isMember: false,
        can: { showCaughtUp: true, showNoContext: false },
        areaShortcuts: [
            {
                role: 'catalogue',
                label: 'Catalogue',
                href: '/catalogue/producers',
            },
        ],
    },
};
const catalogueComponents = {
    'catalogue-producers': [
        'Catalogue/Producers/Index',
        CatalogueProducersIndex,
        '/catalogue/producers',
    ],
    'catalogue-products': [
        'Catalogue/Producers/Products',
        CatalogueProducts,
        '/catalogue/producers/producer-1/products',
    ],
    'catalogue-weekly-create': [
        'Catalogue/Producers/WeeklyProduct/Create',
        CatalogueWeeklyCreate,
        '/catalogue/producers/producer-1/products/weekly/create',
    ],
    'catalogue-weekly-edit': [
        'Catalogue/Producers/WeeklyProduct/Edit',
        CatalogueWeeklyEdit,
        '/catalogue/producers/producer-1/products/weekly/w-1/edit',
    ],
    'catalogue-dashboard': ['Dashboard', CatalogueDashboard, '/dashboard'],
} as const;
const state = query.get('state');
const producerName =
    state === 'long'
        ? 'Cooperativa de pagesia agroecològica de la vall del riu i les muntanyes'
        : 'La Figuera';

const sendStates = ['send-order', 'send-order-confirm'];
const isSendState = sendStates.includes(state ?? '');

const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'light' || mode === 'dark' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const catalogueEntry = isCatalogue
    ? catalogueComponents[requested as keyof typeof catalogueComponents]
    : null;
const page = {
    component:
        catalogueEntry?.[0] ??
        (screen === 'cycles'
            ? 'Manage/Cycles/Index'
            : 'Manage/Producers/Index'),
    url:
        catalogueEntry?.[2] ??
        (screen === 'cycles' ? '/manage/cycles' : '/manage/producers'),
    version: 'reference-pilot',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user: {
                id: 'manager-1',
                name: 'Cooperative manager',
                email: 'manager@example.test',
                avatar: '',
                email_verified_at: null,
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: isCatalogue ? [catalogueRole] : ['manager'],
        },
        roleAreas: isCatalogue
            ? [
                  catalogueRole === 'manager'
                      ? {
                            role: 'manager',
                            label: 'Management',
                            href: '/manage/cycles',
                        }
                      : {
                            role: 'catalogue',
                            label: 'Catalogue',
                            href: '/catalogue/producers',
                        },
              ]
            : [
                  {
                      role: 'manager',
                      label: 'Cooperative',
                      href: '/manage/cycles',
                  },
              ],
        sidebarOpen: true,
        ...(isCatalogue
            ? catalogueProps[requested]
            : screen === 'cycles'
              ? {
                    cycles:
                        state === 'empty'
                            ? []
                            : [
                                  {
                                      id: 'cycle-1',
                                      pickupDate: '2026-11-05',
                                      cancelled: false,
                                      closed: false,
                                      settled: false,
                                      participants: [
                                          {
                                              producerId: 'producer-1',
                                              producerName,
                                              participating: true,
                                              cutoff: '2026-11-03T20:00:00+01:00',
                                              open:
                                                  !isSendState &&
                                                  state !== 'sent',
                                              sent: state === 'sent',
                                              canSendManually: isSendState,
                                          },
                                      ],
                                  },
                              ],
                    producers: [{ id: 'producer-1', name: producerName }],
                    pickupWeekday: 4,
                    pendingCollections: {},
                }
              : {
                    producers:
                        state === 'empty'
                            ? []
                            : [
                                  {
                                      id: 'producer-1',
                                      name: producerName,
                                      active: true,
                                      cutoffDaysBefore: 3,
                                      cutoffTime: '20:00',
                                      accountEmail: null,
                                      accountInvitationPending: false,
                                  },
                              ],
                }),
    },
};

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

void createInertiaApp({
    resolve: (name) =>
        // The fixture supplies the required page props; Inertia's resolver type
        // erases the page component's specific props.
        (catalogueEntry
            ? catalogueEntry[1]
            : name === 'Manage/Cycles/Index'
              ? CyclesIndex
              : ProducersIndex) as unknown as DefineComponent,
    layout: () => AppLayout,
    withApp: (app, { page }) => installTranslations(app, page.props.locale),
    progress: false,
}).then(() => {
    const restoreFixtureUrl = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restoreFixtureUrl();
    window.setTimeout(restoreFixtureUrl, 100);
    window.setTimeout(restoreFixtureUrl, 500);
    if (
        ![
            'dialog',
            'drawer',
            'cycle-management',
            'producer-management',
            ...sendStates,
        ].includes(state ?? '')
    )
        return;
    const openRequestedState = () => {
        const trigger = document.querySelector<HTMLButtonElement>(
            state === 'cycle-management'
                ? '[data-test="manage-cycle-1"]'
                : state === 'producer-management' || isSendState
                  ? '[data-test="manage-cycle-1-producer-1"]'
                  : state === 'dialog'
                    ? '[data-test="open-create-cycle"]'
                    : 'button[data-sidebar="trigger"]',
        );
        if (!trigger) return false;
        trigger.click();
        if (state === 'cycle-management') {
            window.setTimeout(() => {
                const details = document.querySelector<HTMLDetailsElement>(
                    'dialog[open] details',
                );
                if (details) details.open = true;
            }, 50);
        }
        if (state === 'send-order-confirm') {
            window.setTimeout(() => {
                document
                    .querySelector<HTMLButtonElement>(
                        '[data-test="send-order-button"]',
                    )
                    ?.click();
            }, 50);
        }
        return true;
    };
    if (openRequestedState()) return;
    const observer = new MutationObserver(() => {
        if (openRequestedState()) observer.disconnect();
    });
    observer.observe(document.querySelector('#app')!, {
        childList: true,
        subtree: true,
    });
});

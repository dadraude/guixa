import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(location.search);
const role = query.get('role') ?? 'finance';
const state = query.get('state') ?? 'corrected';
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';
const longText =
    'Oli d’oliva verge extra de primera premsada en fred d’una finca familiar amb un nom molt llarg que ha de continuar sent llegible en una pantalla estreta';
const reversed = state === 'reversed';
const partial = ['partial', 'long', 'reversed'].includes(state);
const oilEffective = reversed ? '0.000' : partial ? '2.000' : '3.000';
const honeyEffective = reversed ? '0.000' : '1.000';
const adjustmentLines = [
    { stockProductId: oilId(), quantityDelta: reversed ? '-3.000' : '-1.000' },
    ...(reversed
        ? [{ stockProductId: honeyId(), quantityDelta: '-1.000' }]
        : []),
];
function oilId() {
    return '0198f0a0-0000-7000-8000-000000000231';
}
function honeyId() {
    return '0198f0a0-0000-7000-8000-000000000233';
}
const basket = {
    id: '0198f0a0-0000-7000-8000-000000000229',
    householdId: '0198f0a0-0000-7000-8000-000000000230',
    householdName: 'La Figuera',
    state: 'validated',
    stockLines: [
        {
            stockProductId: oilId(),
            name: state === 'long' ? longText : 'Oli',
            unit: 'ampolla',
            quantity: '3.000',
            unitPriceCents: 1200,
            amountCents: 3600,
            effectiveQuantity: oilEffective,
            effectiveAmountCents: Number(oilEffective) * 1200,
        },
        {
            stockProductId: honeyId(),
            name: 'Mel',
            unit: 'pot',
            quantity: '1.000',
            unitPriceCents: 500,
            amountCents: 500,
            effectiveQuantity: honeyEffective,
            effectiveAmountCents: Number(honeyEffective) * 500,
        },
    ],
    returnLines: [],
    totalCents: 4100,
    effectiveTotalCents: reversed ? 0 : partial ? 2900 : 4100,
    adjustments: partial
        ? [
              {
                  id: '0198f0a0-0000-7000-8000-000000000234',
                  amountCents: reversed ? 4100 : 1200,
                  reason: state === 'long' ? longText : 'Una ampolla ha tornat',
                  recordedBy: 'Finances',
                  occurredAt: '2026-11-02T11:00:00+00:00',
                  lines: adjustmentLines,
              },
          ]
        : [],
    collectionEvents: [],
    pendingCollection: !reversed,
    balanceCents: -4100,
    projectedBalanceCents: -4100,
    validatedAt: '2026-11-02T09:00:00+00:00',
    collectedAt: null,
};
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const page = {
    component: `${role === 'member' ? 'Member' : 'Finance'}/Baskets/Show`,
    url: location.pathname + location.search,
    version: 'dra-228',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors:
            state === 'error'
                ? {
                      domain: 'No es pot retornar més quantitat de la que queda a la cistella.',
                  }
                : {},
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
        roleAreas: [
            {
                role,
                label: role === 'member' ? 'Orders' : 'Finance',
                href: role === 'member' ? '/' : '/finance/baskets',
            },
        ],
        basket,
        containerBalance: 3,
        stockProducts: [],
        returnItems: [],
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
    document.body.dataset.visualReady = state;
});

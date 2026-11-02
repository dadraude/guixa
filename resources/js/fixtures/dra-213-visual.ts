import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(location.search);
const role = query.get('role') === 'member' ? 'member' : 'finance';
const state = query.get('state') ?? 'corrected';
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';
const basketId = '0198f0a0-0000-7000-8000-000000000213';
const householdId = '0198f0a0-0000-7000-8000-000000000214';
const correctionId = '0198f0a0-0000-7000-8000-000000000215';
const longText =
    'Correcció de l’import per una incidència documentada a la cistella original amb un motiu molt llarg que ha de continuar sent llegible en pantalles estretes';
const corrected = !['empty', 'draft'].includes(state);
const basket = {
    id: basketId,
    householdId,
    householdName: state === 'long' ? longText : 'La Figuera',
    state:
        state === 'draft'
            ? 'draft'
            : state === 'collected'
              ? 'collected'
              : 'validated',
    stockLines: [
        {
            stockProductId: '0198f0a0-0000-7000-8000-000000000216',
            name: 'Oli',
            unit: 'ampolla',
            quantity: '1.000',
            unitPriceCents: 1200,
            amountCents: 1200,
        },
    ],
    returnLines: [],
    totalCents: 1200,
    effectiveTotalCents: corrected ? 1000 : 1200,
    adjustments: corrected
        ? [
              {
                  id: correctionId,
                  amountCents: 200,
                  reason: state === 'long' ? longText : 'Preu corregit',
                  recordedBy: 'Finances',
                  occurredAt: '2026-11-01T11:00:00+00:00',
              },
          ]
        : [],
    balanceCents: -1000,
    projectedBalanceCents: -1000,
    validatedAt: state === 'draft' ? null : '2026-11-01T10:00:00+00:00',
    collectedAt: state === 'collected' ? '2026-11-01T12:00:00+00:00' : null,
};
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const page = {
    component: `${role === 'member' ? 'Member' : 'Finance'}/Baskets/Show`,
    url: location.pathname + location.search,
    version: 'dra-213',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors:
            state === 'error' ? { domain: 'La correcció no és vàlida.' } : {},
        auth: {
            user: {
                id: '0198f0a0-0000-7000-8000-000000000217',
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
    if (state === 'ready') {
        for (const [selector, value] of [
            ['#correction-amount', '200'],
            ['#correction-reason', 'Preu corregit'],
        ]) {
            const field = document.querySelector(selector) as
                | HTMLInputElement
                | HTMLTextAreaElement
                | null;
            if (field) {
                field.value = value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    }
    document.body.dataset.visualReady = state;
});

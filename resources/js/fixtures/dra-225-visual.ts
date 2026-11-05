import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const view = query.get('view') === 'detail' ? 'Show' : 'Index';
const state = query.get('state') ?? 'populated';
const role = query.get('role') === 'finance' ? 'finance' : 'member';
const pending = query.get('pending') === '1';
const mode = query.get('mode');
const fixtureUrl = window.location.href;
const fixturePath = window.location.pathname + window.location.search;
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';
const householdId = '0198f0a0-0000-7000-8000-000000000101';
const basketId = '0198f0a0-0000-7000-8000-000000000102';
const itemId = '0198f0a0-0000-7000-8000-000000000103';
const productId = '0198f0a0-0000-7000-8000-000000000104';
const longText =
    'La Figuera de la Vall de les terres altes amb un nom extraordinàriament llarg que descriu la casa i els seus productes';
const longState = state === 'long';
const householdName = longState ? longText : 'La Figuera';
const basketState = pending
    ? 'validated'
    : ['empty-draft', 'long', 'populated'].includes(state)
      ? 'draft'
      : state;
const stockLines =
    state === 'empty-draft' || state === 'cancelled'
        ? []
        : [
              {
                  stockProductId: productId,
                  name: longState ? longText : 'Oli de la cooperativa',
                  unit: 'ampolla',
                  quantity: '2.000',
                  unitPriceCents: 1200,
                  amountCents: 2400,
              },
          ];
const returnLines =
    state === 'empty-draft' || state === 'cancelled'
        ? []
        : [
              {
                  returnItemId: itemId,
                  name: longState ? longText : 'Pot de vidre',
                  amountCents: 50,
                  quantity: 2,
                  creditCents: 100,
              },
          ];
const totalCents = stockLines.length ? 2300 : 0;
const basket = {
    id: basketId,
    householdId,
    householdName,
    state: basketState,
    stockLines,
    returnLines,
    totalCents,
    balanceCents: -500,
    projectedBalanceCents: -500 - totalCents,
    validatedAt:
        basketState === 'validated' || basketState === 'collected'
            ? '2026-10-30T10:00:00+00:00'
            : null,
    collectedAt:
        basketState === 'collected' ? '2026-10-31T11:00:00+00:00' : null,
};
const summaryOf = (id: string, createdAt: string, validatedAt: string) => ({
    id,
    householdName,
    state: basketState,
    status: basketState,
    totalCents,
    createdAt,
    pendingCollection: basketState === 'validated',
    validatedAt: basketState === 'validated' ? validatedAt : null,
});
const summaries =
    state === 'empty'
        ? []
        : pending
          ? [
                summaryOf(basketId, '2026-10-30 10:00', '2026-11-05 08:15'),
                summaryOf(
                    '0198f0a0-0000-7000-8000-000000000106',
                    '2026-11-02 09:30',
                    '2026-11-05 12:40',
                ),
                summaryOf(
                    '0198f0a0-0000-7000-8000-000000000107',
                    '2026-11-04 16:45',
                    '2026-11-06 17:05',
                ),
            ]
          : [summaryOf(basketId, '2026-10-30 10:00', '')];
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const page = {
    component: `${role === 'finance' ? 'Finance' : 'Member'}/Baskets/${view}`,
    url: fixturePath,
    version: 'dra-225',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors: {},
        auth: {
            user: {
                id: '0198f0a0-0000-7000-8000-000000000105',
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
                label: role === 'finance' ? 'Finance' : 'Orders',
                href: role === 'finance' ? '/finance/baskets' : '/',
            },
        ],
        ...(view === 'Show' ? { basket } : { baskets: summaries, pending }),
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
    window.history.replaceState(window.history.state, '', fixtureUrl);
});

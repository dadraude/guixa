import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const components = import.meta.glob('../pages/Finance/Baskets/Show.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;

const query = new URLSearchParams(window.location.search);
const state = query.get('state') ?? 'populated';
const role = query.get('role') === 'manager' ? 'manager' : 'finance';
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const householdId = '0198f0a0-0000-7000-8000-000000000032';
const basketId = '0198f0a0-0000-7000-8000-000000000033';
const productId = '0198f0a0-0000-7000-8000-000000000034';
const returnItemId = '0198f0a0-0000-7000-8000-000000000035';
const longName =
    'Pot de vidre retornable de la Vall de les terres altes amb etiqueta extraordinàriament llarga';
const showStock = ['populated', 'netzero', 'error', 'long'].includes(state);
const showReturn = ['populated', 'credit', 'netzero', 'error', 'long'].includes(
    state,
);
const basketState = ['credit', 'netzero'].includes(state)
    ? 'validated'
    : 'draft';
const stockAmount = state === 'netzero' ? 50 : 1000;
const returnQuantity =
    state === 'populated' || state === 'error' || state === 'long' ? 4 : 1;
const totalCents =
    (showStock ? stockAmount : 0) - (showReturn ? returnQuantity * 50 : 0);
const balanceCents = basketState === 'validated' ? -totalCents : 0;
const basket = {
    id: basketId,
    householdId,
    householdName:
        state === 'long'
            ? 'La llar de la Vall de les terres altes amb un nom extraordinàriament llarg'
            : 'La Figuera',
    state: basketState,
    stockLines: showStock
        ? [
              {
                  stockProductId: productId,
                  name: 'Oli',
                  unit: 'ampolla',
                  quantity: '1.000',
                  unitPriceCents: stockAmount,
                  amountCents: stockAmount,
              },
          ]
        : [],
    returnLines: showReturn
        ? [
              {
                  returnItemId,
                  name: state === 'long' ? longName : 'Pot de vidre',
                  amountCents: 50,
                  quantity: returnQuantity,
                  creditCents: returnQuantity * 50,
              },
          ]
        : [],
    totalCents,
    balanceCents,
    projectedBalanceCents:
        basketState === 'draft' ? balanceCents - totalCents : balanceCents,
    validatedAt:
        basketState === 'validated' ? '2026-10-30T09:00:00+01:00' : null,
    collectedAt: null,
};
const props = {
    name: 'Guixa',
    locale: 'ca',
    sidebarOpen: true,
    errors:
        state === 'error'
            ? {
                  domain: 'No es pot validar la cistella.',
                  return_quantity: 'Quantitat no vàlida.',
              }
            : {},
    auth: {
        user: {
            id: '0198f0a0-0000-7000-8000-000000000031',
            name: 'Marta Soler',
            email: 'marta@example.test',
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
            label: role === 'finance' ? 'Finance' : 'Management',
            href: role === 'finance' ? '/finance/baskets' : '/manage/cycles',
        },
    ],
    basket,
    containerBalance: 3,
    stockProducts:
        basketState === 'draft' && state !== 'disabled'
            ? [
                  {
                      id: productId,
                      producerId: 'producer',
                      name: 'Oli',
                      unit: 'ampolla',
                      price: '10.00',
                      variable: false,
                      containersPerUnit: null,
                      active: true,
                      available: '5.000',
                  },
              ]
            : [],
    returnItems:
        basketState === 'draft' && state !== 'disabled'
            ? [
                  {
                      id: returnItemId,
                      name: 'Pot de vidre',
                      amount: '0.50',
                      amountCents: 50,
                      active: true,
                  },
              ]
            : [],
};
const page = {
    component: 'Finance/Baskets/Show',
    url: window.location.pathname + window.location.search,
    version: 'dra-212',
    props,
};
const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

void createInertiaApp({
    resolve: () => components['../pages/Finance/Baskets/Show.vue'].default,
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
            '#main-content a[href],#main-content button,#main-content input',
        ),
    ].filter(
        (element) =>
            element.getClientRects().length > 0 &&
            element.tabIndex >= 0 &&
            !element.matches(':disabled') &&
            getComputedStyle(element).pointerEvents !== 'none',
    );
    for (const element of document.querySelectorAll<HTMLElement>(
        '[data-visual-page-focus-first],[data-visual-page-focus-last]',
    )) {
        element.removeAttribute('data-visual-page-focus-first');
        element.removeAttribute('data-visual-page-focus-last');
    }
    if (focusable.length === 0)
        throw new Error(`No focusable T57 controls: ${state}`);
    focusable[0].dataset.visualPageFocusFirst = '';
    focusable.at(-1)!.dataset.visualPageFocusLast = '';
    document.body.dataset.visualReady = state;
});

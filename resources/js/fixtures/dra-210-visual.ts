import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { financeBasketRow } from './financeBasketRow';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const pageName = query.get('page') ?? 'Finance/Baskets/Index';
const state = query.get('state') ?? 'populated';
const role = query.get('role') === 'member' ? 'member' : 'manager';
const fixtureUrl = window.location.href;
const fixturePath = window.location.pathname + window.location.search;
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
if (!components[`../pages/${pageName}.vue`])
    throw new Error(`Unknown page: ${pageName}`);

const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const basketId = '0198f0a0-0000-7000-8000-000000000021';
const householdId = '0198f0a0-0000-7000-8000-000000000022';
const productId = '0198f0a0-0000-7000-8000-000000000023';
const longName =
    'La llar de la Vall de les terres altes amb un nom extraordinàriament llarg';
const householdName = state === 'long' ? longName : 'La Figuera';
const stockName =
    state === 'long'
        ? 'Mel artesanal de romaní de la Vall de les terres altes'
        : 'Mel de romaní';
const basketState =
    state === 'validated' || state === 'collected' || state === 'cancelled'
        ? state
        : state === 'collect-processing'
          ? 'validated'
          : 'draft';
const line = {
    stockProductId: productId,
    name: stockName,
    unit: 'pot',
    quantity: '2.000',
    unitPriceCents: 650,
    amountCents: 1300,
};
const stock = {
    id: productId,
    producerId: 'producer-one',
    name: stockName,
    unit: 'pot',
    price: '6.50',
    variable: false,
    containersPerUnit: '2.000',
    active: true,
    available: '12.000',
};
const totalCents = state === 'empty' || state === 'cancelled' ? 0 : 1300;
const balanceCents =
    basketState === 'draft' || basketState === 'cancelled' ? 500 : -800;
const basket = financeBasketRow({
    id: basketId,
    householdId,
    householdName,
    state: basketState,
    stockLines: state === 'empty' || state === 'cancelled' ? [] : [line],
    totalCents,
    balanceCents,
    projectedBalanceCents:
        basketState === 'draft' ? balanceCents - totalCents : balanceCents,
    validatedAt: basketState === 'draft' ? null : '2026-10-30T09:00:00+01:00',
    collectedAt:
        basketState === 'collected' ? '2026-10-30T10:00:00+01:00' : null,
});
const summaries =
    state === 'empty'
        ? []
        : state === 'long'
          ? Array.from({ length: 8 }, (_, index) => ({
                id: `${basketId}-${index}`,
                householdName: `${householdName} ${index + 1}`,
                state: 'validated',
                status: 'validated',
                totalCents: 1300,
                createdAt: '2026-10-30 09:00',
                pendingCollection: true,
                validatedAt: '2026-10-30 09:00',
            }))
          : [
                {
                    id: basketId,
                    householdName,
                    state: 'validated',
                    status: 'validated',
                    totalCents: 1300,
                    createdAt: '2026-10-30 09:00',
                    pendingCollection: true,
                    validatedAt: '2026-10-30 09:00',
                },
            ];
const wallet = {
    balance: '-8.00',
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
                              ? 'Compra independent de productes d’estoc de la llar de la Vall de les terres altes'
                              : 'Compra de cistella',
                      cycleId: null,
                      basketId,
                      sourceMovementId: null,
                      recordedBy: null,
                  },
                  ...(state === 'long'
                      ? [
                            {
                                id: 'movement-two',
                                type: 'charge',
                                amount: '-6.50',
                                occurredAt: '2026-10-30 10:00',
                                note: 'Segona compra independent',
                                cycleId: null,
                                basketId:
                                    '0198f0a0-0000-7000-8000-000000000024',
                                sourceMovementId: null,
                                recordedBy: null,
                            },
                        ]
                      : []),
              ],
};
const propsByPage: Record<string, Record<string, unknown>> = {
    'Finance/Baskets/Index': { baskets: summaries, pending: false },
    'Finance/Baskets/Create': {
        households:
            state === 'empty' ? [] : [{ id: householdId, name: householdName }],
    },
    'Finance/Baskets/Show': {
        basket,
        containerBalance: 3,
        stockProducts: [stock],
        returnItems: [],
    },
    'Wallet/Statement': {
        household:
            role === 'manager'
                ? { id: householdId, name: householdName }
                : null,
        wallet,
        canManageReceipts: role === 'manager',
        ...(role === 'manager'
            ? { householdReturnHref: `/manage/households/${householdId}` }
            : {}),
    },
};
const page = {
    component: pageName,
    url: fixturePath,
    version: 'dra-210',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors:
            state === 'error'
                ? {
                      domain: 'No hi ha prou estoc disponible per validar la cistella.',
                  }
                : {},
        auth: {
            user: {
                id: 'actor-one',
                name: role === 'manager' ? 'Gestora' : 'Membre',
                email: 'actor@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: [role],
        },
        roleAreas:
            role === 'manager'
                ? [
                      {
                          role: 'manager',
                          label: 'Cooperative',
                          href: '/manage/cycles',
                      },
                  ]
                : [{ role: 'member', label: 'Member', href: '/orders' }],
        sidebarOpen: true,
        ...propsByPage[pageName],
    },
};

const requests: string[] = [];
Object.assign(window, { dra210Requests: requests });
if (
    state === 'processing' ||
    state === 'collect-processing' ||
    (state === 'error' && pageName.endsWith('/Create'))
) {
    http.setClient({
        request: async (request) => {
            requests.push(`${request.method} ${request.url}`);
            if (state === 'processing' || state === 'collect-processing')
                return new Promise(() => undefined);
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    props: {
                        ...page.props,
                        errors: {
                            household_id: 'Aquesta llar no està disponible.',
                        },
                    },
                }),
                headers: { 'x-inertia': 'true' },
            };
        },
    });
}

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
    const waitFor = async (selector: string): Promise<HTMLElement> => {
        for (let attempt = 0; attempt < 100; attempt++) {
            const element = document.querySelector<HTMLElement>(selector);
            if (element) return element;
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
        throw new Error(`Visual element missing: ${selector}`);
    };
    await waitFor('#main-content');
    if (window.matchMedia('(max-width: 767px)').matches) {
        const toggle = await waitFor('button[data-sidebar="trigger"]');
        toggle.click();
        await waitFor('[data-sidebar="sidebar"][data-mobile="true"]');
        toggle.click();
        for (let attempt = 0; attempt < 100; attempt++) {
            if (
                !document.querySelector(
                    '[data-sidebar="sidebar"][data-mobile="true"]',
                )
            )
                break;
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
    }
    if (
        (state === 'processing' || state === 'error') &&
        pageName.endsWith('/Create')
    ) {
        (await waitFor('[data-test="household-select"]')).dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }),
        );
        const option = await waitFor('[role="option"]');
        option.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
        );
        await new Promise((resolve) => window.setTimeout(resolve, 50));
        (await waitFor('[data-test="create-basket"]')).click();
        if (state === 'processing')
            await waitFor('[data-test="create-basket"]:disabled');
        else {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (
                    [
                        ...document.querySelectorAll<HTMLElement>(
                            '#main-content p.text-destructive',
                        ),
                    ].some(
                        (element) =>
                            element.getClientRects().length > 0 &&
                            !!element.textContent?.trim(),
                    )
                )
                    break;
                await new Promise((resolve) => window.setTimeout(resolve, 25));
            }
            if (
                ![
                    ...document.querySelectorAll<HTMLElement>(
                        '#main-content p.text-destructive',
                    ),
                ].some(
                    (element) =>
                        element.getClientRects().length > 0 &&
                        !!element.textContent?.trim(),
                )
            )
                throw new Error('Create validation error was not visible');
        }
    } else if (state === 'processing' || state === 'collect-processing') {
        const selector =
            state === 'collect-processing'
                ? '[data-test="collect-basket"]'
                : '[data-test="validate-basket"]';
        (await waitFor(selector)).click();
        await waitFor(`${selector}:disabled`);
        const expected = state === 'collect-processing' ? 'put' : 'post';
        if (
            requests.length !== 1 ||
            !requests[0].startsWith(`${expected} `) ||
            !requests[0].endsWith(
                `/finance/baskets/${basketId}/${state === 'collect-processing' ? 'collection' : 'validation'}`,
            )
        )
            throw new Error(
                `Unexpected ${state} request: ${requests.join(', ')}`,
            );
    }
    for (let attempt = 0; attempt < 100; attempt++) {
        const main = document.querySelector('#main-content');
        if (main && getComputedStyle(main).pointerEvents !== 'none') break;
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
    if (focusable.length === 0 && role === 'member') {
        focusable.push(await waitFor('button[data-sidebar="trigger"]'));
    }
    if (focusable.length === 0)
        throw new Error(`No focusable controls: ${pageName}/${state}`);
    for (const element of document.querySelectorAll<HTMLElement>(
        '[data-visual-page-focus-first],[data-visual-page-focus-last]',
    )) {
        element.removeAttribute('data-visual-page-focus-first');
        element.removeAttribute('data-visual-page-focus-last');
    }
    focusable[0].setAttribute('data-visual-page-focus-first', '');
    focusable.at(-1)?.setAttribute('data-visual-page-focus-last', '');
    document.body.dataset.visualReady = state;
    window.history.replaceState(window.history.state, '', fixtureUrl);
});

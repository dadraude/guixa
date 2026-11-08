import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { financeBasketRow } from './financeBasketRow';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(location.search);
const target = query.get('page') ?? 'wallet';
const state = query.get('state') ?? 'populated';
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const longName =
    'Maria Dolors Soler i Puigdomènech de la Vall de les Terres Altes';
const authorName =
    state === 'long' ? longName : state === 'empty' ? '' : 'Anna Soler';
const role =
    target === 'member'
        ? 'member'
        : target === 'finance'
          ? 'finance'
          : 'finance';
const staff = target !== 'member';
const householdId = '0198f0a0-0000-7000-8000-000000000247';
const movement = (
    id: string,
    type: string,
    amount: string,
    occurredAt: string,
    sourceMovementId: string | null = null,
) => ({
    id,
    type,
    amount,
    occurredAt,
    note: null,
    sourceMovementId,
    basketId: null,
    cycleId: null,
    authorName,
});
const receiptId = '0198f0a0-0000-7000-8000-000000000248';
const wallet = {
    balance: '0.00',
    movements: [
        movement(
            '0198f0a0-0000-7000-8000-000000000249',
            'correction',
            '-50.00',
            '2026-10-30 11:00',
            receiptId,
        ),
        movement(receiptId, 'top_up', '50.00', '2026-10-30 10:00'),
    ],
};
const basket = financeBasketRow(
    {
        id: '0198f0a0-0000-7000-8000-000000000250',
        householdId,
        householdName: 'La Figuera',
        state: 'validated',
        stockLines: [
            {
                stockProductId: '0198f0a0-0000-7000-8000-000000000251',
                name: 'Oli',
                unit: 'ampolla',
                quantity: '3.000',
                unitPriceCents: 1200,
                amountCents: 3600,
            },
        ],
        returnLines: [],
        totalCents: 3600,
        effectiveTotalCents: 2400,
        adjustments: [
            {
                id: '0198f0a0-0000-7000-8000-000000000252',
                amountCents: 1200,
                reason: 'Una ampolla ha tornat',
                authorName,
                occurredAt: '2026-11-01T12:00:00+00:00',
                lines: [
                    {
                        stockProductId: '0198f0a0-0000-7000-8000-000000000251',
                        quantityDelta: '-1.000',
                    },
                ],
            },
        ],
        collectionEvents: [
            {
                id: '0198f0a0-0000-7000-8000-000000000253',
                type: 'collected',
                authorName,
                occurredAt: '2026-11-01T10:00:00+00:00',
                reason: null,
            },
            {
                id: '0198f0a0-0000-7000-8000-000000000254',
                type: 'corrected',
                authorName,
                occurredAt: '2026-11-01T11:00:00+00:00',
                reason: 'Marcada per error',
            },
        ],
        balanceCents: -2400,
        projectedBalanceCents: -2400,
        validatedAt: '2026-11-01T09:00:00+00:00',
        collectedAt: null,
    },
    staff ? 'staff' : 'member',
);
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const component =
    target === 'wallet'
        ? 'Wallet/Statement'
        : `${staff ? 'Finance' : 'Member'}/Baskets/Show`;
const page = {
    component,
    url: location.pathname + location.search,
    version: 'dra-247',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors: {},
        auth: {
            user: {
                id: '0198f0a0-0000-7000-8000-000000000255',
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
                label: staff ? 'Finance' : 'Orders',
                href: staff ? '/finance/baskets' : '/',
            },
        ],
        wallet,
        household:
            target === 'wallet'
                ? { id: householdId, name: 'La Figuera' }
                : null,
        canManageReceipts: target === 'wallet',
        receiptAction: `/finance/households/${householdId}/wallet/top-ups`,
        correctionAction: `/finance/households/${householdId}/wallet/corrections`,
        correctionId: '0198f0a0-0000-7000-8000-000000000256',
        basket,
        containerBalance: 0,
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

import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, router } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { financeBasketRow } from './financeBasketRow';
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
    'Una correcció de la marca de recollida documentada amb un motiu molt llarg que ha de continuar sent llegible en una pantalla estreta, fins i tot amb el text ampliat';
const collected = [
    'original',
    'recollected',
    'error',
    'ready',
    'processing',
].includes(state);
const original = {
    id: '0198f0a0-0000-7000-8000-000000000226',
    type: 'collected',
    authorName: 'Anna Soler',
    occurredAt: '2026-11-01T10:00:00+00:00',
    reason: null,
};
const correction = {
    id: '0198f0a0-0000-7000-8000-000000000227',
    type: 'corrected',
    authorName: 'Finances',
    occurredAt: '2026-11-01T11:00:00+00:00',
    reason: state === 'long' ? longText : 'Marcada per error',
};
const events =
    state === 'never'
        ? []
        : ['original', 'error', 'ready', 'processing'].includes(state)
          ? [original]
          : [original, correction];
if (state === 'recollected') {
    events.push({
        id: '0198f0a0-0000-7000-8000-000000000228',
        type: 'collected',
        authorName: 'Gestió',
        occurredAt: '2026-11-01T12:00:00+00:00',
        reason: null,
    });
}
const basket = financeBasketRow(
    {
        id: '0198f0a0-0000-7000-8000-000000000229',
        householdId: '0198f0a0-0000-7000-8000-000000000230',
        householdName: state === 'long' ? longText : 'La Figuera',
        state: collected ? 'collected' : 'validated',
        stockLines: [
            {
                stockProductId: '0198f0a0-0000-7000-8000-000000000231',
                name: 'Oli',
                unit: 'ampolla',
                quantity: '1.000',
                unitPriceCents: 1200,
                amountCents: 1200,
            },
        ],
        returnLines: [],
        totalCents: 1200,
        effectiveTotalCents: 1200,
        adjustments: [],
        collectionEvents: events,
        balanceCents: -1200,
        projectedBalanceCents: -1200,
        validatedAt: '2026-11-01T09:00:00+00:00',
        collectedAt: state === 'never' ? null : original.occurredAt,
    },
    role === 'member' ? 'member' : 'staff',
);
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const page = {
    component: `${role === 'member' ? 'Member' : 'Finance'}/Baskets/Show`,
    url: location.pathname + location.search,
    version: 'dra-226',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors:
            state === 'error'
                ? { reason: 'Cal indicar el motiu de la correcció.' }
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
if (state === 'processing') {
    router.post = (_url, _data, options) => {
        options?.onStart?.({} as never);
    };
}
void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: () => AppLayout,
    withApp: (app, { page }) => installTranslations(app, page.props.locale),
    progress: false,
}).then(() => {
    if (['ready', 'processing'].includes(state)) {
        const reason = document.querySelector(
            '#collection-reason',
        ) as HTMLTextAreaElement | null;
        if (reason) {
            reason.value = 'Marcada per error';
            reason.dispatchEvent(new Event('input', { bubbles: true }));
            if (state === 'processing') {
                reason.closest('form')?.dispatchEvent(
                    new Event('submit', {
                        bubbles: true,
                        cancelable: true,
                    }),
                );
            }
        }
    }
    document.body.dataset.visualReady = state;
});

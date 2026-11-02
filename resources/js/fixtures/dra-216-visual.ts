import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, router } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(location.search);
const state = query.get('state') ?? 'validated';
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';
const longText =
    'La Figuera de la Cooperativa del Barri Vell amb un nom de llar molt llarg que ha de continuar sent llegible en una pantalla estreta, fins i tot amb el text ampliat';
const events =
    state === 'corrected'
        ? [
              {
                  id: '0198f0a0-0000-7000-8000-000000000216',
                  type: 'collected',
                  recordedBy: 'Gestió',
                  occurredAt: '2026-11-01T10:00:00+00:00',
                  reason: null,
              },
              {
                  id: '0198f0a0-0000-7000-8000-000000000217',
                  type: 'corrected',
                  recordedBy: 'Finances',
                  occurredAt: '2026-11-01T11:00:00+00:00',
                  reason: 'Marcada per error',
              },
          ]
        : [];
const basket = {
    id: '0198f0a0-0000-7000-8000-000000000218',
    householdId: '0198f0a0-0000-7000-8000-000000000219',
    householdName: state === 'long' ? longText : 'La Figuera',
    state: 'validated',
    stockLines: [
        {
            stockProductId: '0198f0a0-0000-7000-8000-000000000220',
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
    collectedAt: state === 'corrected' ? events[0].occurredAt : null,
};
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const page = {
    component: 'Member/Baskets/Show',
    url: location.pathname + location.search,
    version: 'dra-216',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors:
            state === 'error'
                ? { domain: 'Només es pot recollir una cistella validada.' }
                : {},
        auth: {
            user: {
                id: '0198f0a0-0000-7000-8000-000000000221',
                name: 'Anna Soler',
                email: 'anna@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: ['member'],
        },
        roleAreas: [{ role: 'member', label: 'Orders', href: '/' }],
        basket,
    },
};
const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);
const requests: string[] = [];
router.put = (url, _data, options) => {
    requests.push(
        typeof url === 'string' ? url : url instanceof URL ? url.href : url.url,
    );
    document.body.dataset.visualRequests = requests.join(',');
    options?.onStart?.({} as never);
};
void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: () => AppLayout,
    withApp: (app, { page }) => installTranslations(app, page.props.locale),
    progress: false,
}).then(() => {
    if (state === 'processing') {
        (
            document.querySelector(
                '[data-test="collect-basket"]',
            ) as HTMLButtonElement | null
        )?.click();
    }
    document.body.dataset.visualReady = state;
});

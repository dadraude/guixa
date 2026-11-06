import '@/../css/app.css';
import '../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

// Deterministic fixture: the real pages with the props the controllers send; no requests are made.
const query = new URLSearchParams(location.search);
const view = query.get('view') ?? 'manager';
const state = query.get('state') ?? 'populated';
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const id = (n: number) => `0198f0a0-0000-7000-8000-${String(n).padStart(12, '0')}`;
const longNote =
    'Ajust per inventari: dues ampolles trencades durant el transport de la collita de tardor fins al magatzem de la cooperativa';
const sees = view === 'producer' ? null : id(900);
const line = (
    n: number,
    type: string,
    quantity: string,
    balanceAfter: string,
    note: string | null,
    authorName: string,
    withCycle: boolean,
    basketId: string | null,
) => ({
    id: id(n),
    occurredAt: `2026-10-20 1${n}:00`,
    type,
    quantity,
    balanceAfter,
    note,
    authorName,
    cycleId: withCycle ? id(800) : null,
    pickupDate: withCycle ? '2026-10-24' : null,
    basketId,
});
const lines =
    state === 'empty'
        ? []
        : [
              line(4, 'correction', '1.000', '7.000', 'Recompte equivocat', 'Anna Soler', false, sees),
              line(3, 'sale', '-3.000', '6.000', null, 'Anna Soler', false, sees),
              line(
                  2,
                  'adjustment',
                  '-1.000',
                  '9.000',
                  state === 'long' ? longNote : 'Pot trencat',
                  state === 'long' ? 'Maria de la Concepció Fernández-Villalonga' : 'Biel Puig',
                  true,
                  null,
              ),
              line(1, 'entry', '10.000', '10.000', 'Comprat a X', 'Biel Puig', false, null),
          ];
const paged = state === 'empty' ? false : true;
const history = {
    lines,
    total: lines.length + (paged ? 100 : 0),
    page: 2,
    prevUrl: paged ? `${location.pathname}?page=1` : null,
    nextUrl: paged ? `${location.pathname}?page=3` : null,
};
const product = { name: 'Mel', unit: 'pot', available: '7.000' };
const stockRow = (n: number, name: string) => ({
    id: id(n),
    producerId: id(700),
    name,
    unit: 'pot',
    price: '6.50',
    variable: false,
    containersPerUnit: null,
    active: true,
    available: '7.000',
});

const pages: Record<string, { component: string; props: object; role: string }> = {
    manager: {
        component: 'Manage/Stock/Movements',
        role: 'manager',
        props: {
            product: { ...stockRow(100, 'Mel'), available: '7.000' },
            cycles: [{ id: id(800), pickupDate: '2026-10-24' }],
            history,
        },
    },
    finance: {
        component: 'Finance/Stock/Movements',
        role: 'finance',
        props: { product, history },
    },
    producer: {
        component: 'Producer/Stock/Movements',
        role: 'producer',
        props: { product, history },
    },
    'finance-list': {
        component: 'Finance/Stock/Index',
        role: 'finance',
        props: {
            products: [stockRow(100, 'Mel'), stockRow(101, 'Oli d’oliva verge extra de la collita de tardor')],
        },
    },
};
const current = pages[view];
const components = import.meta.glob('../../resources/js/pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const page = {
    component: current.component,
    url: location.pathname + location.search,
    version: 'dra-238',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors: {},
        auth: {
            user: {
                id: id(1000),
                name: 'Anna Soler',
                email: 'anna@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: [current.role],
        },
        roleAreas: [
            {
                role: current.role,
                label: current.role === 'producer' ? 'Production' : current.role === 'manager' ? 'Cooperative' : 'Finance',
                href: '/',
            },
        ],
        ...current.props,
    },
};
const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);
void createInertiaApp({
    resolve: (name) => components[`../../resources/js/pages/${name}.vue`].default,
    layout: () => AppLayout,
    withApp: (app, { page }) => installTranslations(app, page.props.locale),
    progress: false,
}).then(() => {
    document.body.dataset.visualReady = `${view}-${state}`;
});

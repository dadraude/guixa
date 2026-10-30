import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const pageName = query.get('page') ?? 'Stock/Index';
const state = query.get('state') ?? 'populated';
const producerPage = pageName === 'Producer/Products/Index';
const mixed = state === 'member-producer';
const long = state === 'long';
const routePath = producerPage ? '/producer/products' : '/stock';
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
initializeTheme();
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';
if (state === 'fallback-font') {
    document.documentElement.style.fontFamily = 'system-ui';
    document.body.style.fontFamily = 'system-ui';
}
const honey = {
    id: '0198f0a0-0000-7000-8000-000000000001',
    producerId: '0198f0a0-0000-7000-8000-000000000010',
    producerName: 'Apicultora',
    name: long
        ? 'Mel artesanal de romaní elaborada a la Vall de les terres altes'
        : 'Mel de romaní',
    unit: 'pot',
    price: '6.50',
    available: '5.000',
    soldOut: false,
    active: true,
};
const oil = {
    ...honey,
    id: '0198f0a0-0000-7000-8000-000000000002',
    producerName: 'Oleïcultor',
    name: 'Oli',
    unit: 'ampolla',
    price: '4.50',
    available: '0.000',
    soldOut: true,
};
const inactive = {
    ...honey,
    id: '0198f0a0-0000-7000-8000-000000000003',
    name: 'Mel antiga',
    active: false,
    available: '0.000',
};
const weekly = {
    id: '0198f0a0-0000-7000-8000-000000000004',
    name: 'Pa',
    unit: 'barra',
    price: '3.50',
    variable: false,
    containersPerUnit: null,
    active: true,
};
const products = state === 'empty' ? [] : [honey, oil];
const stockProducts =
    state === 'empty' || state === 'stock-empty' ? [] : [honey, oil, inactive];
const roles = producerPage
    ? ['producer']
    : mixed
      ? ['producer', 'member']
      : ['member'];
const roleAreas = roles.map((role) => ({
    role,
    label: role,
    href: role === 'member' ? '/stock' : '/producer/products',
}));
const weeklyProducts =
    state === 'empty'
        ? []
        : state === 'long'
          ? Array.from({ length: 8 }, (_, index) => ({
                ...weekly,
                id: `0198f0a0-0000-7000-8000-${String(index + 20).padStart(12, '0')}`,
                name: `Pa de massa mare de la Vall i de les terres altes ${index + 1}`,
            }))
          : [weekly];
const page = {
    component: pageName,
    url: routePath,
    version: 'dra-209',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user: {
                id: 'actor',
                name: 'Persona',
                email: 'actor@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles,
        },
        roleAreas,
        sidebarOpen: true,
        ...(producerPage
            ? { products: weeklyProducts, stockProducts }
            : { products }),
    },
};
const initialPage = document.createElement('script');
const fixtureUrl = window.location.href;
const initialNavigation = new Promise<void>((resolve) => {
    const onNavigate = (event: Event) => {
        if ((event as CustomEvent<{ type: string }>).detail.type !== 'initial')
            return;

        document.removeEventListener('inertia:navigate', onNavigate);
        window.history.replaceState(window.history.state, '', fixtureUrl);
        resolve();
    };
    document.addEventListener('inertia:navigate', onNavigate);
});
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
    await initialNavigation;
    for (let i = 0; i < 100 && !document.querySelector('#main-content'); i++)
        await new Promise((resolve) => window.setTimeout(resolve, 25));
    const root = document.querySelector('#main-content');
    if (!root) throw new Error('Main content missing');
    if (state === 'fallback-font') {
        const theme = root.closest<HTMLElement>('.pilot-theme');
        if (!theme) throw new Error('Theme boundary missing');
        theme.style.fontFamily = 'system-ui';
    }
    const focusable = [
        ...root.querySelectorAll<HTMLElement>('a[href],button,input'),
    ].filter((node) => node.getClientRects().length && node.tabIndex >= 0);
    focusable[0]?.setAttribute('data-visual-page-focus-first', '');
    focusable.at(-1)?.setAttribute('data-visual-page-focus-last', '');
    if (window.location.href !== fixtureUrl)
        throw new Error(
            `Fixture URL was not restored: ${window.location.href}`,
        );
    document.body.dataset.visualReady = state;
});

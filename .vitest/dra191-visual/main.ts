import { createApp, defineComponent, h } from 'vue';
import { page } from './inertia';
import AppLayout from '@/layouts/AppLayout.vue';
import Dashboard from '@/pages/Dashboard.vue';
import WalletStatement from '@/pages/Wallet/Statement.vue';
import ProducerCutoff from '@/pages/Producer/Cutoff.vue';
import ProducerCycleSummary from '@/pages/Producer/CycleSummary.vue';
import ProductIndex from '@/pages/Producer/Products/Index.vue';
import ProductCreate from '@/pages/Producer/Products/Create.vue';
import ProductEdit from '@/pages/Producer/Products/Edit.vue';
import { installTranslations } from '@/lib/i18n';
import { updateTheme } from '@/composables/useAppearance';
import '../../resources/css/app.css';

const query = new URLSearchParams(location.search);
const scenario = query.get('scenario') ?? 'dashboard';
const mode = query.get('mode') ?? 'light';
const isEmpty = query.get('empty') === '1';
const longName = query.get('long') === '1';

const product = {
    id: 'bread',
    name: 'Pa de pagès',
    unit: 'unit',
    price: '3.50',
    variable: false,
    containersPerUnit: '1',
    active: true,
};
const inactiveProduct = {
    ...product,
    id: 'honey',
    name: 'Mel de muntanya',
    unit: 'jar',
    price: '6.50',
    containersPerUnit: null,
    active: false,
};
const wallet = {
    balance: '-12.50',
    movements: isEmpty
        ? []
        : [
              {
                  id: 'a',
                  type: 'top_up',
                  amount: '50.00',
                  occurredAt: '2026-10-24 12:30',
                  note: 'Transfer',
                  cycleId: null,
              },
              {
                  id: 'b',
                  type: 'correction',
                  amount: '-12.50',
                  occurredAt: '2026-10-23 18:00',
                  note: 'Duplicate payment',
                  cycleId: 'cycle',
              },
              {
                  id: 'c',
                  type: 'charge',
                  amount: '-50.00',
                  occurredAt: '2026-10-22 19:00',
                  note: null,
                  cycleId: 'cycle',
              },
          ],
};
const summary = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    sent: query.get('sent') === '1',
    products: isEmpty
        ? []
        : [
              {
                  productId: 'bread',
                  name: longName ? 'P'.repeat(59) : product.name,
                  unit: product.unit,
                  available: true,
                  quantity: '40.000',
              },
              {
                  productId: 'honey',
                  name: inactiveProduct.name,
                  unit: inactiveProduct.unit,
                  available: false,
                  quantity: '0.000',
              },
          ],
};

const views: Record<
    string,
    { component: string; view: unknown; props: Record<string, unknown> }
> = {
    dashboard: { component: 'Dashboard', view: Dashboard, props: {} },
    'wallet-member': {
        component: 'Wallet/Statement',
        view: WalletStatement,
        props: { wallet, household: null },
    },
    'wallet-manager': {
        component: 'Wallet/Statement',
        view: WalletStatement,
        props: { wallet, household: { id: 'home', name: 'Casa Riera' } },
    },
    cutoff: {
        component: 'Producer/Cutoff',
        view: ProducerCutoff,
        props: { cutoff: { daysBefore: 3, time: '20:00' } },
    },
    summary: {
        component: 'Producer/CycleSummary',
        view: ProducerCycleSummary,
        props: { summary },
    },
    products: {
        component: 'Producer/Products/Index',
        view: ProductIndex,
        props: { products: isEmpty ? [] : [product, inactiveProduct] },
    },
    create: {
        component: 'Producer/Products/Create',
        view: ProductCreate,
        props: {},
    },
    edit: {
        component: 'Producer/Products/Edit',
        view: ProductEdit,
        props: { product },
    },
};
const selected = views[scenario] ?? views.dashboard;
page.component = selected.component;
page.props.roleAreas =
    scenario.startsWith('wallet') || scenario === 'dashboard'
        ? [
              { role: 'member', label: 'Member', href: '/cycles' },
              { role: 'manager', label: 'Manager', href: '/manage/cycles' },
          ]
        : [{ role: 'producer', label: 'Producer', href: '/producer/products' }];
page.url = '/' + scenario;
updateTheme(mode === 'dark' ? 'dark' : mode === 'system' ? 'system' : 'light');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '200%' : '100%';

const Root = defineComponent({
    setup() {
        return () =>
            h(AppLayout, null, {
                default: () => h(selected.view as never, selected.props),
            });
    },
});

const app = createApp(Root);
app.config.globalProperties.$page = page;
installTranslations(app);
app.mount('#app');
document.documentElement.dataset.fixtureQuery = location.search;

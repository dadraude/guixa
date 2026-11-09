import { createApp, defineComponent, h } from 'vue';
import { page } from './inertia';
import AppLayout from '@/layouts/AppLayout.vue';
import Availability from '@/pages/Catalogue/Producers/Availability.vue';
import { installTranslations } from '@/lib/i18n';
import { updateTheme } from '@/composables/useAppearance';
import '../../resources/css/app.css';
import '../../public/theme-colors.css';

const query = new URLSearchParams(location.search);
const role = query.get('role') === 'manager' ? 'manager' : 'catalogue';
const state = query.get('state') ?? 'populated';
const appearance = query.get('mode') === 'dark' ? 'dark' : 'light';
localStorage.setItem('appearance', appearance);

const roleAreas = {
    catalogue: {
        role: 'catalogue',
        label: 'Catalogue',
        href: '/catalogue/producers',
    },
    manager: { role: 'manager', label: 'Manager', href: '/manage/cycles' },
};
page.props.roleAreas = [roleAreas[role]];
page.props.auth.user = {
    id: 1,
    name: 'Maria Riera',
    email: 'maria@example.test',
    email_verified_at: '2026-10-01T10:00:00Z',
};

const longName =
    'Tomàquet de penjar de la varietat antiga conreat en terrasses de secà';
const names = ['Tomàquet', 'Bròquil', 'Carbassó', 'Pebrot'];
const cycle = (
    index: number,
    pickupDate: string,
    canChange: boolean,
    productNames: string[],
) => ({
    cycleId: `cycle-${index}`,
    pickupDate,
    canChange,
    products: productNames.map((name, position) => ({
        productId: `product-${index}-${position}`,
        name,
        unit: 'kg',
        available: position % 2 === 0,
    })),
});

const cycles =
    state === 'empty'
        ? []
        : state === 'long'
          ? [
                cycle(1, '2026-11-14', true, [longName, 'X'.repeat(59)]),
                cycle(2, '2026-11-21', true, names),
            ]
          : [
                cycle(1, '2026-11-14', true, names),
                cycle(2, '2026-11-21', true, names),
                cycle(3, '2026-11-28', true, names),
                cycle(4, '2026-12-05', false, names.slice(0, 2)),
            ];

const producer = {
    id: 'producer-q',
    name: state === 'long' ? longName : 'Hort de Cal Q',
    active: state !== 'deactivated',
};
page.component = 'Catalogue/Producers/Availability';
page.url = `/catalogue/producers/${producer.id}/availability`;

updateTheme(appearance);
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '200%' : '100%';

const Root = defineComponent({
    setup: () => () =>
        h(AppLayout, null, {
            default: () => h(Availability, { producer, cycles }),
        }),
});
const app = createApp(Root);
app.config.globalProperties.$page = page;
installTranslations(app);
app.mount('#app');
requestAnimationFrame(() =>
    requestAnimationFrame(() =>
        setTimeout(() => {
            document.documentElement.dataset.visualReady = 'true';
        }, 500),
    ),
);

import { createApp, defineComponent, h } from 'vue';
import { page } from './inertia';
import AppLayout from '@/layouts/AppLayout.vue';
import OrderIndex from '@/pages/Orders/Index.vue';
import OrderEdit from '@/pages/Orders/Edit.vue';
import PickupShow from '@/pages/Manage/Pickups/Show.vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import { edit as editOrder } from '@/routes/orders';
import { show as showPickup } from '@/routes/manage/cycles/pickups';
import { installTranslations } from '@/lib/i18n';
import { updateTheme } from '@/composables/useAppearance';
import '../../resources/css/app.css';

// Mounts the real pages and shell with Inertia mocked: no request, persistence
// or authorization is exercised here, only layout.
const query = new URLSearchParams(location.search);
const scenario = query.get('scenario') ?? 'orders-index';
const flag = (name: string) => query.get(name) === '1';
const long = flag('long');
const longText = (letter: string) => letter.repeat(59);

const role = scenario === 'pickup-show' ? 'manager' : 'member';
const roleAreas = {
    member: { role: 'member', label: 'Member', href: '/' },
    manager: { role: 'manager', label: 'Manager', href: '/manage/cycles' },
};
page.props.roleAreas = [roleAreas[role]];
page.props.auth.user = {
    id: 1,
    name: 'Maria Riera',
    email: 'maria@example.test',
    email_verified_at: '2026-10-01T10:00:00Z',
};

const product = (id: string, name: string, price: string, unit = 'unitat') => ({
    id,
    name: long && id === 'bread' ? longText('P') : name,
    unit,
    price,
    variable: false,
});
const baker = {
    producerId: 'baker',
    producerName: long ? longText('F') : 'Forn de la plaça',
    open: true,
    products: [
        product('bread', 'Pa de pagès', '3.50'),
        product('flour', 'Farina de blat', '2.20', 'kg'),
    ],
};
const dairy = {
    producerId: 'dairy',
    producerName: 'Formatgeria del turó',
    open: false,
    products: [product('cheese', 'Formatge tendre', '9.80', 'kg')],
};
const orderLine = (
    producer: typeof baker,
    id: string,
    quantity: string,
    notDelivered = false,
) => {
    const item = producer.products.find((entry) => entry.id === id)!;
    return {
        productId: id,
        producerId: producer.producerId,
        producerName: producer.producerName,
        name: item.name,
        unit: item.unit,
        price: item.price,
        variable: false,
        quantity,
        notDelivered,
    };
};
const pickup = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    householdId: 'home',
    householdName: 'Casa Riera',
    status: 'pending',
    totalCents: 1850,
    balanceCents: null,
    resultingBalanceCents: null,
    lines: [
        {
            productId: 'bread',
            name: long ? longText('P') : 'Pa de pagès',
            unit: 'unitat',
            variable: false,
            unitPriceCents: 350,
            ordered: '4.000',
            allocated: '4.000',
            delivered: '3.000',
            adjusted: true,
            amountCents: 1050,
        },
    ],
    stockLines: [
        {
            stockProductId: 'honey',
            name: 'Mel de farigola',
            unit: 'pot',
            variable: false,
            unitPriceCents: 600,
            quantity: '1',
            amountCents: 600,
        },
    ],
    returnLines: [
        {
            returnItemId: 'jar',
            name: 'Pot de vidre',
            amountCents: 50,
            quantity: 4,
            creditCents: 200,
        },
    ],
    adjustments: [
        {
            id: 'adj',
            productId: 'bread',
            name: 'Pa de pagès',
            unit: 'unitat',
            quantityDelta: '-1.000',
            amountCents: -350,
            recordedAt: '2026-10-30T10:00:00Z',
        },
    ],
    originalChargeCents: 1400,
};
const baseOrder = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    cancelled: false,
    producers: [baker, dairy],
    lines: [orderLine(baker, 'bread', '4.000')] as unknown[],
    pickup: null as unknown,
};
function orderFor(state: string) {
    if (state === 'marked')
        return {
            ...baseOrder,
            producers: [{ ...baker, open: false }],
            lines: [orderLine(baker, 'bread', '2.000', true)],
        };
    if (state === 'closed')
        return {
            ...baseOrder,
            producers: [{ ...baker, open: false }],
        };
    if (state === 'cancelled') return { ...baseOrder, cancelled: true };
    if (state === 'no-producers')
        return { ...baseOrder, producers: [], lines: [] };
    if (state === 'pickup') return { ...baseOrder, pickup };
    return baseOrder;
}

const cycles = [
    {
        cycleId: 'open',
        pickupDate: '2026-10-30',
        open: true,
        cancelled: false,
        lineCount: 2,
    },
    {
        cycleId: 'open-2',
        pickupDate: '2026-11-06',
        open: true,
        cancelled: false,
        lineCount: 1,
    },
    {
        cycleId: 'closed',
        pickupDate: '2026-10-23',
        open: false,
        cancelled: false,
        lineCount: 0,
    },
    {
        cycleId: 'cancelled',
        pickupDate: '2026-10-16',
        open: false,
        cancelled: true,
        lineCount: 1,
    },
];

const views = {
    'orders-index': {
        component: 'Orders/Index',
        view: OrderIndex,
        props: { cycles: flag('empty') ? [] : cycles },
        url: OrderController.index().url,
    },
    'orders-edit': {
        component: 'Orders/Edit',
        view: OrderEdit,
        props: { order: orderFor(query.get('order') ?? 'populated') },
        url: editOrder('cycle').url,
    },
    'pickup-show': {
        component: 'Manage/Pickups/Show',
        view: PickupShow,
        props: {
            pickup,
            stockProducts: [
                {
                    id: 'honey',
                    name: 'Mel de farigola',
                    unit: 'pot',
                    price: '6.00',
                    variable: false,
                    containersPerUnit: null,
                    active: true,
                    available: '5.000',
                },
            ],
            returnItems: [
                {
                    id: 'jar',
                    name: 'Pot de vidre',
                    amount: '0.50',
                    amountCents: 50,
                    active: true,
                },
            ],
        },
        url: showPickup({ cycle: 'cycle', household: 'home' }).url,
    },
} as const;
const selected = views[scenario as keyof typeof views] ?? views['orders-index'];
page.component = selected.component;
page.url = selected.url;
if (flag('error')) page.props.errors.domain = 'El valor no és vàlid.';

updateTheme(
    query.get('mode') === 'dark'
        ? 'dark'
        : query.get('mode') === 'system'
          ? 'system'
          : 'light',
);
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '200%' : '100%';

const Root = defineComponent({
    setup: () => () =>
        h(AppLayout, null, {
            default: () => h(selected.view as never, selected.props),
        }),
});
const app = createApp(Root);
app.config.globalProperties.$page = page;
installTranslations(app);
app.mount('#app');

const pause = (ms: number) => new Promise((done) => setTimeout(done, ms));

async function prepareVisualState(): Promise<void> {
    if (scenario === 'orders-edit' && flag('dirty')) {
        const input =
            document.querySelector<HTMLInputElement>('#quantity-bread');
        if (input) {
            input.value = '5';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    if (flag('sidebar'))
        document
            .querySelector<HTMLElement>('[data-sidebar="trigger"]')
            ?.click();
    await pause(600);
    document.documentElement.dataset.visualReady = 'true';
}

requestAnimationFrame(() =>
    requestAnimationFrame(() => void prepareVisualState()),
);

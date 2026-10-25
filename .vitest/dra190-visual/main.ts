import { createApp, defineComponent, h, ref, watch } from 'vue';
import { page } from './inertia';
import AppLayout from '@/layouts/AppLayout.vue';
import OrderIndex from '@/pages/Orders/Index.vue';
import OrderEdit from '@/pages/Orders/Edit.vue';
import Welcome from '@/pages/Welcome.vue';
import OverlayChecks from './OverlayChecks.vue';
import { installTranslations } from '@/lib/i18n';
import { updateTheme } from '@/composables/useAppearance';
import '../../resources/css/app.css';

const product = {
    id: 'bread',
    name: 'Pa de pagès',
    unit: 'unitat',
    price: '3.50',
    variable: false,
};
const producer = {
    producerId: 'baker',
    producerName: 'Forn de la plaça',
    open: true,
    products: [product],
};
const orderedLine = {
    productId: 'bread',
    producerId: 'baker',
    producerName: producer.producerName,
    name: product.name,
    unit: product.unit,
    price: product.price,
    variable: false,
    quantity: '4.000',
    notDelivered: false,
};
const baseOrder = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    cancelled: false,
    producers: [producer],
    lines: [],
};
const cycles = [
    {
        cycleId: 'open',
        pickupDate: '2026-10-30',
        open: true,
        cancelled: false,
        lineCount: 2,
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
const pickup = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    householdId: 'home',
    householdName: '',
    status: 'pending',
    totalCents: 1050,
    balanceCents: null,
    resultingBalanceCents: null,
    lines: [
        {
            productId: 'bread',
            name: 'Pa de pagès',
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
    stockLines: [],
    returnLines: [],
};
const scenario = ref(
    new URLSearchParams(location.search).get('scenario') ?? 'index',
);
const showOverlayChecks =
    new URLSearchParams(location.search).get('overlay') === '1';
const hideControls =
    new URLSearchParams(location.search).get('controls') === '0';
const mode = ref(new URLSearchParams(location.search).get('mode') ?? 'light');
const textSize = ref(new URLSearchParams(location.search).get('text') ?? '100');
const orderFor = (value: string) => {
    if (value === 'cancelled') return { ...baseOrder, cancelled: true };
    if (value === 'empty') return { ...baseOrder, producers: [] };
    if (value === 'closed')
        return { ...baseOrder, producers: [{ ...producer, open: false }] };
    if (value === 'marked')
        return {
            ...baseOrder,
            producers: [{ ...producer, open: false }],
            lines: [
                {
                    productId: 'bread',
                    producerId: 'baker',
                    producerName: producer.producerName,
                    name: product.name,
                    unit: product.unit,
                    price: product.price,
                    variable: false,
                    quantity: '2.000',
                    notDelivered: true,
                },
            ],
        };
    if (value === 'pickup') return { ...baseOrder, pickup };
    if (value === 'settled')
        return {
            ...baseOrder,
            producers: [{ ...producer, open: false }],
            lines: [orderedLine],
            pickup: {
                ...pickup,
                totalCents: 2050,
                originalChargeCents: 1950,
                stockLines: [
                    {
                        stockProductId: 'honey',
                        name: 'Mel',
                        unit: 'pot',
                        variable: false,
                        unitPriceCents: 600,
                        quantity: '2.000',
                        amountCents: 1200,
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
                        id: 'adjustment',
                        productId: 'bread',
                        name: 'Pa de pagès',
                        unit: 'unitat',
                        quantityDelta: '1.000',
                        amountCents: 350,
                        recordedAt: '2026-10-30T12:00:00Z',
                    },
                ],
            },
        };
    if (value === 'nothing')
        return {
            ...baseOrder,
            pickup: {
                ...pickup,
                status: 'nothing_to_collect',
                lines: [],
                totalCents: 0,
            },
        };
    return baseOrder;
};

watch(
    scenario,
    (value) => {
        page.props.errors.domain =
            value === 'error' ? 'La quantitat no és vàlida.' : '';
        if (value === 'index' || value === 'no-cycles')
            page.component = 'Orders/Index';
        else if (value === 'welcome') page.component = 'Welcome';
        else if (value === 'outside') page.component = 'Dashboard';
        else page.component = 'Orders/Edit';
        page.url = value === 'index' ? '/cycles' : '/cycles/cycle/order';
    },
    { immediate: true },
);
watch(
    mode,
    (value) =>
        updateTheme(
            value === 'dark' ? 'dark' : value === 'system' ? 'system' : 'light',
        ),
    { immediate: true },
);
watch(
    textSize,
    (value) => (document.documentElement.style.fontSize = `${value}%`),
    { immediate: true },
);

const Root = defineComponent({
    setup() {
        return () =>
            h('div', [
                !hideControls &&
                    h(
                        'nav',
                        {
                            style: 'position:fixed;right:8px;bottom:8px;z-index:100;background:white;color:black;padding:8px;border-radius:8px',
                            'aria-label': 'Fixture controls',
                        },
                        [
                            h(
                                'select',
                                {
                                    'aria-label': 'Scenario',
                                    value: scenario.value,
                                    onChange: (event: Event) =>
                                        (scenario.value = (
                                            event.target as HTMLSelectElement
                                        ).value),
                                },
                                [
                                    'index',
                                    'no-cycles',
                                    'edit',
                                    'empty',
                                    'error',
                                    'closed',
                                    'cancelled',
                                    'marked',
                                    'pickup',
                                    'nothing',
                                    'settled',
                                    'outside',
                                    'welcome',
                                ].map((value) => h('option', { value }, value)),
                            ),
                            h(
                                'select',
                                {
                                    'aria-label': 'Appearance',
                                    value: mode.value,
                                    onChange: (event: Event) =>
                                        (mode.value = (
                                            event.target as HTMLSelectElement
                                        ).value),
                                },
                                ['light', 'dark', 'system'].map((value) =>
                                    h('option', { value }, value),
                                ),
                            ),
                            h(
                                'select',
                                {
                                    'aria-label': 'Text size',
                                    value: textSize.value,
                                    onChange: (event: Event) =>
                                        (textSize.value = (
                                            event.target as HTMLSelectElement
                                        ).value),
                                },
                                ['100', '200'].map((value) =>
                                    h('option', { value }, `${value}%`),
                                ),
                            ),
                        ],
                    ),
                scenario.value === 'welcome'
                    ? h(Welcome)
                    : h(AppLayout, null, {
                          default: () => [
                              scenario.value === 'index' ||
                              scenario.value === 'no-cycles'
                                  ? h(OrderIndex, {
                                        cycles:
                                            scenario.value === 'no-cycles'
                                                ? []
                                                : cycles,
                                    })
                                  : scenario.value === 'outside'
                                    ? h(
                                          'main',
                                          { class: 'p-8' },
                                          'Baseline outside pilot',
                                      )
                                    : h(OrderEdit, {
                                          order: orderFor(scenario.value),
                                      }),
                              showOverlayChecks ? h(OverlayChecks) : null,
                          ],
                      }),
            ]);
    },
});

const app = createApp(Root);
app.config.globalProperties.$page = page;
installTranslations(app);
app.mount('#app');

if (new URLSearchParams(location.search).get('probe') === '1') {
    void document.fonts
        .load('400 16px "Instrument Sans"', 'Comandes')
        .then((faces) => {
            const cycleLink = document.querySelector(
                'a[href="/cycles/open/order"]',
            );
            const dialog = document.querySelector('[role="dialog"]');
            document.documentElement.dataset.pilotProbe = JSON.stringify({
                reducedMotion: matchMedia('(prefers-reduced-motion: reduce)')
                    .matches,
                transitionDuration: cycleLink
                    ? getComputedStyle(cycleLink).transitionDuration
                    : 'missing',
                loadedFontFaces: faces.map((face) => face.status),
                fontCheck: document.fonts.check(
                    '400 16px "Instrument Sans"',
                    'Comandes',
                ),
                dialogAnimationDuration: dialog
                    ? getComputedStyle(dialog).animationDuration
                    : 'missing',
            });
        });
}

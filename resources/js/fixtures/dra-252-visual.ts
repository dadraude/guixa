import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import ReceptionsIndex from '@/pages/Receptions/Index.vue';
import ReceptionsShow from '@/pages/Receptions/Show.vue';

// Deterministic data for the Recepció area (DRA-252).
// `page` is index or show; `role` is receiver or manager (what the `can` flags allow);
// `state` is populated (default), empty (index), distributed (show) or long (long names).
const query = new URLSearchParams(window.location.search);
const pageName = query.get('page') ?? 'index';
const role = query.get('role') ?? 'receiver';
const state = query.get('state') ?? 'populated';
const long = state === 'long';

document.documentElement.classList.toggle('dark', query.get('mode') === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const bakerName = long
    ? 'Forn de pa artesà de la Vall de la Mare de Déu amb un nom molt llarg'
    : 'Forn del Barri';

const cycles =
    state === 'empty'
        ? []
        : [
              {
                  cycleId: 'cycle-2',
                  pickupDate: '2026-11-14',
                  closed: false,
                  producers: [
                      {
                          producerId: 'baker',
                          producerName: bakerName,
                          state: 'sent',
                      },
                      {
                          producerId: 'grower',
                          producerName: 'Hort de la Vall',
                          state: 'validated',
                      },
                  ],
              },
              {
                  cycleId: 'cycle-1',
                  pickupDate: '2026-11-07',
                  closed: true,
                  producers: [
                      {
                          producerId: 'baker',
                          producerName: bakerName,
                          state: 'distributed',
                      },
                  ],
              },
          ];

const allocation = (id: string, name: string, quantity: string) => ({
    householdId: id,
    householdName: name,
    ordered: quantity,
    allocated: quantity,
    byHand: false,
    delivered: quantity,
    adjusted: false,
});

const reception = {
    cycleId: 'cycle-2',
    producerId: 'baker',
    producerName: bakerName,
    pickupDate: '2026-11-14',
    unorderedOptions: [{ productId: 'rye', name: 'Sègol', unit: 'pa' }],
    products: [
        {
            productId: 'bread',
            name: 'Pa de pagès',
            unit: 'pa',
            variable: false,
            nobodyOrdered: false,
            ordered: '10.000',
            received: '10.000',
            allocated: '10.000',
            delivered: '10.000',
            difference: '0.000',
            balanced: true,
            balancedForDistribution: true,
            hasManualAllocations: false,
            hasAdjustments: false,
            allocations: [
                allocation('a', 'Can Roca', '4.000'),
                allocation('b', 'La Plaça', '6.000'),
            ],
        },
    ],
};

const distributed = state === 'distributed';
const manager = role === 'manager';
const can = {
    edit: !distributed,
    validate: manager && !distributed,
    distribute: false,
    reopen: manager && distributed,
};

const props =
    pageName === 'index'
        ? { cycles }
        : {
              reception,
              distribution: {
                  validated: distributed,
                  distributed,
                  settled: distributed,
                  closed: false,
              },
              can,
              households: [
                  { id: 'a', name: 'Can Roca' },
                  { id: 'b', name: 'La Plaça' },
                  { id: 'd', name: 'La Font' },
              ],
          };

const page = {
    component: pageName === 'index' ? 'Receptions/Index' : 'Receptions/Show',
    props: { ...props, errors: {} },
    url: window.location.pathname + window.location.search,
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rescuedProps: [],
    flash: {},
    rememberedState: {},
};

const fixturePage = defineComponent({
    render: () =>
        h(
            (pageName === 'index'
                ? ReceptionsIndex
                : ReceptionsShow) as unknown as DefineComponent,
            props,
        ),
});

void createInertiaApp({
    page,
    resolve: () => fixturePage as unknown as DefineComponent,
    layout: () => null,
    setup({ el, App, props: appProps, plugin }) {
        createApp({ render: () => h(App, appProps) })
            .use(plugin)
            .mount(el);
        requestAnimationFrame(() => {
            document.body.dataset.visualReady = `${pageName}-${role}-${state}`;
        });
    },
});

import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h, type Component } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Edit from './Edit.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h('a', attrs, slots.default?.()),
    }),
    usePage: () => ({ props: { errors: {} } }),
    router: { put: vi.fn(), delete: vi.fn() },
}));

const order = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    cancelled: false,
    producers: [
        {
            producerId: 'baker',
            producerName: 'Forn de la plaça',
            open: true,
            products: [
                {
                    id: 'bread',
                    name: 'Pa de pagès',
                    unit: 'unitat',
                    price: '3.50',
                    variable: false,
                },
            ],
        },
    ],
    lines: [],
};

async function render(component: Component, props: Record<string, unknown>) {
    const app = createSSRApp(component, props);
    installTranslations(app);
    return renderToString(app);
}

describe('member order editor presentation', () => {
    it('labels quantity inputs and keeps explicit save and the running total', async () => {
        const html = await render(Edit, { order });

        expect(html).toContain('La teva comanda');
        expect(html).toContain('for="quantity-bread"');
        expect(html).toContain('id="quantity-bread"');
        expect(html).toContain('Desa');
        expect(html).toContain('0,00');
        expect(html).toMatch(
            /disabled[^>]*data-test="save-bread"|data-test="save-bread"[^>]*disabled/,
        );
    });

    it('retains the cancelled notice without any product controls', async () => {
        const html = await render(Edit, {
            order: { ...order, cancelled: true },
        });

        expect(html).toContain('Aquest cicle s’ha cancel·lat');
        expect(html).not.toContain('quantity-bread');
    });

    it('keeps closed rows read-only and marked lines removable with a zero amount', async () => {
        const html = await render(Edit, {
            order: {
                ...order,
                producers: [{ ...order.producers[0], open: false }],
                lines: [
                    {
                        productId: 'bread',
                        producerId: 'baker',
                        producerName: 'Forn de la plaça',
                        name: 'Pa de pagès',
                        unit: 'unitat',
                        price: '3.50',
                        variable: false,
                        quantity: '2.000',
                        notDelivered: true,
                    },
                ],
            },
        });

        expect(html).toContain('No es lliurarà');
        expect(html).toContain('Elimina');
        expect(html).toContain('data-test="remove-bread"');
        expect(html).toMatch(
            /disabled[^>]*data-test="quantity-bread"|data-test="quantity-bread"[^>]*disabled/,
        );
        expect(html).toContain('0,00');
    });

    it('shows the pickup once the cycle is settled, with the delivered quantities and the total', async () => {
        const html = await render(Edit, {
            order: {
                ...order,
                pickup: {
                    cycleId: 'cycle',
                    pickupDate: '2026-10-30',
                    householdId: 'home',
                    householdName: '',
                    status: 'pending',
                    totalCents: 1050,
                    balanceCents: null,
                    resultingBalanceCents: null,
                    stockLines: [],
                    returnLines: [],
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
                },
            },
        });

        expect(html).toContain('data-test="pickup"');
        expect(html).toContain('La teva recollida');
        expect(html).toContain('Demanat 4.000, lliurat 3.000');
        expect(html).toContain('data-test="pickup-total"');
        expect(html).toContain('10,50');
    });

    it('shows the stock and return lines of the pickup read-only, since the total includes them (R22, R23a)', async () => {
        const html = await render(Edit, {
            order: {
                ...order,
                pickup: {
                    cycleId: 'cycle',
                    pickupDate: '2026-10-30',
                    householdId: 'home',
                    householdName: '',
                    status: 'pending',
                    totalCents: 1000,
                    balanceCents: null,
                    resultingBalanceCents: null,
                    lines: [],
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
                },
            },
        });

        expect(html).toContain('data-test="stock-line-honey"');
        expect(html).toContain('data-test="return-line-jar"');
        expect(html).not.toContain('data-test="add-stock-form"');
        expect(html).not.toContain('data-test="add-return-form"');
        expect(html).not.toContain('data-test="remove-stock-honey"');
    });

    it('shows no stock or return section when the pickup has none', async () => {
        const html = await render(Edit, {
            order: {
                ...order,
                pickup: {
                    cycleId: 'cycle',
                    pickupDate: '2026-10-30',
                    householdId: 'home',
                    householdName: '',
                    status: 'pending',
                    totalCents: 0,
                    balanceCents: null,
                    resultingBalanceCents: null,
                    lines: [],
                    stockLines: [],
                    returnLines: [],
                },
            },
        });

        expect(html).not.toContain('data-test="stock-lines"');
        expect(html).not.toContain('data-test="return-lines"');
    });

    it('shows no pickup before the cycle is settled', async () => {
        const html = await render(Edit, { order });

        expect(html).not.toContain('data-test="pickup"');
    });
});

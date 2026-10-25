import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import Show from './Show.vue';

const shared = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Head: () => null,
        Link: defineComponent({
            setup:
                (_, { slots }) =>
                () =>
                    h('a', slots.default?.()),
        }),
        usePage: () => ({ props: { errors: shared.errors } }),
        router: { put: vi.fn(), post: vi.fn(), delete: vi.fn() },
    };
});

const line = (adjusted: boolean) => ({
    productId: 'beef',
    name: 'Beef',
    unit: 'kg',
    variable: true,
    unitPriceCents: 2000,
    ordered: '1.000',
    allocated: '1.000',
    delivered: adjusted ? '1.120' : '1.000',
    adjusted,
    amountCents: adjusted ? 2240 : 2000,
});

const pickup = (adjusted: boolean) => ({
    cycleId: 'cycle',
    pickupDate: '2026-11-07',
    householdId: 'a',
    householdName: 'Household A',
    status: 'pending',
    lines: [line(adjusted)],
    totalCents: adjusted ? 2240 : 2000,
    balanceCents: 5000,
    resultingBalanceCents: adjusted ? 2760 : 3000,
    stockLines: [] as unknown[],
    returnLines: [] as unknown[],
});

const stockProducts = [
    {
        id: 'honey',
        name: 'Honey',
        unit: 'jar',
        price: '6.00',
        variable: false,
        containersPerUnit: null,
        active: true,
        available: '5.000',
    },
];

const returnItems = [
    {
        id: 'jar',
        name: 'Glass jar',
        amount: '0.50',
        amountCents: 50,
        active: true,
    },
];

describe('pickup page', () => {
    beforeEach(() => {
        shared.errors = { domain: 'Nope' };
    });

    it('offers not collected only for pending pickups and explains the charge', async () => {
        const render = (status: string) =>
            renderToString(
                createSSRApp(Show, {
                    pickup: { ...pickup(false), status },
                    stockProducts,
                    returnItems,
                }),
            );
        expect(await render('pending')).toContain(
            'data-test="not-collected-form"',
        );
        expect(await render('not_collected')).not.toContain(
            'data-test="not-collected-form"',
        );
        expect(await render('not_collected')).toContain(
            'data-test="not-collected"',
        );
    });

    it('shows adjustment history separately from the original charge and current total', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                pickup: {
                    ...pickup(false),
                    status: 'completed',
                    originalChargeCents: 2240,
                    totalCents: 3000,
                    adjustments: [
                        {
                            id: 'adjustment',
                            productId: 'beef',
                            name: 'Beef',
                            unit: 'kg',
                            quantityDelta: '-0.120',
                            amountCents: -240,
                            recordedAt: '2026-11-07T10:00:00Z',
                        },
                    ],
                },
                stockProducts,
                returnItems,
            }),
        );
        expect(html).toContain('data-test="pickup-adjustment-adjustment"');
        expect(html).toContain('Original charge');
        expect(html).toContain('22,40 €');
        expect(html).toContain('-0.120 kg');
        expect(html).toContain('Current pickup total');
        expect(html).not.toContain('data-test="resulting-balance"');
    });

    it('shows the lines, the balance, the total and the resulting balance', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                pickup: pickup(false),
                stockProducts,
                returnItems,
            }),
        );

        expect(html).toContain('Pickup: Household A');
        expect(html).toContain('data-test="delivered-form-beef"');
        expect(html).toContain('50,00 €');
        expect(html).toContain('20,00 €');
        expect(html).toContain('30,00 €');
        expect(html).not.toContain('data-test="adjusted"');
        expect(html).toContain('Nope');
    });

    it('offers to complete a pending pickup with an optional top-up', async () => {
        const html = await renderToString(
            createSSRApp(Show, { pickup: pickup(false) }),
        );

        expect(html).toContain('data-test="complete-form"');
        expect(html).toContain('data-test="top-up"');
        expect(html).not.toContain('data-test="completed"');
    });

    it('shows a completed pickup read-only, with nothing left to complete', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                pickup: { ...pickup(false), status: 'completed' },
            }),
        );

        expect(html).toContain('data-test="completed"');
        expect(html).not.toContain('data-test="complete-form"');
        expect(html).not.toContain('data-test="delivered-form-beef"');
    });

    it('flags an adjusted line', async () => {
        const html = await renderToString(
            createSSRApp(Show, { pickup: pickup(true) }),
        );

        expect(html).toContain('data-test="adjusted"');
        expect(html).toContain('22,40 €');
        expect(html).toContain('27,60 €');
    });

    it('shows the delivered quantity read-only when there is nothing to collect', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                pickup: { ...pickup(false), status: 'nothing_to_collect' },
            }),
        );

        expect(html).not.toContain('data-test="delivered-form-beef"');
        expect(html).toContain('data-test="delivered"');
        expect(html).toContain('1.000 kg');
    });

    it('shows the stock and return lines and the forms to add more', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                pickup: {
                    ...pickup(false),
                    stockLines: [
                        {
                            stockProductId: 'honey',
                            name: 'Honey',
                            unit: 'jar',
                            variable: false,
                            unitPriceCents: 600,
                            quantity: '2.000',
                            amountCents: 1200,
                        },
                    ],
                    returnLines: [
                        {
                            returnItemId: 'jar',
                            name: 'Glass jar',
                            amountCents: 50,
                            quantity: 4,
                            creditCents: 200,
                        },
                    ],
                },
                stockProducts,
                returnItems,
            }),
        );

        expect(html).toContain('data-test="stock-line-honey"');
        expect(html).toContain('data-test="return-line-jar"');
        expect(html).toContain('data-test="add-stock-form"');
        expect(html).toContain('data-test="add-return-form"');
    });

    it('keeps the stock and return lines read-only when the pickup is not pending', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                pickup: { ...pickup(false), status: 'nothing_to_collect' },
                stockProducts,
                returnItems,
            }),
        );

        expect(html).not.toContain('data-test="add-stock-form"');
        expect(html).not.toContain('data-test="add-return-form"');
    });

    const sections = async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                pickup: pickup(false),
                stockProducts,
                returnItems,
            }),
        );
        const [beforeReturns, returns] = html.split('data-test="return-lines"');
        const [beforeStock, stock] = beforeReturns.split(
            'data-test="stock-lines"',
        );

        return { beforeStock, stock, returns };
    };

    it('shows a rejected return quantity only in the returns section', async () => {
        shared.errors = { return_quantity: 'Bad return quantity' };
        const { beforeStock, stock, returns } = await sections();

        expect(returns).toContain('Bad return quantity');
        expect(stock).not.toContain('Bad return quantity');
        expect(beforeStock).not.toContain('Bad return quantity');
    });

    it('shows a rejected stock quantity only in the stock section', async () => {
        shared.errors = { stock_quantity: 'Bad stock quantity' };
        const { stock, returns } = await sections();

        expect(stock).toContain('Bad stock quantity');
        expect(returns).not.toContain('Bad stock quantity');
    });

    it('does not show the delivered-quantity field error in either section', async () => {
        shared.errors = { quantity: 'Bad delivered quantity' };
        const { stock, returns } = await sections();

        expect(stock).not.toContain('Bad delivered quantity');
        expect(returns).not.toContain('Bad delivered quantity');
    });
});

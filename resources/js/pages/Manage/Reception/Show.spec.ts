import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import Show from './Show.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Head: () => null,
        Form: defineComponent({
            setup:
                (_, { attrs, slots }) =>
                () =>
                    h(
                        'form',
                        attrs,
                        slots.default?.({ errors: {}, processing: false }),
                    ),
        }),
        usePage: () => ({ props: { errors: { domain: 'Nope' } } }),
        router: { put: vi.fn() },
    };
});

const allocation = (
    id: string,
    name: string,
    ordered: string,
    byHand = false,
) => ({
    householdId: id,
    householdName: name,
    ordered,
    allocated: ordered,
    byHand,
    delivered: ordered,
    adjusted: false,
});

const reception = {
    cycleId: 'cycle',
    producerId: 'baker',
    producerName: 'Baker',
    pickupDate: '2026-11-07',
    products: [
        {
            productId: 'bread',
            name: 'Bread',
            unit: 'loaf',
            variable: false,
            nobodyOrdered: false,
            ordered: '20.000',
            received: '15.000',
            allocated: '14.000',
            delivered: '14.000',
            difference: '1.000',
            balanced: false,
            balancedForDistribution: false,
            hasManualAllocations: true,
            hasAdjustments: false,
            allocations: [
                allocation('a', 'Household A', '4.000'),
                allocation('c', 'Household C', '10.000', true),
            ],
        },
        {
            productId: 'rye',
            name: 'Rye',
            unit: 'loaf',
            variable: false,
            nobodyOrdered: true,
            ordered: '0.000',
            received: '3.000',
            allocated: '3.000',
            delivered: '3.000',
            difference: '0.000',
            balanced: true,
            balancedForDistribution: true,
            hasManualAllocations: false,
            hasAdjustments: false,
            allocations: [],
        },
    ],
    unorderedOptions: [],
};

const open = { validated: false, distributed: false, settled: false };

describe('reception page', () => {
    it('shows each product with its totals and flags the unbalanced ones', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                reception,
                distribution: open,
                households: [{ id: 'd', name: 'Household D' }],
            }),
        );

        expect(html).toContain('Reception: Baker');
        expect(html).toContain('Unbalanced by 1,000 loaf');
        expect(html).toContain('data-test="balanced"');
        expect(html).toContain('Set by hand');
        expect(html).toContain('Another household…');
        expect(html).toContain('Nope');
    });

    it('flags a pickup adjustment but still lets the producer be distributed', async () => {
        const adjusted = {
            ...reception,
            products: [
                {
                    ...reception.products[1],
                    delivered: '3.120',
                    difference: '-0.120',
                    balanced: false,
                    hasAdjustments: true,
                },
            ],
        };

        const html = await renderToString(
            createSSRApp(Show, {
                reception: adjusted,
                distribution: {
                    validated: true,
                    distributed: false,
                    settled: false,
                },
                households: [],
            }),
        );

        expect(html).toContain('data-test="adjusted"');
        expect(html).toContain('3,120 loaf');
        expect(html).not.toContain('data-test="distribute-hint"');
    });

    it('lets a manager correct the total of a product nobody ordered instead of the proportional form', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                reception,
                distribution: open,
                households: [],
            }),
        );

        expect(html).toContain('data-test="unordered-total-rye"');
        expect(html).not.toContain('data-test="received-form-rye"');
        expect(html).toContain('data-test="received-form-bread"');
    });

    it('offers to validate, and keeps distributing disabled while a product is unbalanced', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                reception,
                distribution: open,
                households: [],
            }),
        );

        expect(html).toContain('data-test="validate-form"');
        expect(html).toContain('Mark as distributed');
        expect(html).toContain('data-test="distribute-hint"');
        expect(html).not.toContain('data-test="reopen-form"');
    });

    it('shows a distributed reception read-only with a reopen button until the cycle closes', async () => {
        const render = (closed: boolean) =>
            renderToString(
                createSSRApp(Show, {
                    reception,
                    distribution: {
                        validated: true,
                        distributed: true,
                        settled: true,
                        closed,
                    },
                    households: [{ id: 'd', name: 'Household D' }],
                }),
            );

        const html = await render(false);

        expect(html).toContain('data-test="distributed"');
        expect(html).toContain('data-test="reopen-form"');
        expect(html).not.toContain('data-test="validate-form"');
        expect(html).not.toContain('data-test="distribute-form"');
        expect(html).not.toContain('data-test="received-form-bread"');
        expect(html).not.toContain('data-test="unordered-total-rye"');
        expect(html).not.toContain('Household D');
        expect(html).toContain('data-test="allocated-value"');
        expect(await render(true)).not.toContain('data-test="reopen-form"');
    });
});

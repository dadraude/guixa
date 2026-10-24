import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import Show from './Show.vue';

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
        usePage: () => ({ props: { errors: { domain: 'Nope' } } }),
        router: { put: vi.fn() },
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
});

describe('pickup page', () => {
    it('shows the lines, the balance, the total and the resulting balance', async () => {
        const html = await renderToString(
            createSSRApp(Show, { pickup: pickup(false) }),
        );

        expect(html).toContain('Pickup: Household A');
        expect(html).toContain('data-test="delivered-form-beef"');
        expect(html).toContain('50,00 €');
        expect(html).toContain('20,00 €');
        expect(html).toContain('30,00 €');
        expect(html).not.toContain('data-test="adjusted"');
        expect(html).toContain('Nope');
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
});

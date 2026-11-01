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
        usePage: () => ({ props: { errors: {} } }),
        router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    };
});

const basket = {
    id: 'basket',
    householdId: 'household',
    householdName: 'Household K',
    state: 'draft',
    stockLines: [],
    returnLines: [
        {
            returnItemId: 'jar',
            name: 'Glass jar',
            amountCents: 50,
            quantity: 1,
            creditCents: 50,
        },
    ],
    totalCents: -50,
    balanceCents: 0,
    projectedBalanceCents: 50,
    validatedAt: null,
    collectedAt: null,
};

describe('Finance basket detail', () => {
    it('renders a return-only credit and enables validation for real return contents (S88)', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                basket,
                stockProducts: [],
                returnItems: [
                    {
                        id: 'jar',
                        name: 'Glass jar',
                        amount: '0.50',
                        amountCents: 50,
                        active: true,
                    },
                ],
            }),
        );

        expect(html).toContain('data-test="return-lines"');
        expect(html).toContain('Glass jar');
        expect(html).toContain('-0,50');
        expect(html).toContain('−0,50');
        const validateButton = html.match(
            /<button[^>]*data-test="validate-basket"[^>]*>/,
        )?.[0];
        expect(validateButton).toBeTruthy();
        expect(validateButton).not.toMatch(/\sdisabled(?:\s|>)/);
    });

    it('keeps validation disabled for an empty draft while leaving cancellation available (S88)', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                basket: {
                    ...basket,
                    returnLines: [],
                    totalCents: 0,
                    projectedBalanceCents: 0,
                },
                stockProducts: [],
                returnItems: [],
            }),
        );

        const validateButton = html.match(
            /<button[^>]*data-test="validate-basket"[^>]*>/,
        )?.[0];
        expect(validateButton).toMatch(/\sdisabled(?:\s|>)/);
        expect(html).toContain('Cancel draft');
    });
});

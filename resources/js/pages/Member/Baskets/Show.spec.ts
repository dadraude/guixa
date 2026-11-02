import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Show from './Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h('a', attrs, slots.default?.()),
    }),
}));

const basket: App.Http.BasketRow = {
    id: 'basket-own',
    householdId: 'household-own',
    householdName: 'La Figuera',
    state: 'draft',
    stockLines: [
        {
            stockProductId: 'oil',
            name: 'Olive oil',
            unit: 'bottle',
            quantity: '2.000',
            unitPriceCents: 1200,
            amountCents: 2400,
        },
    ],
    returnLines: [
        {
            returnItemId: 'jar',
            name: 'Glass jar',
            amountCents: 50,
            quantity: 2,
            creditCents: 100,
        },
    ],
    totalCents: 2300,
    effectiveTotalCents: 2300,
    adjustments: [],
    balanceCents: -500,
    projectedBalanceCents: -2800,
    validatedAt: null,
    collectedAt: null,
};

async function render(record = basket) {
    const app = createSSRApp(Show, { basket: record });
    installTranslations(app);
    return renderToString(app);
}

describe('member basket detail', () => {
    it('shows corrected amounts and reason without staff controls (R23, R31)', async () => {
        const html = await render({
            ...basket,
            state: 'validated',
            totalCents: 1200,
            effectiveTotalCents: 1000,
            adjustments: [
                {
                    id: 'correction-1',
                    amountCents: 200,
                    reason: 'Corrected price',
                    recordedBy: 'manager',
                    occurredAt: '2026-10-30T11:00:00+00:00',
                },
            ],
        });
        expect(html).toContain('data-test="original-basket-total"');
        expect(html).toContain('data-test="effective-basket-total"');
        expect(html).toContain('Corrected price');
        expect(html).not.toContain('data-test="basket-correction-form"');
    });

    it('shows actual line snapshots, exact money and a draft preparation label without mutations (R19a, R21, R23a)', async () => {
        const html = await render();
        expect(html).toContain('Olive oil');
        expect(html).toContain('Glass jar');
        expect(html).toContain('24,00\u00a0€');
        expect(html).toContain('−1,00\u00a0€');
        expect(html).toContain('23,00\u00a0€');
        expect(html).toContain('-5,00\u00a0€');
        expect(html).toContain('-28,00\u00a0€');
        expect(html).toContain('data-test="draft-preparation"');
        expect(html).not.toContain('data-test="pending-collection"');
        expect(html).not.toContain('<form');
        expect(html).not.toContain('<button');
        expect(html).not.toContain('/finance/');
    });

    it.each(['validated', 'collected', 'cancelled'] as const)(
        'renders %s as read-only retained history',
        async (state) => {
            const html = await render({ ...basket, state });
            expect(html).toContain(`data-test="basket-status-${state}"`);
            if (state === 'cancelled')
                expect(html).toMatch(
                    /data-test="basket-status-cancelled"[^>]*>.*Cistella cancel·lada/s,
                );
            expect(html).not.toContain('<button');
        },
    );
});

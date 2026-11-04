// @vitest-environment jsdom
import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, nextTick } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Show from './Show.vue';
import { withServerFlags } from '@/testing/basketFlags';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        Head: () => null,
        Link: defineComponent({
            setup:
                (_, { attrs, slots }) =>
                () =>
                    h('a', attrs, slots.default?.()),
        }),
        usePage: () => ({
            props: { errors: { domain: 'Cistella no recollible' } },
        }),
        router: { put: vi.fn() },
    };
});

const basket = withServerFlags(
    {
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
                effectiveQuantity: '2.000',
                effectiveAmountCents: 2400,
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
        collectionEvents: [],
        pendingCollection: true,
        weeklyLines: [],
        kind: 'stock',
        referenceCycleId: null,
        late: false,
        balanceCents: -500,
        projectedBalanceCents: -2800,
        validatedAt: null,
        collectedAt: null,
    },
    'member',
);

async function render(record = basket) {
    const app = createSSRApp(Show, { basket: record });
    installTranslations(app);
    return renderToString(app);
}

describe('member basket detail', () => {
    it('titles a late weekly basket apart from the base weekly basket (R19)', async () => {
        const base = await render(
            withServerFlags(
                {
                    ...basket,
                    kind: 'weekly',
                    late: false,
                },
                'member',
            ) as typeof basket,
        );
        const late = await render(
            withServerFlags(
                {
                    ...basket,
                    kind: 'weekly',
                    late: true,
                },
                'member',
            ) as typeof basket,
        );

        expect(base).toContain('Cistella setmanal');
        expect(base).not.toContain('Cistella tardana');
        expect(late).toContain('Cistella tardana');
    });

    it('shows a never-collected validated basket as pending and read-only', async () => {
        const html = await render(
            withServerFlags(
                {
                    ...basket,
                    state: 'validated',
                    collectionEvents: [],
                    collectedAt: null,
                },
                'member',
            ),
        );
        expect(html).toContain('data-test="original-collection"');
        expect(html).toContain('Encara no s’ha recollit');
        expect(html).toContain('data-test="effective-collection"');
        expect(html).toContain('Pendent de recollida');
        expect(html).not.toContain('data-test="collection-history"');
        expect(html).not.toContain('data-test="collection-correction-form"');
        expect(html).toContain('data-test="collect-basket"');
        expect(html).not.toContain('<form');
    });

    it('shows original collection and correction history without a staff form (R23b)', async () => {
        const html = await render(
            withServerFlags(
                {
                    ...basket,
                    state: 'validated',
                    collectionEvents: [
                        {
                            id: 'event-1',
                            type: 'collected',
                            recordedBy: 'manager',
                            occurredAt: '2026-10-30T10:00:00+00:00',
                            reason: null,
                        },
                        {
                            id: 'event-2',
                            type: 'corrected',
                            recordedBy: 'finance',
                            occurredAt: '2026-10-30T11:00:00+00:00',
                            reason: 'Wrong mark',
                        },
                    ],
                },
                'member',
            ),
        );
        expect(html).toContain('data-test="original-collection"');
        expect(html).toContain('data-test="effective-collection"');
        expect(html).toContain('Wrong mark');
        expect(html).not.toContain('data-test="collection-correction-form"');
    });

    it('shows corrected amounts and reason without staff controls (R23, R31)', async () => {
        const html = await render(
            withServerFlags(
                {
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
                            lines: [],
                        },
                    ],
                },
                'member',
            ),
        );
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

    it.each(['collected', 'cancelled'] as const)(
        'renders %s as read-only retained history',
        async (state) => {
            const html = await render(
                withServerFlags({ ...basket, state }, 'member'),
            );
            expect(html).toContain(`data-test="basket-status-${state}"`);
            if (state === 'cancelled')
                expect(html).toMatch(
                    /data-test="basket-status-cancelled"[^>]*>.*Cistella cancel·lada/s,
                );
            expect(html).not.toContain('<button');
        },
    );

    it.each(['draft', 'collected', 'cancelled'] as const)(
        'offers no collect action for %s (R23b)',
        async (state) => {
            const html = await render(
                withServerFlags({ ...basket, state }, 'member'),
            );
            expect(html).not.toContain('data-test="collect-basket"');
            expect(html).not.toContain('data-test="domain-error"');
        },
    );

    it('puts the own-household collection from a real translated button and disables it while processing (R23b, S82)', async () => {
        const put = vi.spyOn(router, 'put');
        put.mockClear();
        const wrapper = mount(Show, {
            props: {
                basket: withServerFlags(
                    { ...basket, state: 'validated' },
                    'member',
                ),
            },
            global: { plugins: [{ install: installTranslations }] },
        });
        try {
            const button = wrapper.get<HTMLButtonElement>(
                '[data-test="collect-basket"]',
            );
            expect(button.element.tagName).toBe('BUTTON');
            expect(button.attributes('type')).toBe('button');
            expect(button.text()).toBe('Marca com a recollida');
            expect(button.classes()).toContain('min-h-11');
            expect(wrapper.get('[data-test="domain-error"]').text()).toBe(
                'Cistella no recollible',
            );
            await button.trigger('click');
            expect(put).toHaveBeenCalledTimes(1);
            expect(put.mock.calls[0][0]).toBe('/baskets/basket-own/collection');
            put.mock.calls[0][2]?.onStart?.({} as never);
            await nextTick();
            expect(button.element.disabled).toBe(true);
            put.mock.calls[0][2]?.onFinish?.({} as never);
            await nextTick();
            expect(button.element.disabled).toBe(false);
        } finally {
            wrapper.unmount();
            put.mockRestore();
        }
    });
});

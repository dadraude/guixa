// @vitest-environment jsdom
import { describe, expect, it } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { installTranslations } from '@/lib/i18n';
import StockLines from '@/components/pickup/StockLines.vue';
import AmountAdjustments from './AmountAdjustments.vue';

const oil = {
    stockProductId: 'oil',
    name: 'Olive oil',
    unit: 'bottle',
    quantity: '3.000',
    unitPriceCents: 1200,
    amountCents: 3600,
    effectiveQuantity: '2.000',
    effectiveAmountCents: 2400,
};
const adjustments = [
    {
        id: 'correction-1',
        amountCents: 1200,
        reason: 'One bottle came back',
        recordedBy: 'manager',
        occurredAt: '2026-11-02T11:00:00+00:00',
        lines: [{ stockProductId: 'oil', quantityDelta: '-1.000' }],
    },
];

function mountPanel(props: Record<string, unknown> = {}) {
    return mount(AmountAdjustments, {
        props: {
            originalCents: 3600,
            effectiveCents: 2400,
            adjustments,
            editable: true,
            stockLines: [oil],
            ...props,
        },
        global: { plugins: [{ install: installTranslations }] },
    });
}

describe('basket correction panel', () => {
    it('shows the returned quantity of each correction next to its reason', () => {
        const wrapper = mountPanel();
        const lines = wrapper.get(
            '[data-test="basket-adjustment-lines-correction-1"]',
        );
        expect(lines.text()).toContain('Olive oil');
        expect(lines.text()).toContain('1,000');
    });

    it('submits returned goods with the amount, trimmed, and nothing for an amount-only correction', async () => {
        const wrapper = mountPanel();
        await wrapper.get('#correction-amount').setValue('1200');
        await wrapper.get('#correction-reason').setValue('  Came back  ');
        await wrapper.get('[data-test="returned-oil"]').setValue(' 1 ');
        await wrapper
            .get('[data-test="basket-correction-form"]')
            .trigger('submit');
        await wrapper.get('[data-test="returned-oil"]').setValue('');
        await wrapper
            .get('[data-test="basket-correction-form"]')
            .trigger('submit');

        const [goods, amountOnly] = wrapper.emitted('submit') as unknown[][];
        expect(goods.slice(0, 2)).toEqual([1200, 'Came back']);
        expect(goods[3]).toEqual([{ stock_product_id: 'oil', quantity: '1' }]);
        expect(amountOnly[3]).toEqual([]);
        expect(goods[2]).toBe(amountOnly[2]);
    });

    it('offers no returned-goods field when nothing is left to return or the view is read-only', () => {
        const reversed = {
            ...oil,
            effectiveQuantity: '0.000',
            effectiveAmountCents: 0,
        };
        expect(
            mountPanel({ stockLines: [reversed] })
                .find('[data-test="returned-goods"]')
                .exists(),
        ).toBe(false);
        expect(
            mountPanel({ editable: false })
                .find('[data-test="basket-correction-form"]')
                .exists(),
        ).toBe(false);
    });
});

describe('stock lines of a corrected basket', () => {
    it('shows original and effective quantity and amount only when they differ', () => {
        const wrapper = mount(StockLines, {
            props: {
                lines: [
                    oil,
                    {
                        ...oil,
                        stockProductId: 'honey',
                        effectiveQuantity: '3.000',
                    },
                ],
                options: [],
                editable: false,
                processing: false,
            },
            global: { plugins: [{ install: installTranslations }] },
        });
        const corrected = wrapper.get('[data-test="stock-line-oil"]');
        expect(
            corrected.get('[data-test="effective-quantity"]').text(),
        ).toContain('2');
        expect(
            corrected.get('[data-test="effective-amount"]').text(),
        ).toContain('24');
        expect(corrected.get('[data-test="amount"]').text()).toContain('36');
        expect(
            wrapper
                .get('[data-test="stock-line-honey"]')
                .find('[data-test="effective-quantity"]')
                .exists(),
        ).toBe(false);
    });
});

// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import WeeklyLines from './WeeklyLines.vue';

const line = {
    productId: 'bread',
    name: 'Bread',
    unit: 'loaf',
    ordered: '5.000',
    allocated: '4.000',
    quantity: '3.000',
    unitPriceCents: 350,
    amountCents: 1050,
    effectiveQuantity: '3.000',
    effectiveAmountCents: 1050,
    differsFromAllocation: true,
};

const weeklyLines = (props: Record<string, unknown> = {}) =>
    mount(WeeklyLines, {
        props: { lines: [line], editable: true, processing: false, ...props },
    });

describe('weekly lines', () => {
    it('shows ordered, allocated and the actual quantity, flagging a difference from the allocation', () => {
        const wrapper = weeklyLines();
        const row = wrapper.get('[data-test="weekly-line-bread"]').text();

        expect(row).toContain('Bread');
        expect(row).toContain('5,000');
        expect(row).toContain('4,000');
        expect(
            wrapper.find('[data-test="weekly-difference-bread"]').exists(),
        ).toBe(true);
    });

    it('lets staff save a new actual quantity, zero included', async () => {
        const wrapper = weeklyLines();

        await wrapper.get('input').setValue('0');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.emitted('save')).toEqual([['bread', '0']]);
    });

    it('is read-only for a member and shows no flag when the actual equals the allocation', () => {
        const wrapper = weeklyLines({
            editable: false,
            lines: [
                { ...line, quantity: '4.000', differsFromAllocation: false },
            ],
        });

        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper.find('[data-test="weekly-difference-bread"]').exists(),
        ).toBe(false);
        expect(wrapper.get('[data-test="weekly-actual"]').text()).toContain(
            '4,000',
        );
    });

    it('shows the effective quantity after a correction adjustment', () => {
        const wrapper = weeklyLines({
            editable: false,
            lines: [{ ...line, effectiveQuantity: '5.000' }],
        });

        expect(
            wrapper.find('[data-test="weekly-effective-bread"]').exists(),
        ).toBe(true);
    });
});

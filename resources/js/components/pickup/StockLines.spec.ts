// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import StockLines from './StockLines.vue';

const line = {
    stockProductId: 'honey',
    name: 'Honey',
    unit: 'jar',
    unitPriceCents: 600,
    quantity: '2.000',
    amountCents: 1200,
    effectiveQuantity: '2.000',
    effectiveAmountCents: 1200,
};

const options = [
    {
        id: 'honey',
        producerId: 'beekeeper',
        name: 'Honey',
        unit: 'jar',
        price: '6.00',
        variable: false,
        containersPerUnit: null,
        active: true,
        available: '5.000',
    },
    {
        id: 'cheese',
        producerId: 'dairy',
        name: 'Cheese',
        unit: 'kg',
        price: '20.00',
        variable: true,
        containersPerUnit: null,
        active: true,
        available: '3.500',
    },
];

const stockLines = (props: Record<string, unknown> = {}) =>
    mount(StockLines, {
        props: {
            lines: [line],
            options,
            editable: true,
            processing: false,
            ...props,
        },
    });

describe('stock lines', () => {
    it('lists each line with its quantity and amount', () => {
        const wrapper = stockLines();

        expect(wrapper.get('[data-test="stock-line-honey"]').text()).toContain(
            'Honey',
        );
        expect(wrapper.get('[data-test="stock-line-honey"]').text()).toContain(
            '2,000 jar',
        );
        expect(wrapper.get('[data-test="stock-line-honey"]').text()).toContain(
            '12,00\u00a0€',
        );
    });

    it('asks to add the chosen product with the typed quantity', async () => {
        const wrapper = stockLines();

        await wrapper
            .get('[data-test="stock-product"]')
            .trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const option = [...document.querySelectorAll('[role="option"]')].find(
            (element) => element.textContent?.includes('Cheese'),
        );
        expect(option).toBeTruthy();
        option!.dispatchEvent(new Event('pointerup', { bubbles: true }));
        await flushPromises();
        await wrapper.get('[data-test="stock-quantity"]').setValue('0.250');
        await wrapper.get('[data-test="add-stock-form"]').trigger('submit');

        expect(wrapper.emitted('add')).toEqual([['cheese', '0.250']]);
    });

    it('does not ask to add without a product or a quantity', async () => {
        const wrapper = stockLines();

        await wrapper.get('[data-test="add-stock-form"]').trigger('submit');

        expect(wrapper.emitted('add')).toBeUndefined();
    });

    it('asks to remove a whole line from a labelled 44px button', async () => {
        const wrapper = stockLines();
        const button = wrapper.get('[data-test="remove-stock-honey"]');

        expect(button.attributes('aria-label')).toContain('Honey');
        expect(button.classes()).toContain('min-h-11');

        await button.trigger('click');

        expect(wrapper.emitted('remove')).toEqual([['honey']]);
    });

    it('labels the add controls and gives them 44px targets', () => {
        const wrapper = stockLines();

        expect(
            wrapper.get('[data-test="stock-product"]').attributes('aria-label'),
        ).toBeTruthy();
        expect(wrapper.get('[data-test="stock-product"]').classes()).toContain(
            'h-11',
        );
        expect(
            wrapper
                .get('[data-test="stock-quantity"]')
                .attributes('aria-label'),
        ).toBeTruthy();
        expect(wrapper.get('[data-test="stock-quantity"]').classes()).toContain(
            'h-11',
        );
    });

    it('offers the available quantity of each product', async () => {
        const wrapper = stockLines();

        await wrapper
            .get('[data-test="stock-product"]')
            .trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        expect(
            [...document.querySelectorAll('[role="option"]')].some((option) =>
                option.textContent?.includes('3,500 kg'),
            ),
        ).toBe(true);
    });

    it('is read-only when the pickup cannot change', () => {
        const wrapper = stockLines({ editable: false });

        expect(wrapper.find('[data-test="add-stock-form"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="remove-stock-honey"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain('Honey');
    });

    it('shows the validation errors next to the fields', () => {
        const wrapper = stockLines({
            errors: {
                stock_quantity: 'The quantity is invalid.',
                stock_product_id: 'The product is invalid.',
            },
        });

        expect(wrapper.text()).toContain('The quantity is invalid.');
        expect(wrapper.text()).toContain('The product is invalid.');
    });

    it('formats the unit price with a comma', () => {
        expect(stockLines().text()).toContain('6,00\u00a0€');
    });

    it('accepts basket rows and shows the basket-specific empty state', () => {
        expect(
            stockLines().get('[data-test="stock-line-honey"]').text(),
        ).toContain('12,00\u00a0€');
        const empty = stockLines({
            lines: [],
            emptyMessage: 'No basket stock yet.',
        });
        expect(empty.get('[data-test="no-stock-lines"]').text()).toBe(
            'No basket stock yet.',
        );
    });
});

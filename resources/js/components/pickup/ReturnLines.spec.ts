// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import ReturnLines from './ReturnLines.vue';

const line = {
    returnItemId: 'jar',
    name: 'Glass jar',
    amountCents: 50,
    quantity: 4,
    creditCents: 200,
};

const options = [
    {
        id: 'jar',
        name: 'Glass jar',
        amount: '0.50',
        amountCents: 50,
        active: true,
    },
    {
        id: 'crate',
        name: 'Crate',
        amount: '2.00',
        amountCents: 200,
        active: false,
    },
];

const returnLines = (props: Record<string, unknown> = {}) =>
    mount(ReturnLines, {
        props: {
            lines: [line],
            options,
            editable: true,
            processing: false,
            ...props,
        },
    });

describe('return lines', () => {
    it('lists each line with its quantity and credit', () => {
        const text = returnLines().get('[data-test="return-line-jar"]').text();

        expect(text).toContain('Glass jar');
        expect(text).toContain('4');
        expect(text).toContain('2,00\u00a0€');
    });

    it('asks to add the chosen item with the typed whole quantity, deactivated items included', async () => {
        const wrapper = returnLines();

        await wrapper
            .get('[data-test="return-item"]')
            .trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const option = [...document.querySelectorAll('[role="option"]')].find(
            (element) => element.textContent?.includes('Crate'),
        );
        expect(option).toBeTruthy();
        option!.dispatchEvent(new Event('pointerup', { bubbles: true }));
        await flushPromises();
        await wrapper.get('[data-test="return-quantity"]').setValue('3');
        await wrapper.get('[data-test="add-return-form"]').trigger('submit');

        expect(wrapper.emitted('add')).toEqual([['crate', '3']]);
    });

    it('does not ask to add without an item or a quantity', async () => {
        const wrapper = returnLines();

        await wrapper.get('[data-test="add-return-form"]').trigger('submit');

        expect(wrapper.emitted('add')).toBeUndefined();
    });

    it('asks to remove a whole line from a labelled 44px button', async () => {
        const wrapper = returnLines();
        const button = wrapper.get('[data-test="remove-return-jar"]');

        expect(button.attributes('aria-label')).toContain('Glass jar');
        expect(button.classes()).toContain('min-h-11');

        await button.trigger('click');

        expect(wrapper.emitted('remove')).toEqual([['jar']]);
    });

    it('labels the add controls, takes whole units and gives 44px targets', () => {
        const wrapper = returnLines();
        const quantity = wrapper.get('[data-test="return-quantity"]');

        expect(
            wrapper.get('[data-test="return-item"]').attributes('aria-label'),
        ).toBeTruthy();
        expect(quantity.attributes('aria-label')).toBeTruthy();
        expect(quantity.attributes('inputmode')).toBe('numeric');
        expect(quantity.classes()).toContain('h-11');
    });

    it('is read-only when the pickup cannot change', () => {
        const wrapper = returnLines({ editable: false });

        expect(wrapper.find('[data-test="add-return-form"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="remove-return-jar"]').exists()).toBe(
            false,
        );
    });

    it('shows the validation errors next to the fields', () => {
        const wrapper = returnLines({
            errors: {
                return_quantity: 'The quantity must be an integer.',
                return_item_id: 'The item is invalid.',
            },
        });

        expect(wrapper.text()).toContain('The quantity must be an integer.');
        expect(wrapper.text()).toContain('The item is invalid.');
    });

    it('formats the amounts of the options with a comma, like the lines', async () => {
        const wrapper = returnLines();

        await wrapper
            .get('[data-test="return-item"]')
            .trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const labels = [...document.querySelectorAll('[role="option"]')].map(
            (option) => option.textContent,
        );
        expect(labels.some((label) => label?.includes('0,50\u00a0€'))).toBe(
            true,
        );
        expect(labels.some((label) => label?.includes('2,00\u00a0€'))).toBe(
            true,
        );
    });
});

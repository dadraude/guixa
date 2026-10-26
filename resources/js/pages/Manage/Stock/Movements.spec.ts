// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import Movements from './Movements.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ errors: {}, processing: false })),
    }),
}));

describe('stock adjustment cycle selection', () => {
    it('shows the full selected label and clears it when the form resets', async () => {
        const wrapper = mount(Movements, {
            props: {
                product: {
                    id: 'honey',
                    name: 'Honey',
                    unit: 'jar',
                    price: '6.50',
                    variable: false,
                    containersPerUnit: '1',
                    active: true,
                    available: '9',
                },
                cycles: [{ id: 'cycle', pickupDate: '2026-11-07' }],
            },
            attachTo: document.body,
        });
        const select = wrapper.get('#adjustment-cycle');
        const form = select.element.closest('form') as HTMLFormElement;
        await nextTick();
        expect(new FormData(form).get('cycle_id')).toBe('');

        await select.trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const option = [...document.querySelectorAll('[role="option"]')].find(
            (element) => element.textContent?.includes('Pickup 2026-11-07'),
        );
        expect(option).toBeTruthy();
        option!.dispatchEvent(new Event('pointerup', { bubbles: true }));
        await flushPromises();
        expect(
            wrapper.get('[data-test="adjustment-cycle-preview"]').text(),
        ).toBe('Pickup 2026-11-07');
        expect(new FormData(form).get('cycle_id')).toBe('cycle');

        await wrapper
            .get('[data-test="clear-adjustment-cycle"]')
            .trigger('click');
        expect(new FormData(form).get('cycle_id')).toBe('');

        await select.trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const secondOption = [
            ...document.querySelectorAll('[role="option"]'),
        ].find((element) => element.textContent?.includes('Pickup 2026-11-07'));
        secondOption!.dispatchEvent(new Event('pointerup', { bubbles: true }));
        await flushPromises();

        form.reset();
        await nextTick();
        expect(
            wrapper.find('[data-test="adjustment-cycle-preview"]').exists(),
        ).toBe(false);
        wrapper.unmount();
    });
});

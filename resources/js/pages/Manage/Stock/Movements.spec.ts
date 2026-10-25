// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
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
        });
        const select = wrapper.get('#adjustment-cycle');
        const form = select.element.closest('form') as HTMLFormElement;
        expect(new FormData(form).get('cycle_id')).toBe('');

        await select.setValue('cycle');
        expect(
            wrapper.get('[data-test="adjustment-cycle-preview"]').text(),
        ).toBe('Pickup 2026-11-07');
        expect(new FormData(form).get('cycle_id')).toBe('cycle');

        form.reset();
        await nextTick();
        expect(
            wrapper.find('[data-test="adjustment-cycle-preview"]').exists(),
        ).toBe(false);
        wrapper.unmount();
    });
});

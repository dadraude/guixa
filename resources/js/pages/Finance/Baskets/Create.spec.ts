// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h, reactive } from 'vue';
import InputError from '@/components/InputError.vue';
import { installTranslations } from '@/lib/i18n';
import Create from './Create.vue';

const formState = reactive<{
    errors: Record<string, string>;
    processing: boolean;
}>({ errors: {}, processing: false });

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h('a', attrs, slots.default?.()),
    }),
    Form: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h(
                    'form',
                    { class: attrs.class },
                    slots.default?.({
                        errors: formState.errors,
                        processing: formState.processing,
                    }),
                ),
    }),
}));

const households = [
    { id: 'north', name: 'North household' },
    { id: 'south', name: 'South household' },
] as App.Http.BasketHouseholdRow[];

function render(rows = households) {
    return mount(Create, {
        props: { households: rows },
        global: { plugins: [{ install: installTranslations }] },
    });
}

describe('finance basket create form', () => {
    beforeEach(() => {
        formState.errors = {};
        formState.processing = false;
    });

    it('titles the page and offers the household select with one primary action', () => {
        const wrapper = render();

        expect(wrapper.get('h1').text()).toBe('Crea una cistella');
        expect(wrapper.find('label[for="basket-household"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="household-select"]').exists()).toBe(
            true,
        );
        expect(wrapper.findAll('button[type="submit"]')).toHaveLength(1);
        expect(wrapper.get('[data-test="create-basket"]').text()).toBe(
            'Crea una cistella',
        );
    });

    it.each([
        ['household_id', 'Choose a household first'],
        ['domain', 'Household already has a basket'],
    ])('shows the %s error through InputError', (key, message) => {
        formState.errors = { [key]: message };
        const wrapper = render();

        expect(wrapper.findComponent(InputError).props('message')).toBe(
            message,
        );
        expect(wrapper.get('p.text-destructive').text()).toBe(message);
    });

    it('disables the primary action while the form is submitting', () => {
        formState.processing = true;
        const wrapper = render();

        expect(
            wrapper.get('[data-test="create-basket"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('replaces the form with a notice when there are no active households', () => {
        const wrapper = render([]);

        expect(wrapper.find('[data-test="no-households"]').exists()).toBe(true);
        expect(wrapper.find('form').exists()).toBe(false);
    });
});

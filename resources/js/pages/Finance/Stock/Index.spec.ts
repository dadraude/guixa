// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import Index from './Index.vue';
import { installTranslations } from '@/lib/i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        props: { href: { type: [String, Object], default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h(
                    'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : (props.href as { url: string }).url,
                    },
                    slots.default?.(),
                ),
    }),
}));

const product = (id: string, active: boolean) => ({
    id,
    producerId: 'producer',
    name: id,
    unit: 'jar',
    price: '6.50',
    variable: false,
    containersPerUnit: null,
    active,
    available: '7.000',
});

describe('finance stock list', () => {
    it('marks a deactivated product inactive and keeps its history link', () => {
        const wrapper = mount(Index, {
            global: { plugins: [{ install: installTranslations }] },
            props: {
                products: [product('honey', true), product('oil', false)],
            },
        });
        const rows = wrapper.findAll('[data-test="finance-stock-product"]');

        expect(rows).toHaveLength(2);
        expect(
            rows[0].find('[data-test="finance-stock-inactive"]').exists(),
        ).toBe(false);
        expect(rows[1].get('[data-test="finance-stock-inactive"]').text()).toBe(
            'Inactiu',
        );
        expect(rows[1].get('a').attributes('href')).toContain('oil');
    });
});

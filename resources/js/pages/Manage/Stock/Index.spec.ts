import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import StockIndex from './Index.vue';
import ReturnItemsIndex from '../ReturnItems/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ errors: {}, processing: false })),
    }),
}));

describe('manager list presentation', () => {
    it('keeps stock column meanings visible when rows reflow', async () => {
        const html = await renderToString(
            createSSRApp(StockIndex, {
                products: [
                    {
                        id: 'stock-1',
                        name: 'Honey',
                        unit: 'jar',
                        variable: false,
                        price: '5.00',
                        containersPerUnit: 1,
                        available: 9,
                        active: true,
                    },
                ],
            }),
        );

        expect(html).toContain('pilot-manager-table');
        expect(html).toContain('pilot-stock-mobile-list');
        expect(html).toContain('<summary');
        expect(html).toContain('pilot-cell-label');
        expect(html).toContain('Available');
        expect(html).toContain('Movements');
        expect(html).toContain('deactivate-mobile-stock-1');
        expect(html).toContain('deactivate-stock-1');
    });

    it('keeps return item column meanings visible when rows reflow', async () => {
        const html = await renderToString(
            createSSRApp(ReturnItemsIndex, {
                items: [
                    {
                        id: 'return-1',
                        name: 'Jar',
                        amount: '0.50',
                        active: true,
                    },
                ],
            }),
        );

        expect(html).toContain('pilot-manager-table');
        expect(html).toContain('pilot-cell-label');
        expect(html).toContain('0.50');
        expect(html).toContain('Deactivate');
    });
});

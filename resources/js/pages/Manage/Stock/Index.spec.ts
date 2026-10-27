// @vitest-environment jsdom
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
    router: { put: vi.fn(), delete: vi.fn() },
}));

describe('manager list presentation', () => {
    it('renders 20 compact stock rows and a six-column desktop table', async () => {
        const products = Array.from({ length: 20 }, (_, index) => ({
            id: `stock-${index}`,
            name: `Producte ${index}`,
            unit: 'pot',
            variable: false,
            price: '5.00',
            containersPerUnit: '1.000',
            available: '9.000',
            active: index !== 0,
        }));
        const html = await renderToString(
            createSSRApp(StockIndex, { products }),
        );
        expect(html.match(/min-h-16 min-w-0 flex-1/g) ?? []).toHaveLength(20);
        expect(html).toContain('manager-stock-table');
        expect(html).toContain('Cerca productes');
        expect(html).toContain('aria-label="Reactivate: Producte 0"');
        expect(html).toContain('aria-label="Deactivate: Producte 1"');
        expect(html).toContain('aria-label="Edit: Producte 0"');
        expect(html).toContain('aria-label="Movements: Producte 0"');
        expect(html).toContain('<dialog');
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

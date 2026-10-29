// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import StockIndex from './Index.vue';
import ReturnItemsIndex from '../ReturnItems/Index.vue';
import { installTranslations } from '@/lib/i18n';

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
                    attrs,
                    slots.default?.({ errors: {}, processing: false }),
                ),
    }),
    router: { put: vi.fn(), delete: vi.fn() },
}));

describe('manager list presentation', () => {
    it('renders 20 compact stock rows and a six-column desktop table', async () => {
        const products = Array.from({ length: 20 }, (_, index) => ({
            id: `stock-${index}`,
            producerId: 'producer-1',
            name: `Producte ${index}`,
            unit: 'pot',
            variable: false,
            price: '5.00',
            containersPerUnit: '1.000',
            available: '9.000',
            active: index !== 0,
        }));
        const app = createSSRApp(StockIndex, { products });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html.match(/min-h-16 min-w-0 flex-1/g) ?? []).toHaveLength(20);
        expect(html).toContain('manager-stock-table');
        expect(html).toContain('Cerca productes');
        expect(html).toContain('aria-label="Reactiva: Producte 0"');
        expect(html).toContain('aria-label="Desactiva: Producte 1"');
        expect(html).toContain('aria-label="Edita Producte 0"');
        expect(html).toContain('aria-label="Moviments: Producte 0"');
        expect(html).toContain(
            '/manage/producers/producer-1/products/stock-0/reactivation',
        );
        expect(html).toContain('/manage/producers/producer-1/products/stock-1');
        expect(html).toContain('<dialog');
    });

    it('keeps return item column meanings visible when rows reflow', async () => {
        const app = createSSRApp(ReturnItemsIndex, {
            items: [
                {
                    id: 'return-1',
                    name: 'Jar',
                    amount: '0.50',
                    active: true,
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).toContain('pilot-manager-table');
        expect(html).toContain('pilot-cell-label');
        expect(html).toContain('0,50\u00a0€');
        expect(html).toContain('Desactiva');
    });

    it('formats a four-digit return credit as Catalan money without changing the row value', async () => {
        const item = {
            id: 'large-credit',
            name: 'Caixa',
            amount: '1234.50',
            active: true,
        };
        const app = createSSRApp(ReturnItemsIndex, { items: [item] });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).toContain('1.234,50\u00a0€');
        expect(item.amount).toBe('1234.50');
    });
});

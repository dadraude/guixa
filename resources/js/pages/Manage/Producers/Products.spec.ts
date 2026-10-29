import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Products from './Products.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h('a', attrs, slots.default?.()),
    }),
    router: { put: vi.fn() },
}));

const producer = { id: 'producer-1', name: 'La Figuera' };
const weekly = {
    id: 'weekly-1',
    producerId: producer.id,
    name: 'Bread',
    unit: 'loaf',
    price: '3.50',
    variable: false,
    containersPerUnit: null,
    active: true,
    stock: false,
    used: false,
};
const stock = { ...weekly, id: 'stock-1', name: 'Honey', stock: true };

async function render(products: (typeof weekly)[]) {
    const app = createSSRApp(Products, { producer, products });
    installTranslations(app);
    return renderToString(app);
}

describe('Manager producer catalogue', () => {
    it('shows both modes and only lets Manager navigate to stock editing', async () => {
        const html = await render([weekly, stock]);
        expect(html).toContain('Producte setmanal');
        expect(html).toContain('Producte d’estoc');
        expect(html.match(/Edita/g)).toHaveLength(1);
        expect(html).not.toContain('Edita: Bread');
        expect(
            html.match(/data-test="change-product-classification"/g),
        ).toHaveLength(2);
    });

    it('shows used records without classification actions', async () => {
        const html = await render([
            { ...weekly, used: true },
            { ...stock, used: true },
        ]);
        expect(html).toContain('ja s’ha utilitzat');
        expect(html).not.toContain('data-test="change-product-classification"');
    });
});

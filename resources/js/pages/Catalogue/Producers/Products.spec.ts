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
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ errors: {}, processing: false })),
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
    can: { edit: true, changeClassification: true, delete: true },
    available: null as string | null,
};
const stock = {
    ...weekly,
    id: 'stock-1',
    name: 'Honey',
    stock: true,
    available: '12.000',
};
const readOnlyStock = {
    ...stock,
    can: { edit: false, changeClassification: false, delete: false },
};

async function render(
    products: (typeof weekly | typeof stock)[],
    canCreateStock = true,
) {
    const app = createSSRApp(Products, { producer, products, canCreateStock });
    installTranslations(app);
    return renderToString(app);
}

describe('Producer products in the Catàleg area', () => {
    it('offers the availability page of the producer as a link (R3, S13)', async () => {
        const html = await render([weekly]);
        expect(html).toMatch(/<a [^>]*>Disponibilitat per cicle<\/a>/);
    });

    it('shows the Manager both modes with edit, classification and both create actions', async () => {
        const html = await render([weekly, stock]);
        expect(html).toContain('Producte setmanal');
        expect(html).toContain('Producte d’estoc');
        expect(html).toContain('aria-label="Edita: Bread"');
        expect(html).toContain('aria-label="Edita: Honey"');
        expect(html).toContain('Afegeix un producte setmanal');
        expect(html).toContain('Afegeix un producte d&#39;estoc');
        expect(
            html.match(/data-test="change-product-classification"/g),
        ).toHaveLength(2);
    });

    it('shows the available quantity of stock products with a movements link, and none for weekly ones (R4)', async () => {
        const html = await render([weekly, stock]);
        expect(html.match(/data-test="stock-available"/g)).toHaveLength(1);
        expect(html).toContain('12,000 loaf disponibles');
        expect(html).toContain('aria-label="Moviments: Honey"');
    });

    it('hides the stock edit, classification and create actions when the can flags are off (R14)', async () => {
        const html = await render([readOnlyStock], false);
        expect(html).not.toContain('Edita: Honey');
        expect(html).not.toContain('data-test="change-product-classification"');
        expect(html).not.toContain('Afegeix un producte d');
        expect(html).toContain('data-test="stock-available"');
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

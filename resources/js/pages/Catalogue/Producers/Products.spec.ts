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
};
const stock = { ...weekly, id: 'stock-1', name: 'Honey', stock: true };
const catalogueWeekly = {
    ...weekly,
    can: { edit: true, changeClassification: false, delete: true },
};
const catalogueStock = {
    ...stock,
    can: { edit: false, changeClassification: false, delete: false },
};

async function render(products: (typeof weekly)[], canCreateStock = true) {
    const app = createSSRApp(Products, { producer, products, canCreateStock });
    installTranslations(app);
    return renderToString(app);
}

describe('Producer products in the Catàleg area', () => {
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

    it('lets Catalogue edit and switch weekly products only, with no stock or classification actions (R3, R4)', async () => {
        const html = await render([catalogueWeekly, catalogueStock], false);
        expect(html).toContain('aria-label="Edita: Bread"');
        expect(html).toContain('data-test="deactivate-weekly-1"');
        expect(html).not.toContain('Edita: Honey');
        expect(html).not.toContain('data-test="deactivate-stock-1"');
        expect(html).not.toContain('estoc</a>');
        expect(html).toContain('Afegeix un producte setmanal');
        expect(html).not.toContain('Afegeix un producte d');
        expect(html).not.toContain('data-test="change-product-classification"');
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

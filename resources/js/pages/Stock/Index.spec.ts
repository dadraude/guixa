import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: (props: { title: string }) => h('title', props.title),
}));

async function render(products: App.Http.StockOfferRow[]) {
    const app = createSSRApp(Index, { products });
    installTranslations(app);
    return renderToString(app);
}

const offer = (
    overrides: Partial<App.Http.StockOfferRow>,
): App.Http.StockOfferRow => ({
    id: 'one',
    producerId: 'producer-one',
    producerName: 'Cal Pagès',
    name: 'Mongetes',
    unit: 'kg',
    price: '4.50',
    available: '12.000',
    soldOut: false,
    ...overrides,
});

describe('member stock list (R26)', () => {
    it('lists each offer with producer, unit, price and quantity, marks sold out as text and offers no write control', async () => {
        const html = await render([
            offer({}),
            offer({
                id: 'two',
                name: 'Oli de la cooperativa de productors de la Garrotxa',
                available: '0.000',
                soldOut: true,
            }),
        ]);

        expect(html).toContain('<title>Catàleg d’estoc</title>');
        expect(html.match(/data-test="stock-offer"/g)).toHaveLength(2);
        expect(html).toContain('Mongetes');
        expect(html).toMatch(/Cal Pagès · kg ·/);
        expect(html).toMatch(/12,000[\s\u00a0]+kg/);
        expect(html).toContain('4,50 €');
        expect(html.match(/data-test="stock-sold-out"/g)).toHaveLength(1);
        expect(html).toContain('Esgotat');
        expect(html).not.toMatch(/<(button|form|input|select|a)[\s>]/);
    });

    it('shows the empty notice instead of the list', async () => {
        const html = await render([]);

        expect(html).toContain('data-test="stock-empty"');
        expect(html).toContain(
            'Encara no hi ha productes d’estoc disponibles.',
        );
        expect(html).not.toContain('data-test="stock-offer"');
    });
});

import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Index from './Index.vue';

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
}));

describe('producer product mobile row labels', () => {
    it('renders Catalan labels with the existing price, status and actions', async () => {
        const app = createSSRApp(Index, {
            products: [
                {
                    id: 'bread',
                    name: 'Pa de pagès',
                    unit: 'unit',
                    price: '3.50',
                    variable: false,
                    containersPerUnit: '1',
                    active: true,
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);

        expect(html).toContain('Nom');
        expect(html).toContain('Unitat');
        expect(html).toContain('Preu');
        expect(html).toContain('Envasos per unitat');
        expect(html).toContain('Actiu');
        expect(html).toContain('Pa de pagès');
        expect(html).toContain('3.50 EUR');
        expect(html).toContain('aria-label="Desactiva: Pa de pagès"');
        expect(html).toContain('pilot-table-actions');
        expect(html.match(/<td class="pilot-amount py-2"/g)).toHaveLength(2);
    });
});

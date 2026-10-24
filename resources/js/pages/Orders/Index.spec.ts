import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h, type Component } from 'vue';
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
    usePage: () => ({ props: { errors: {} } }),
    router: { put: vi.fn(), delete: vi.fn() },
}));

async function render(component: Component, props: Record<string, unknown>) {
    const app = createSSRApp(component, props);
    installTranslations(app);
    return renderToString(app);
}

describe('member cycle list presentation', () => {
    it('renders Catalan cycle states, dates and unchanged destinations', async () => {
        const html = await render(Index, {
            cycles: [
                {
                    cycleId: 'open',
                    pickupDate: '2026-10-30',
                    open: true,
                    cancelled: false,
                    lineCount: 2,
                },
                {
                    cycleId: 'closed',
                    pickupDate: '2026-10-23',
                    open: false,
                    cancelled: false,
                    lineCount: 0,
                },
                {
                    cycleId: 'cancelled',
                    pickupDate: '2026-10-16',
                    open: false,
                    cancelled: true,
                    lineCount: 1,
                },
            ],
        });

        expect(html).toContain('Comandes obertes');
        expect(html).toContain('Comandes tancades');
        expect(html).toContain('Cancel·lades');
        expect(html).toContain('30/10/2026');
        expect(html).toContain('/cycles/open/order');
        expect(html).toContain('2 productes');
    });
});

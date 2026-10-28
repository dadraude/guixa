import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Statement from './Statement.vue';

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

describe('wallet statement mobile row labels', () => {
    it('renders the same Catalan columns beside the existing balance and movement values', async () => {
        const app = createSSRApp(Statement, {
            household: null,
            wallet: {
                balance: '-12.50',
                movements: [
                    {
                        id: 'one',
                        type: 'top_up',
                        amount: '50.00',
                        occurredAt: '2026-10-24 12:00',
                        note: 'Transfer',
                        cycleId: null,
                    },
                ],
            },
        });
        installTranslations(app);
        const html = await renderToString(app);

        expect(html).toContain('Data');
        expect(html).toContain('Tipus');
        expect(html).toContain('Nota');
        expect(html).toContain('Import');
        expect(html).toContain('Saldo: -12,50\u00a0€');
        expect(html).toContain('50,00\u00a0€');
        expect(html).toContain('12:00');
        expect(html).not.toContain('2026-10-24 12:00');
    });
});

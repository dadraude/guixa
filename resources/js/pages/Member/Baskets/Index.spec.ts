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
}));

async function render(baskets: App.Http.BasketSummaryRow[]) {
    const app = createSSRApp(Index, { baskets });
    installTranslations(app);
    return renderToString(app);
}

describe('member basket list', () => {
    it('renders own basket links, exact totals and preparation/collection states without staff actions', async () => {
        const html = await render([
            {
                id: 'basket-one',
                householdName: 'La Figuera',
                state: 'draft',
                totalCents: 2300,
                createdAt: '2026-10-30 10:00',
                pendingCollection: false,
            },
            {
                id: 'basket-two',
                householdName: 'La Figuera',
                state: 'validated',
                totalCents: -100,
                createdAt: '2026-10-31 10:00',
                pendingCollection: true,
            },
            {
                id: 'basket-three',
                householdName: 'La Figuera',
                state: 'cancelled',
                totalCents: 0,
                createdAt: '2026-11-01 10:00',
                pendingCollection: false,
            },
        ]);
        expect(html).toContain('/baskets/basket-one');
        expect(html).toContain('/baskets/basket-two');
        expect(html).toContain('En preparació');
        expect(html).toContain('Pendent de recollida');
        expect(html).toMatch(
            /data-test="status-cancelled"[^>]*>.*Cistella cancel·lada/s,
        );
        expect(html).toContain('23,00\u00a0€');
        expect(html).toContain('-1,00\u00a0€');
        expect(html).not.toContain('<button');
        expect(html).not.toContain('/finance/');
    });

    it('translates the empty state without creating an action', async () => {
        const html = await render([]);
        expect(html).toContain('Encara no hi ha cap cistella.');
        expect(html).not.toContain('<button');
    });
});

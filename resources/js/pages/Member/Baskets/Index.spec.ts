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

async function render(baskets: App.Http.BasketSummaryRow[], pending = false) {
    const app = createSSRApp(Index, { baskets, pending });
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
                status: 'draft',
                totalCents: 2300,
                createdAt: '2026-10-30 10:00',
                validatedAt: null,
                pendingCollection: false,
            },
            {
                id: 'basket-two',
                householdName: 'La Figuera',
                state: 'validated',
                status: 'validated',
                totalCents: -100,
                createdAt: '2026-10-31 10:00',
                validatedAt: null,
                pendingCollection: true,
            },
            {
                id: 'basket-three',
                householdName: 'La Figuera',
                state: 'cancelled',
                status: 'cancelled',
                totalCents: 0,
                createdAt: '2026-11-01 10:00',
                validatedAt: null,
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

    it('says nothing is pending collection when the pending view is empty', async () => {
        const html = await render([], true);
        expect(html).toContain('Cap cistella pendent de recollida.');
        expect(html).not.toContain('Encara no hi ha cap cistella.');
    });

    it('shows the validation date instead of the creation date in the pending view', async () => {
        const html = await render(
            [
                {
                    id: 'basket-one',
                    householdName: 'La Figuera',
                    state: 'validated',
                    status: 'validated',
                    totalCents: 100,
                    createdAt: '2026-10-30 10:00',
                    validatedAt: '2026-11-05 08:15',
                    pendingCollection: true,
                },
            ],
            true,
        );
        expect(html).toMatch(
            /data-test="validated-at"[^>]*>[^<]*5 de nov\. 2026, 8:15/,
        );
        expect(html).not.toContain('30 d’oct. 2026');
        expect(html).toMatch(/<h1[^>]*>\s*Recollides pendents/);
        expect(html).toMatch(/data-test="all-baskets"/);
    });

    it('keeps the plain title and no back link in the full list', async () => {
        const html = await render([]);
        expect(html).toMatch(/<h1[^>]*>\s*Cistelles/);
        expect(html).not.toContain('data-test="all-baskets"');
    });
});

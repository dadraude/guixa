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
                        basketId: null,
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

    it('links a basket source for a manager but leaves member source as text', async () => {
        const wallet = {
            balance: '-12.50',
            movements: [
                {
                    id: 'basket-charge',
                    type: 'charge',
                    amount: '-30.00',
                    occurredAt: '2026-10-24 12:00',
                    note: 'Basket purchase',
                    cycleId: null,
                    basketId: 'basket-one',
                },
                {
                    id: 'basket-credit',
                    type: 'credit',
                    amount: '10.00',
                    occurredAt: '2026-10-25 12:00',
                    note: null,
                    cycleId: null,
                    basketId: 'basket-two',
                },
            ],
        };
        const manager = createSSRApp(Statement, {
            household: { id: 'household-one', name: 'Household' },
            wallet,
        });
        installTranslations(manager);
        const managerHtml = await renderToString(manager);
        expect(managerHtml).toContain('data-test="basket-source-link"');
        expect(managerHtml).toContain('/finance/baskets/basket-one');
        expect(managerHtml).toContain('/finance/baskets/basket-two');
        expect(managerHtml).toContain('Cistella: basket-one');
        expect(managerHtml).toContain('Cistella: basket-two');

        const member = createSSRApp(Statement, { household: null, wallet });
        installTranslations(member);
        const memberHtml = await renderToString(member);
        expect(memberHtml).toContain('data-test="basket-source-text"');
        expect(memberHtml).toContain('Cistella: basket-one');
        expect(memberHtml).toContain('Cistella: basket-two');
        expect(memberHtml).not.toContain('data-test="basket-source-link"');
        expect(memberHtml).not.toContain('/finance/baskets/basket-one');
    });
});

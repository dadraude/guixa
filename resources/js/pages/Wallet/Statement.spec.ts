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

    it('links each basket source to the permitted staff or own-household detail', async () => {
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
        expect(memberHtml).not.toContain('data-test="basket-source-text"');
        expect(memberHtml).toContain('Cistella: basket-one');
        expect(memberHtml).toContain('Cistella: basket-two');
        expect(memberHtml).toContain('data-test="basket-source-link"');
        expect(memberHtml).toContain('/baskets/basket-one');
        expect(memberHtml).toContain('/baskets/basket-two');
        expect(memberHtml).not.toContain('/finance/baskets/basket-one');
    });
});

describe('confirmed receipt forms and linked correction text', () => {
    it('shows the receipt controls and Catalan confirmation to staff, and only movement text to members', async () => {
        const receiptId = '0198f0a0-0000-7000-8000-000000000001';
        const wallet = {
            balance: '0.00',
            movements: [
                {
                    id: 'correction',
                    type: 'correction',
                    amount: '-50.00',
                    occurredAt: '2026-10-24 13:00',
                    note: 'duplicated',
                    cycleId: null,
                    basketId: null,
                    sourceMovementId: receiptId,
                    recordedBy: 'finance',
                },
                {
                    id: receiptId,
                    type: 'top_up',
                    amount: '50.00',
                    occurredAt: '2026-10-24 12:00',
                    note: 'cash',
                    cycleId: null,
                    basketId: null,
                    sourceMovementId: null,
                    recordedBy: 'finance',
                },
            ],
        };
        const staff = createSSRApp(Statement, {
            household: { id: 'household-one', name: 'La Figuera' },
            wallet,
            canManageReceipts: true,
            receiptAction: '/finance/households/household-one/wallet/top-ups',
            correctionAction:
                '/finance/households/household-one/wallet/corrections',
            correctionId: 'correction-id',
            status: 'Ingrés registrat.',
        });
        installTranslations(staff);
        const staffHtml = await renderToString(staff);
        expect(staffHtml).toContain('Ingrés original');
        expect(staffHtml).toContain('Tria un ingrés');
        expect(staffHtml).toContain('data-test="record-top-up"');
        expect(staffHtml).toContain('data-test="record-correction"');
        expect(staffHtml).toContain('data-test="receipt-correction-source"');
        expect(staffHtml).toContain('Correcció de l’ingrés de 50,00');
        expect(staffHtml).toContain('Ingrés registrat.');

        const member = createSSRApp(Statement, {
            household: null,
            wallet,
            canManageReceipts: false,
        });
        installTranslations(member);
        const memberHtml = await renderToString(member);
        expect(memberHtml).toContain('Correcció de l’ingrés de 50,00');
        expect(memberHtml).not.toContain('data-test="record-top-up"');
        expect(memberHtml).not.toContain('data-test="record-correction"');
    });
});

describe('statement navigation boundaries', () => {
    it('keeps the Manager household return link while Finance receipt forms have no forbidden Manager link', async () => {
        const wallet = { balance: '0.00', movements: [] };
        const household = { id: 'household-one', name: 'La Figuera' };
        const manager = createSSRApp(Statement, {
            household,
            wallet,
            canManageReceipts: true,
            receiptAction: '/finance/households/household-one/wallet/top-ups',
            correctionAction:
                '/finance/households/household-one/wallet/corrections',
            householdReturnHref: '/manage/households/household-one',
        });
        installTranslations(manager);
        const managerHtml = await renderToString(manager);
        expect(managerHtml).toContain('/manage/households/household-one');
        expect(managerHtml).toContain('Torna a La Figuera');

        const finance = createSSRApp(Statement, {
            household,
            wallet,
            canManageReceipts: true,
            receiptAction: '/finance/households/household-one/wallet/top-ups',
            correctionAction:
                '/finance/households/household-one/wallet/corrections',
        });
        installTranslations(finance);
        const financeHtml = await renderToString(finance);
        expect(financeHtml).toContain('data-test="record-top-up"');
        expect(financeHtml).not.toContain('Torna a La Figuera');
        expect(financeHtml).not.toContain('/manage/households/household-one');
    });
});

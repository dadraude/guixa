// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import MovementHistory from './MovementHistory.vue';
import { installTranslations } from '@/lib/i18n';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}));

const line = (overrides: Partial<App.Http.StockMovementRow> = {}) => ({
    id: 'one',
    occurredAt: '2026-10-20 11:00',
    type: 'sale',
    quantity: '-3.000',
    balanceAfter: '6.000',
    note: null,
    authorName: 'Ana Buyer',
    cycleId: 'cycle',
    pickupDate: '2026-10-24',
    basketId: 'basket-1',
    ...overrides,
});

const history = (
    lines: App.Http.StockMovementRow[],
    paging: Partial<App.Http.StockMovementPageRow> = {},
): App.Http.StockMovementPageRow => ({
    lines,
    total: lines.length,
    page: 1,
    prevUrl: null,
    nextUrl: null,
    ...paging,
});

const mountHistory = (value: App.Http.StockMovementPageRow) =>
    mount(MovementHistory, {
        global: { plugins: [{ install: installTranslations }] },
        props: { history: value },
    });

describe('stock movement history', () => {
    it('shows the Catalan type, quantity, balance, author and a basket link', () => {
        const wrapper = mountHistory(history([line()]));

        expect(wrapper.get('[data-test="movement-line"]').text()).toContain(
            'Venda',
        );
        expect(wrapper.get('[data-test="movement-line"]').text()).toContain(
            'Ana Buyer',
        );
        expect(
            wrapper.get('[data-test="movement-basket-link"]').attributes('href'),
        ).toContain('basket-1');
    });

    it('drops the basket column when no line carries a basket', () => {
        const wrapper = mountHistory(history([line({ basketId: null })]));

        expect(wrapper.find('[data-test="movement-basket-link"]').exists()).toBe(
            false,
        );
        expect(wrapper.findAll('th')).toHaveLength(7);
    });

    it('shows the empty state without a table', () => {
        const wrapper = mountHistory(history([]));

        expect(wrapper.find('[data-test="movement-history-empty"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('links to the neighbouring pages only when they exist', () => {
        const wrapper = mountHistory(
            history([line()], { page: 2, prevUrl: '/p?page=1', nextUrl: null }),
        );
        const links = wrapper.get('[data-test="movement-paging"]').findAll('a');

        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/p?page=1',
        ]);
    });
});

// @vitest-environment jsdom
import { describe, expect, it } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import MovementHistory from './MovementHistory.vue';
import { installTranslations } from '@/lib/i18n';

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
            wrapper
                .get('[data-test="movement-basket-link"]')
                .attributes('href'),
        ).toContain('basket-1');
    });

    it('drops the basket column when no line carries a basket', () => {
        const wrapper = mountHistory(history([line({ basketId: null })]));

        expect(
            wrapper.find('[data-test="movement-basket-link"]').exists(),
        ).toBe(false);
        expect(wrapper.findAll('th')).toHaveLength(7);
    });

    it('shows the empty state without a table', () => {
        const wrapper = mountHistory(history([]));

        expect(
            wrapper.find('[data-test="movement-history-empty"]').exists(),
        ).toBe(true);
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

    it('says a page past the end is empty, not the whole history, and keeps the way back', () => {
        const wrapper = mountHistory(
            history([], {
                total: 60,
                page: 3,
                prevUrl: '/p?page=2',
                nextUrl: null,
            }),
        );

        expect(
            wrapper.find('[data-test="movement-history-empty"]').exists(),
        ).toBe(false);
        expect(
            wrapper.get('[data-test="movement-history-page-empty"]').text(),
        ).toBe('Aquesta pàgina no té moviments.');
        expect(
            wrapper
                .get('[data-test="movement-paging"]')
                .findAll('a')
                .map((link) => link.attributes('href')),
        ).toEqual(['/p?page=2']);
    });

    it('asks the page to visit a link on a plain click, and leaves modified clicks to the browser', async () => {
        const wrapper = mountHistory(
            history([line()], {
                page: 2,
                prevUrl: '/p?page=1',
                nextUrl: '/p?page=3',
            }),
        );

        await wrapper.get('a[rel="next"]').trigger('click');
        await wrapper.get('a[rel="prev"]').trigger('click', { ctrlKey: true });
        await wrapper
            .get('[data-test="movement-basket-link"]')
            .trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([
            ['/p?page=3'],
            [expect.stringContaining('basket-1')],
        ]);
        expect(wrapper.get('a[rel="prev"]').attributes('href')).toBe(
            '/p?page=1',
        );
    });
});

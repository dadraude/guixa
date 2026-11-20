// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import SettlementDetails from './SettlementDetails.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href.url ?? href"><slot /></a>',
    },
}));
vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string, replacements: Record<string, string> = {}) =>
        Object.entries(replacements).reduce(
            (text, [name, value]) => text.replace(`:${name}`, value),
            key,
        ),
}));

const settlement = (
    balanceCents: number,
    openBaskets: { basketId: string; date: string; status: string }[],
) => ({
    householdId: 'household-1',
    householdName: 'Família Soler',
    balanceCents,
    openBaskets,
});

// R22/R25/S22 (T25): the settlement reads as label plus amount to the cent, whatever the sign, and each open basket
// is a native link, reached by keyboard, that names its status as text. It is inline content, not a dialog.
describe('settlement details', () => {
    let wrapper: ReturnType<typeof mountDetails> | null = null;
    const mountDetails = (row: ReturnType<typeof settlement>) =>
        mount(SettlementDetails, {
            props: { settlement: row } as never,
            attachTo: document.body,
        });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        document.body.innerHTML = '';
    });

    it.each([
        [-1250, /^−12,50\s€$|^-12,50\s€$/],
        [0, /^0,00\s€$/],
    ])('shows the balance %i cents to the cent as text', (cents, amount) => {
        wrapper = mountDetails(settlement(cents, []));
        const row = wrapper.findAll('dt')[0].element.parentElement!;
        expect(row.querySelector('dt')?.textContent).toBe('Balance:');
        expect(row.querySelector('dd')?.textContent?.trim()).toMatch(amount);
    });

    it('says None when no basket is open', () => {
        wrapper = mountDetails(settlement(0, []));
        expect(wrapper.text()).toContain('Open baskets:');
        expect(wrapper.findAll('dd')[1].text()).toBe('None');
        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('lists open baskets as links with their status as text, focusable by keyboard', () => {
        wrapper = mountDetails(
            settlement(500, [
                { basketId: 'b1', date: '2026-11-12', status: 'draft' },
                { basketId: 'b2', date: '2026-11-19', status: 'validated' },
            ]),
        );
        const links = wrapper.findAll('a');
        expect(links.map((link) => link.text().replace(/\s+/g, ' '))).toEqual([
            'Basket of 12/11/2026 (Draft)',
            'Basket of 19/11/2026 (Validated)',
        ]);
        expect(links[0].classes()).toContain('min-h-11');
        (links[1].element as HTMLElement).focus();
        expect(document.activeElement).toBe(links[1].element);
        expect(links[1].attributes('tabindex')).toBeUndefined();
    });
});

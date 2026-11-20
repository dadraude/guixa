// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import HouseholdsIndex from './Index.vue';
import SettlementDetails from './SettlementDetails.vue';

vi.mock('@inertiajs/vue3', () => {
    const passthrough = {
        template: '<form><slot :errors="{}" :processing="false" /></form>',
    };
    return {
        Head: () => null,
        Form: passthrough,
        Link: {
            props: ['href'],
            template: '<a :href="href.url ?? href"><slot /></a>',
        },
    };
});
vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string, replacements: Record<string, string> = {}) =>
        Object.entries(replacements).reduce(
            (text, [name, value]) => text.replace(`:${name}`, value),
            key,
        ),
}));

const household = (id: string, name: string, active: boolean, count = 2) => ({
    id,
    name,
    active,
    memberCount: count,
});

// R22/R26/S21 (T25): a deactivated household reads as the text "Inactive" (colour is never the only signal),
// the members count is text, and a household to settle shows its settlement details (their text: SettlementDetails.spec).
describe('households page', () => {
    let wrapper: ReturnType<typeof mountPage> | null = null;
    const mountPage = () =>
        mount(HouseholdsIndex, {
            props: {
                households: [
                    household('h1', 'Família Soler', true),
                    household('h2', 'Família Puig', false, 1),
                ],
                toSettle: [
                    {
                        householdId: 'h2',
                        householdName: 'Família Puig',
                        balanceCents: -1250,
                        openBaskets: [
                            {
                                basketId: 'b1',
                                date: '2026-11-12',
                                status: 'draft',
                            },
                        ],
                    },
                ],
            } as never,
            attachTo: document.body,
        });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        document.body.innerHTML = '';
    });

    it('marks only the deactivated household with the text Inactive', () => {
        wrapper = mountPage();
        const rows = wrapper.findAll('ul.pilot-list > li');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('2 members');
        expect(rows[0].text()).not.toContain('Inactive');
        expect(rows[1].text()).toContain('1 member');
        expect(rows[1].text()).toContain('Inactive');
    });

    it('shows each household to settle with its settlement details', () => {
        wrapper = mountPage();
        const section = wrapper.get('[aria-labelledby="to-settle-heading"]');
        expect(section.get('h2').text()).toBe('To settle');
        expect(section.text()).toContain('Família Puig');
        const details = wrapper.findComponent(SettlementDetails);
        expect(details.props('settlement')).toMatchObject({
            householdId: 'h2',
            balanceCents: -1250,
        });
    });
});

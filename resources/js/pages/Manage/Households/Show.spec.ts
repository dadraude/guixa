// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import SettlementDetails from './SettlementDetails.vue';
import HouseholdShow from './Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: {
        template: '<form><slot :errors="{}" :processing="false" /></form>',
    },
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

const member = (accountId: string, over: Record<string, unknown> = {}) => ({
    accountId,
    name: `Membre ${accountId}`,
    email: `${accountId}@example.test`,
    invitationPending: false,
    invitationExpired: false,
    invitationExpiresAt: null,
    ...over,
});
const settlement = {
    householdId: 'h1',
    householdName: 'Família Soler',
    balanceCents: 1250,
    openBaskets: [{ basketId: 'b1', date: '2026-11-12', status: 'validated' }],
};

// R22/R26/S21/S22 (T25): an active household states its status as text, offers the member move select and shows the
// settlement above a destructive deactivation; an inactive one says so and offers reactivation instead.
describe('household page', () => {
    let wrapper: ReturnType<typeof mountPage> | null = null;
    const mountPage = (active: boolean) =>
        mount(HouseholdShow, {
            props: {
                household: {
                    id: 'h1',
                    name: 'Família Soler',
                    active,
                    members: [
                        member('anna'),
                        member('pau', { invitationPending: true }),
                    ],
                },
                otherHouseholds: [
                    {
                        id: 'h2',
                        name: 'Família Puig',
                        active: true,
                        memberCount: 1,
                    },
                ],
                deactivationWarning: settlement,
            } as never,
            attachTo: document.body,
        });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        document.body.innerHTML = '';
    });

    it('active: shows status text, the move select per member and the settlement before deactivation', () => {
        wrapper = mountPage(true);
        expect(wrapper.text()).toContain('Active household');
        expect(wrapper.findAll('[data-slot="select-trigger"]')).toHaveLength(2);
        const section = wrapper.get('[aria-labelledby="deactivation-heading"]');
        expect(
            section.findComponent(SettlementDetails).props('settlement'),
        ).toMatchObject({ householdId: 'h1', balanceCents: 1250 });
        const deactivate = section.get('button[type="submit"]');
        expect(deactivate.text()).toBe('Deactivate household');
        expect(deactivate.classes()).toContain('min-h-11');
        expect(wrapper.text()).toContain('Invitation pending');
    });

    it('inactive: says so as text and offers reactivation, not deactivation', () => {
        wrapper = mountPage(false);
        expect(wrapper.text()).toContain(
            'Inactive household: adding a member reactivates it',
        );
        expect(wrapper.find('#deactivation-heading').exists()).toBe(false);
        expect(wrapper.get('#reactivation-heading').text()).toBe(
            'Reactivate household',
        );
        expect(
            wrapper.find('[aria-labelledby="deactivation-heading"]').exists(),
        ).toBe(false);
    });
});

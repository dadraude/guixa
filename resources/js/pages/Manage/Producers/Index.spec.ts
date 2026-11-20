// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import ProducersIndex from './Index.vue';

const formErrors = vi.hoisted(() => ({ value: {} as Record<string, string> }));

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: {
        setup:
            (_: unknown, { slots }: { slots: Record<string, Function> }) =>
            () =>
                slots.default?.({
                    errors: formErrors.value,
                    processing: false,
                }),
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

const producer = (id: string, over: Record<string, unknown> = {}) => ({
    id,
    name: `Productor ${id}`,
    active: true,
    cutoffDaysBefore: 3,
    cutoffTime: '20:00',
    accountEmail: null,
    accountInvitationPending: false,
    ...over,
});

// R22/R26/S21 (T26): a deactivated producer reads as the text "Deactivated" (colour is never the only signal), the
// cutoff is text, and a producer without a login account offers the invitation form while one with an account shows it.
describe('producers page', () => {
    let wrapper: ReturnType<typeof mountPage> | null = null;
    const mountPage = (producers: unknown[]) =>
        mount(ProducersIndex, {
            props: { producers } as never,
            attachTo: document.body,
        });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        document.body.innerHTML = '';
        formErrors.value = {};
    });

    it('marks each producer Active or Deactivated and states its cutoff as text', () => {
        wrapper = mountPage([
            producer('a'),
            producer('b', { active: false, cutoffDaysBefore: 1 }),
        ]);
        expect(wrapper.get('h1').text()).toBe('Producers');
        const rows = wrapper.findAll('ul.pilot-list > li');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Active');
        expect(rows[0].text()).toContain(
            'Cutoff 3 days before pickup, at 20:00',
        );
        expect(rows[1].text()).toContain('Deactivated');
        expect(rows[1].text()).toContain('Cutoff 1 day before pickup');
    });

    it('shows the account with its invitation state, or the invitation form when there is none', () => {
        wrapper = mountPage([
            producer('a', { accountEmail: 'a@example.test' }),
            producer('b', {
                accountEmail: 'b@example.test',
                accountInvitationPending: true,
            }),
            producer('c'),
        ]);
        expect(
            wrapper.get('[data-test="account-a"]').text().replace(/\s+/g, ' '),
        ).toContain('Account: a@example.test (active)');
        expect(wrapper.get('[data-test="account-b"]').text()).toContain(
            '(invitation pending)',
        );
        expect(wrapper.find('[data-test="invite-a"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="invite-c"]').exists()).toBe(true);
    });

    it('says there are no producers, and keeps the new-producer form', () => {
        wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No producers yet.');
        expect(wrapper.find('[data-test="create-producer"]').exists()).toBe(
            true,
        );
    });

    it('shows a field error and a domain error as text after their fields', () => {
        formErrors.value = {
            name: 'The name field is required.',
            domain: 'Cutoff is not valid.',
        };
        wrapper = mountPage([]);
        const errors = wrapper
            .findAll('p.text-destructive')
            .map((error) => error.text())
            .filter(Boolean);
        expect(errors).toEqual([
            'The name field is required.',
            'Cutoff is not valid.',
        ]);
    });
});

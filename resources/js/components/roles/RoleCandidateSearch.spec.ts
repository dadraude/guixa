// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import RoleCandidateSearch from './RoleCandidateSearch.vue';

vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string, replacements: Record<string, string> = {}) =>
        Object.entries(replacements).reduce(
            (text, [name, value]) => text.replace(`:${name}`, value),
            key,
        ),
}));

vi.mock('@inertiajs/vue3', () => ({
    Form: {
        template: '<form><slot :errors="{}" :processing="false" /></form>',
    },
}));

vi.mock('@/actions/App/Http/Controllers/Manage/RoleHolderController', () => ({
    default: { store: { form: () => ({ action: '/manage/roles/x/holders' }) } },
}));

const mountSearch = (
    candidates: unknown,
    query = '',
    error: string | null = null,
) =>
    mount(RoleCandidateSearch, {
        props: {
            role: 'catalogue',
            roleLabel: 'Catalogue',
            query,
            candidates: candidates as never,
            processing: false,
            error,
        },
    });

describe('RoleCandidateSearch', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('asks for the search once the manager stops typing', async () => {
        const wrapper = mountSearch(null);
        const input = wrapper.get('input[name="q"]');

        await input.setValue('A');
        await input.setValue('An');
        await input.setValue(' Ann ');
        vi.advanceTimersByTime(299);
        expect(wrapper.emitted('search')).toBeUndefined();

        vi.advanceTimersByTime(1);
        expect(wrapper.emitted('search')).toEqual([['Ann']]);
    });

    it('does not search after it is removed', async () => {
        const wrapper = mountSearch(null);
        await wrapper.get('input[name="q"]').setValue('Ann');

        wrapper.unmount();
        vi.advanceTimersByTime(1000);

        expect(wrapper.emitted('search')).toBeUndefined();
    });

    it('tells what to do before a search, and when nothing matches', () => {
        expect(mountSearch(null).text()).toContain(
            'Type a name or email to find an account.',
        );
        expect(mountSearch([], 'zzz').text()).toContain(
            'No account matches “zzz”.',
        );
    });

    it('offers each candidate with the status that tells pending from expired', () => {
        const wrapper = mountSearch(
            [
                {
                    accountId: '1',
                    name: 'Bernat',
                    email: 'b@example.org',
                    status: 'invitation_pending',
                },
                {
                    accountId: '2',
                    name: 'Dora',
                    email: 'd@example.org',
                    status: 'invitation_expired',
                },
            ],
            'a',
        );

        expect(wrapper.text()).toContain('Invitation pending');
        expect(wrapper.text()).toContain('Invitation expired');
        expect(wrapper.findAll('button[type="button"]')).toHaveLength(2);
        expect(wrapper.findAll('button')[0].attributes('aria-label')).toBe(
            'Grant Catalogue to Bernat',
        );
    });

    it('asks to grant the chosen account and shows why a grant was rejected (R13)', async () => {
        const wrapper = mountSearch(
            [
                {
                    accountId: '1',
                    name: 'Anna',
                    email: 'a@example.org',
                    status: 'active',
                },
            ],
            'an',
            'Aquest compte ja té aquest rol.',
        );

        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('grant')).toEqual([['1']]);
        expect(wrapper.text()).toContain('Aquest compte ja té aquest rol.');
    });
});

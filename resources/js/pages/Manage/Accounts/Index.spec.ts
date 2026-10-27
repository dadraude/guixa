// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import AccountsIndex from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h(
                    'form',
                    attrs,
                    slots.default?.({ errors: {}, processing: false }),
                ),
    }),
}));

describe('account management', () => {
    it('shows each account status as a switch wired to its existing action', async () => {
        const html = await renderToString(
            createSSRApp(AccountsIndex, {
                accounts: [
                    {
                        accountId: 'active-account',
                        name: 'Admin',
                        email: 'admin@example.com',
                        roles: ['manager'],
                        householdId: null,
                        householdName: null,
                        active: true,
                        invitationPending: false,
                    },
                    {
                        accountId: 'inactive-account',
                        name: 'Edu',
                        email: 'edu@example.com',
                        roles: [],
                        householdId: 'home-1',
                        householdName: 'Casa 1',
                        active: false,
                        invitationPending: false,
                    },
                ],
                households: [
                    {
                        id: 'home-1',
                        name: 'Casa 1',
                        active: true,
                        memberCount: 1,
                    },
                ],
            }),
        );
        const root = document.createElement('div');
        root.innerHTML = html;

        const statusForms = root.querySelectorAll<HTMLFormElement>(
            'form.pilot-status-switch-form',
        );
        expect(statusForms).toHaveLength(2);
        expect(statusForms[0].getAttribute('action')).toBe(
            '/manage/accounts/active-account/deactivation',
        );
        expect(statusForms[1].getAttribute('action')).toContain(
            '/manage/accounts/inactive-account/deactivation?_method=DELETE',
        );
        expect(
            statusForms[0].querySelector('input')?.getAttribute('aria-label'),
        ).toBe('Deactivate: Admin');
        expect(
            statusForms[1].querySelector('input')?.getAttribute('aria-label'),
        ).toBe('Reactivate: Edu');
        expect(
            statusForms[0].querySelector('input')?.hasAttribute('checked'),
        ).toBe(true);
        expect(
            statusForms[1].querySelector('input')?.hasAttribute('checked'),
        ).toBe(false);
        expect(root.querySelector('button[type="submit"]')?.textContent).toBe(
            'Link',
        );
    });
});

// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import AccountsIndex from './Index.vue';
import { installTranslations } from '@/lib/i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        props: ['href'],
        setup:
            (props, { slots }) =>
            () =>
                h(
                    'a',
                    { href: props.href?.url ?? props.href },
                    slots.default?.(),
                ),
    }),
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
        const app = createSSRApp(AccountsIndex, {
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
        });
        installTranslations(app);
        const html = await renderToString(app);
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
        ).toBe('Desactiva: Admin');
        expect(
            statusForms[1].querySelector('input')?.getAttribute('aria-label'),
        ).toBe('Reactiva: Edu');
        expect(
            statusForms[0].querySelector('input')?.hasAttribute('checked'),
        ).toBe(true);
        expect(
            statusForms[1].querySelector('input')?.hasAttribute('checked'),
        ).toBe(false);
        expect(root.querySelector('button[type="submit"]')?.textContent).toBe(
            'Vincula',
        );
    });

    it('translates known role badges while retaining their account role values', async () => {
        const account = {
            accountId: 'one',
            name: 'Anna',
            email: 'anna@example.test',
            roles: ['member', 'manager', 'producer'],
            householdId: null,
            householdName: null,
            active: true,
            invitationPending: false,
        };
        const app = createSSRApp(AccountsIndex, {
            accounts: [account],
            households: [],
        });
        installTranslations(app);
        const root = document.createElement('div');
        root.innerHTML = await renderToString(app);
        const badges = [...root.querySelectorAll('[data-slot="badge"]')].map(
            (badge) => badge.textContent?.trim(),
        );
        expect(badges).toEqual(['Membre', 'Gestor', 'Productor']);
        expect(account.roles).toEqual(['member', 'manager', 'producer']);
    });

    it('shows Catalogue and Receiver badges, no role forms, and links to the roles screen', async () => {
        const app = createSSRApp(AccountsIndex, {
            accounts: [
                {
                    accountId: 'one',
                    name: 'Anna',
                    email: 'anna@example.test',
                    roles: ['manager', 'catalogue', 'receiver'],
                    householdId: null,
                    householdName: null,
                    active: true,
                    invitationPending: false,
                },
            ],
            households: [],
        });
        installTranslations(app);
        const root = document.createElement('div');
        root.innerHTML = await renderToString(app);

        const badges = [...root.querySelectorAll('[data-slot="badge"]')].map(
            (badge) => badge.textContent?.trim(),
        );
        expect(badges).toEqual(['Gestor', 'Catàleg', 'Receptor/a']);
        expect(
            root.querySelectorAll(
                'form[action*="manager-role"], form[action*="finance-role"]',
            ),
        ).toHaveLength(0);
        expect(root.querySelector('a')?.getAttribute('href')).toBe(
            '/manage/roles',
        );
    });
});

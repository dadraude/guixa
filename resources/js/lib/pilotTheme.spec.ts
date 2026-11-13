// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { isPilotThemePage, pilotVariantFor } from './pilotTheme';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ component: 'Orders/Index' });
    return { usePage: () => page };
});

vi.mock('@/layouts/app/AppSidebarLayout.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        default: defineComponent({
            props: {
                pilotTheme: Boolean,
                managerTheme: Boolean,
                pilotVariant: String,
            },
            setup(props) {
                return () =>
                    h('div', {
                        'data-variant': String(props.pilotVariant ?? ''),
                        'data-pilot': String(props.pilotTheme),
                        'data-manager': String(props.managerTheme),
                    });
            },
        }),
    };
});

vi.mock('@/layouts/auth/AuthSimpleLayout.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        default: defineComponent({
            props: { pilotTheme: Boolean, title: String, description: String },
            setup(props) {
                return () =>
                    h('div', { 'data-auth-pilot': String(props.pilotTheme) });
            },
        }),
    };
});

describe('pilot theme page selection', () => {
    it('opts the actual AuthLayout in for authentication pages only', async () => {
        const page = usePage();
        page.component = 'auth/Login';
        const wrapper = mount(AuthLayout);
        const flag = () =>
            wrapper.get('[data-auth-pilot]').attributes('data-auth-pilot');
        expect(flag()).toBe('true');
        page.component = 'auth/TwoFactorChallenge';
        await nextTick();
        expect(flag()).toBe('true');
        page.component = 'Welcome';
        await nextTick();
        expect(flag()).toBe('false');
        wrapper.unmount();
        page.component = 'Orders/Index';
    });

    it('updates the actual AppLayout opt-in as Inertia changes page', async () => {
        const page = usePage();
        const wrapper = mount(AppLayout);
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'true',
        );
        page.component = 'Dashboard';
        await nextTick();
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'true',
        );
        page.component = 'Orders/Edit';
        await nextTick();
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'true',
        );
        page.component = 'Stock/Index';
        await nextTick();
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'true',
        );
        page.component = 'Welcome';
        await nextTick();
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'false',
        );
        wrapper.unmount();
    });

    it('uses the Manager pilot shell on every independent basket page', async () => {
        const page = usePage();
        const wrapper = mount(AppLayout);
        for (const component of [
            'Finance/Baskets/Index',
            'Finance/Baskets/Create',
            'Finance/Baskets/Show',
        ]) {
            page.component = component;
            await nextTick();
            expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
                'true',
            );
            expect(
                wrapper.get('[data-manager]').attributes('data-manager'),
            ).toBe('true');
        }
        page.component = 'Wallet/Statement';
        await nextTick();
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'true',
        );
        expect(wrapper.get('[data-manager]').attributes('data-manager')).toBe(
            'false',
        );
        wrapper.unmount();
    });

    it.each([
        ['Orders/Index', true],
        ['Orders/Edit', true],
        ['Dashboard', true],
        ['Wallet/Statement', true],
        ['Stock/Index', true],
        ['Producer/Cutoff', true],
        ['Producer/CycleSummary', true],
        ['Producer/Products/Index', true],
        ['Producer/Products/Create', true],
        ['Producer/Products/Edit', true],
        ['Manage/Accounts/Index', true],
        ['Manage/Households/Index', true],
        ['Manage/Households/Show', true],
        ['Manage/Producers/Index', true],
        ['Catalogue/Producers/Index', true],
        ['Catalogue/Producers/Products', true],
        ['Catalogue/Producers/WeeklyProduct/Create', true],
        ['Catalogue/Producers/WeeklyProduct/Edit', true],
        ['Manage/Cycles/Index', true],
        ['Manage/Cycles/Show', true],
        ['Receptions/Index', true],
        ['Receptions/Show', true],
        ['Manage/Settings', true],
        ['Manage/Stock/Index', true],
        ['Manage/Stock/Create', true],
        ['Manage/Stock/Edit', true],
        ['Manage/Stock/Movements', true],
        ['Finance/Stock/Index', true],
        ['Finance/Stock/Movements', true],
        ['Producer/Stock/Movements', true],
        ['Manage/ReturnItems/Index', true],
        ['Manage/ReturnItems/Create', true],
        ['Manage/ReturnItems/Edit', true],
        ['Finance/Baskets/Index', true],
        ['Finance/Baskets/Create', true],
        ['Finance/Baskets/Show', true],
        ['settings/Appearance', true],
        ['settings/Profile', true],
        ['settings/Security', true],
        ['auth/AcceptInvitation', true],
        ['auth/ConfirmPassword', true],
        ['auth/ForgotPassword', true],
        ['auth/Login', true],
        ['auth/ResetPassword', true],
        ['auth/TwoFactorChallenge', true],
        ['auth/VerifyEmail', true],
        ['auth/Other', false],
        ['settings/Other', false],
        ['Welcome', false],
        ['Orders/Other', false],
    ])('%s is pilot: %s', (page, expected) => {
        expect(isPilotThemePage(page)).toBe(expected);
    });
});

describe('landing-stock variant admission', () => {
    it.each([
        ['Manage/Stock/Index', 'landing-stock'],
        ['Manage/Stock/Create', null],
        ['Manage/Stock/Edit', null],
        ['Manage/Stock/Movements', null],
        ['Manage/Stock/Index/Extra', null],
        ['Manage/Stock/IndexOld', null],
        ['manage/stock/index', null],
        ['Manage/', null],
        ['Manage/Accounts/Index', null],
        ['Stock/Index', null],
        ['Finance/Stock/Index', null],
        ['Orders/Index', null],
        ['Catalogue/Producers/Index', null],
        ['Welcome', null],
    ])('%s -> %s', (page, expected) => {
        expect(pilotVariantFor(page)).toBe(expected);
    });

    it('passes the variant through the actual AppLayout only on the exact page and clears it on leaving', async () => {
        const page = usePage();
        const wrapper = mount(AppLayout);
        const variant = () =>
            wrapper.get('[data-pilot]').attributes('data-variant');
        for (const component of [
            'Orders/Index',
            'Catalogue/Producers/Index',
            'Manage/Accounts/Index',
            'Manage/Stock/Create',
            'Welcome',
        ]) {
            page.component = component;
            await nextTick();
            expect(variant()).toBe('');
        }
        page.component = 'Manage/Stock/Index';
        await nextTick();
        expect(variant()).toBe('landing-stock');
        page.component = 'Orders/Index';
        await nextTick();
        expect(variant()).toBe('');
        page.component = 'Manage/Stock/Index';
        await nextTick();
        expect(variant()).toBe('landing-stock');
        wrapper.unmount();
        page.component = 'Orders/Index';
    });
});

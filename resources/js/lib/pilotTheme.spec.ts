// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { isPilotThemePage } from './pilotTheme';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ component: 'Orders/Index' });
    return { usePage: () => page };
});

vi.mock('@/layouts/app/AppSidebarLayout.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        default: defineComponent({
            props: { pilotTheme: Boolean },
            setup(props) {
                return () =>
                    h('div', { 'data-pilot': String(props.pilotTheme) });
            },
        }),
    };
});

describe('pilot theme page selection', () => {
    it('updates the actual AppLayout opt-in as Inertia changes page', async () => {
        const page = usePage();
        const wrapper = mount(AppLayout);
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'true',
        );
        page.component = 'Dashboard';
        await nextTick();
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'false',
        );
        page.component = 'Orders/Edit';
        await nextTick();
        expect(wrapper.get('[data-pilot]').attributes('data-pilot')).toBe(
            'true',
        );
        wrapper.unmount();
    });

    it.each([
        ['Orders/Index', true],
        ['Orders/Edit', true],
        ['Dashboard', false],
        ['Welcome', false],
        ['Orders/Other', false],
    ])('%s is pilot: %s', (page, expected) => {
        expect(isPilotThemePage(page)).toBe(expected);
    });
});

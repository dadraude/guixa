// @vitest-environment jsdom
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h, reactive } = await import('vue');
    const page = reactive({
        component: 'Dashboard',
        url: '/',
        props: { sidebarOpen: true },
    });
    const Link = defineComponent({
        props: { href: [String, Object] },
        setup(props, { slots }) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    });
    return { usePage: () => page, Link };
});

vi.mock('@/components/AppShell.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        default: defineComponent({
            props: { class: String },
            setup(props, { slots }) {
                return () =>
                    h(
                        'div',
                        { class: props.class, 'data-test': 'shell' },
                        slots.default?.(),
                    );
            },
        }),
    };
});

vi.mock('@/components/AppSidebar.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return { default: defineComponent({ setup: () => () => h('aside') }) };
});

vi.mock('@/components/AppSidebarHeader.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return { default: defineComponent({ setup: () => () => h('header') }) };
});

vi.mock('@/components/AppContent.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        default: defineComponent({
            setup:
                (_, { slots }) =>
                () =>
                    h('main', slots.default?.()),
        }),
    };
});

vi.mock('@/components/ui/sonner', async () => {
    const { defineComponent } = await import('vue');
    return { Toaster: defineComponent({ setup: () => () => null }) };
});

const Portal = defineComponent({
    setup() {
        return () =>
            h(
                Dialog,
                { open: true },
                {
                    default: () =>
                        h(DialogContent, null, {
                            default: () => h(DialogTitle, () => 'Scope probe'),
                        }),
                },
            );
    },
});

const SheetPortal = defineComponent({
    setup() {
        return () =>
            h(
                Sheet,
                { open: true },
                {
                    default: () =>
                        h(SheetContent, null, {
                            default: () => h(SheetTitle, () => 'Scope probe'),
                        }),
                },
            );
    },
});

describe('pilot theme layout and portal', () => {
    const mountedWrappers: Array<{ unmount: () => void }> = [];

    beforeEach(() => {
        vi.spyOn(console, 'warn');
    });

    afterEach(async () => {
        for (const wrapper of mountedWrappers.splice(0)) wrapper.unmount();
        await nextTick();
        const warnings = vi.mocked(console.warn).mock.calls;
        vi.restoreAllMocks();
        expect(
            warnings.some(
                ([message]) =>
                    typeof message === 'string' &&
                    message.includes(
                        'Non-function value encountered for default slot',
                    ),
            ),
        ).toBe(false);
    });

    it('themes the shell and dialog on admitted pages and clears them after navigation away', async () => {
        const page = usePage();
        page.component = 'Welcome';
        const wrapper = mount(AppLayout, {
            slots: { default: () => h(Portal) },
        });
        mountedWrappers.push(wrapper);
        await nextTick();
        const shell = wrapper.get('[data-test="shell"]');
        const dialog = () =>
            document.querySelector('[data-slot="dialog-content"]');
        const overlay = () =>
            document.querySelector('[data-slot="dialog-overlay"]');

        expect(shell.classes()).not.toContain('pilot-theme');
        expect(dialog()?.classList.contains('pilot-theme')).toBe(false);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(false);
        page.component = 'Dashboard';
        await nextTick();
        expect(shell.classes()).toContain('pilot-theme');
        expect(dialog()?.classList.contains('pilot-theme')).toBe(true);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(true);
        page.component = 'Orders/Index';
        await nextTick();
        expect(shell.classes()).toContain('pilot-theme');
        expect(dialog()?.classList.contains('pilot-theme')).toBe(true);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(true);
        page.component = 'Orders/Edit';
        await nextTick();
        expect(dialog()?.classList.contains('pilot-theme')).toBe(true);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(true);
        page.component = 'Welcome';
        await nextTick();
        expect(shell.classes()).not.toContain('pilot-theme');
        expect(dialog()?.classList.contains('pilot-theme')).toBe(false);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(false);
    });

    it('scopes the sheet backdrop with its content and clears both after navigation', async () => {
        const page = usePage();
        page.component = 'Welcome';
        const wrapper = mount(AppLayout, {
            slots: { default: () => h(SheetPortal) },
        });
        mountedWrappers.push(wrapper);
        const content = () =>
            document.querySelector('[data-slot="sheet-content"]');
        const overlay = () =>
            document.querySelector('[data-slot="sheet-overlay"]');
        await nextTick();
        expect(content()?.classList.contains('pilot-theme')).toBe(false);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(false);
        page.component = 'Dashboard';
        await nextTick();
        expect(content()?.classList.contains('pilot-theme')).toBe(true);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(true);
        page.component = 'Orders/Index';
        await nextTick();
        expect(content()?.classList.contains('pilot-theme')).toBe(true);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(true);
        page.component = 'Welcome';
        await nextTick();
        expect(content()?.classList.contains('pilot-theme')).toBe(false);
        expect(overlay()?.classList.contains('pilot-theme')).toBe(false);
    });

    it('defaults a portal outside the layout to neutral tokens', async () => {
        const wrapper = mount(Portal);
        mountedWrappers.push(wrapper);
        await nextTick();
        expect(
            document
                .querySelector('[data-slot="dialog-content"]')
                ?.classList.contains('pilot-theme'),
        ).toBe(false);
        expect(
            document
                .querySelector('[data-slot="dialog-overlay"]')
                ?.classList.contains('pilot-theme'),
        ).toBe(false);
    });
});

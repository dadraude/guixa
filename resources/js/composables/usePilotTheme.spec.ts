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
import { toast } from 'vue-sonner';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

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
        vi.unstubAllGlobals();
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

    it('scopes landing to shell, dialog and sheet portals of the exact stock page only', async () => {
        const page = usePage();
        page.component = 'Orders/Other';
        const wrapper = mount(AppLayout, {
            slots: { default: () => [h(Portal), h(SheetPortal)] },
        });
        mountedWrappers.push(wrapper);
        await nextTick();
        const shell = wrapper.get('[data-test="shell"]');
        const dialog = () =>
            document.querySelector('[data-slot="dialog-content"]');
        const sheet = () =>
            document.querySelector('[data-slot="sheet-content"]');
        const landing = (element: Element | null | undefined) =>
            element?.classList.contains('landing');

        for (const component of [
            'Orders/Other',
            'Catalogue/Producers/Index/Extra',
            'Catalogue/Producers',
            'Catalogue/Producers/Products/Extra',
            'Catalogue/Producers/Product',
            'Catalogue/Producers/Availability/Extra',
            'Catalogue/Producers/WeeklyProduct/Create/Extra',
            'Manage/Stock/Movements',
            'Manage/Stock/Create',
            'Welcome',
        ]) {
            page.component = component;
            await nextTick();
            expect(shell.classes()).not.toContain('landing');
            expect(landing(dialog())).toBe(false);
            expect(landing(sheet())).toBe(false);
        }
        page.component = 'Manage/Stock/Index';
        await nextTick();
        expect(shell.classes()).toContain('landing');
        expect(shell.classes()).toContain('pilot-theme');
        expect(landing(dialog())).toBe(true);
        expect(landing(sheet())).toBe(true);
        for (const component of [
            'Orders/Index',
            'Orders/Edit',
            'Catalogue/Producers/Index',
            'Catalogue/Producers/Products',
            'Catalogue/Producers/Availability',
            'Catalogue/Producers/WeeklyProduct/Create',
            'Catalogue/Producers/WeeklyProduct/Edit',
        ]) {
            page.component = component;
            await nextTick();
            expect(shell.classes(), component).toContain('landing');
            expect(landing(dialog()), component).toBe(true);
            expect(landing(sheet()), component).toBe(true);
        }
        page.component = 'Orders/Other';
        await nextTick();
        expect(shell.classes()).not.toContain('landing');
        expect(landing(dialog())).toBe(false);
        expect(landing(sheet())).toBe(false);
        page.component = 'Manage/Stock/Index';
        await nextTick();
        expect(landing(dialog())).toBe(true);
    });

    it('does not apply landing to a portal outside the layout', async () => {
        const wrapper = mount(Portal);
        mountedWrappers.push(wrapper);
        await nextTick();
        expect(
            document
                .querySelector('[data-slot="dialog-content"]')
                ?.classList.contains('landing'),
        ).toBe(false);
    });

    it('adds and removes landing on dropdown, submenu, tooltip and toaster content', async () => {
        const { Toaster: RealToaster } = await vi.importActual<
            typeof import('@/components/ui/sonner')
        >('@/components/ui/sonner');
        const Overlays = defineComponent({
            setup() {
                return () => [
                    h(DropdownMenu, { open: true }, () => [
                        h(DropdownMenuTrigger, () => 'Menu'),
                        h(DropdownMenuContent, null, () =>
                            h(DropdownMenuSub, { open: true }, () => [
                                h(DropdownMenuSubTrigger, () => 'More'),
                                h(DropdownMenuSubContent, () => 'Sub'),
                            ]),
                        ),
                    ]),
                    h(TooltipProvider, () =>
                        h(Tooltip, { open: true }, () => [
                            h(TooltipTrigger, () => 'Tip'),
                            h(TooltipContent, () => 'Tooltip body'),
                        ]),
                    ),
                    h(RealToaster),
                ];
            },
        });
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
        const page = usePage();
        page.component = 'Orders/Other';
        const wrapper = mount(AppLayout, {
            slots: { default: () => h(Overlays) },
        });
        mountedWrappers.push(wrapper);
        toast('Scope probe');
        await nextTick();
        await nextTick();
        const targets = {
            dropdown: '[data-slot="dropdown-menu-content"]',
            submenu: '[data-slot="dropdown-menu-sub-content"]',
            tooltip: '[data-slot="tooltip-content"]',
            toaster: '[data-sonner-toaster]',
        };
        // The toaster renders in place (not portalled), so it lives in the wrapper.
        const find = (selector: string) =>
            document.querySelector(selector) ??
            wrapper.element.querySelector(selector);
        const has = (selector: string) =>
            find(selector)?.classList.contains('landing');
        for (const selector of Object.values(targets))
            expect(find(selector), selector).not.toBeNull();

        for (const component of ['Orders/Other', 'Manage/Stock/Create']) {
            page.component = component;
            await nextTick();
            for (const selector of Object.values(targets))
                expect(has(selector), `${component} ${selector}`).toBe(false);
        }
        for (const component of [
            'Orders/Index',
            'Orders/Edit',
            'Catalogue/Producers/Index',
            'Catalogue/Producers/Products',
            'Catalogue/Producers/Availability',
            'Catalogue/Producers/WeeklyProduct/Create',
            'Catalogue/Producers/WeeklyProduct/Edit',
        ]) {
            page.component = component;
            await nextTick();
            for (const [name, selector] of Object.entries(targets))
                expect(has(selector), `${component} ${name}`).toBe(true);
        }
        page.component = 'Manage/Stock/Index';
        await nextTick();
        for (const [name, selector] of Object.entries(targets)) {
            expect(has(selector), name).toBe(true);
            expect(
                find(selector)?.classList.contains('pilot-theme'),
                name,
            ).toBe(true);
        }
        page.component = 'Orders/Other';
        await nextTick();
        for (const [name, selector] of Object.entries(targets))
            expect(has(selector), name).toBe(false);
    });
});

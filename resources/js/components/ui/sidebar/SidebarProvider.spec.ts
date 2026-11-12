// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h, nextTick } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Sidebar from './Sidebar.vue';
import SidebarProvider from './SidebarProvider.vue';
import SidebarTrigger from './SidebarTrigger.vue';

const SidebarFixture = defineComponent({
    props: { defaultOpen: { type: Boolean, default: true } },
    setup(props) {
        return () =>
            h(SidebarProvider, { defaultOpen: props.defaultOpen }, () => [
                h(SidebarTrigger),
                h(Sidebar, null, () => 'Sidebar content'),
            ]);
    },
});

function app(defaultOpen: boolean) {
    const instance = createSSRApp(SidebarFixture, { defaultOpen });
    installTranslations(instance);
    return instance;
}

afterEach(() => {
    document.body.innerHTML = '';
    document.cookie = 'sidebar_state=; path=/; max-age=0';
    vi.restoreAllMocks();
});

describe('sidebar SSR hydration', () => {
    it.each([
        { name: 'expanded', open: true, serverIcon: 'lucide-panel-left-close' },
        { name: 'collapsed', open: false, serverIcon: 'lucide-panel-left-open' },
    ])('hydrates a mobile viewport from server $name state', async ({ open, serverIcon }) => {
        const originalMatchMedia = window.matchMedia;
        let client: ReturnType<typeof app> | undefined;
        try {
            // SSR has no browser media query. Make it available only for hydration.
            Object.defineProperty(window, 'matchMedia', { configurable: true, value: undefined });
            const html = await renderToString(app(open));
            expect(html).toContain(serverIcon);
            const root = document.createElement('div');
            root.innerHTML = html;
            document.body.append(root);
            const warnings = vi.spyOn(console, 'warn').mockImplementation(() => {});
            Object.defineProperty(window, 'matchMedia', {
                configurable: true,
                value: vi.fn(() => ({
                    matches: true,
                    addEventListener: vi.fn(),
                    removeEventListener: vi.fn(),
                })),
            });

            client = app(open);
            client.mount(root);
            await nextTick();
            expect(warnings.mock.calls).toEqual([]);
            expect(root.querySelector('[data-sidebar="trigger"] svg')?.classList.contains('lucide-panel-left-open')).toBe(true);
            expect(root.querySelector('[data-slot="sidebar"][data-state]')).toBeNull();
            root.querySelector<HTMLButtonElement>('[data-sidebar="trigger"]')?.click();
            await nextTick();
            expect(document.body.querySelector('[data-sidebar="sidebar"][data-mobile="true"]')).not.toBeNull();
        } finally {
            client?.unmount();
            Object.defineProperty(window, 'matchMedia', {
                configurable: true,
                value: originalMatchMedia,
            });
        }
    });

    it('keeps a persisted collapsed desktop sidebar and toggles it open', async () => {
        const originalMatchMedia = window.matchMedia;
        let client: ReturnType<typeof app> | undefined;
        try {
            Object.defineProperty(window, 'matchMedia', { configurable: true, value: undefined });
            const root = document.createElement('div');
            root.innerHTML = await renderToString(app(false));
            document.body.append(root);
            const warnings = vi.spyOn(console, 'warn').mockImplementation(() => {});
            Object.defineProperty(window, 'matchMedia', {
                configurable: true,
                value: vi.fn(() => ({
                    matches: false,
                    addEventListener: vi.fn(),
                    removeEventListener: vi.fn(),
                })),
            });

            client = app(false);
            client.mount(root);
            await nextTick();
            expect(warnings.mock.calls).toEqual([]);
            expect(root.querySelector('[data-slot="sidebar"]')?.getAttribute('data-state')).toBe('collapsed');
            root.querySelector<HTMLButtonElement>('[data-sidebar="trigger"]')?.click();
            await nextTick();
            expect(root.querySelector('[data-slot="sidebar"]')?.getAttribute('data-state')).toBe('expanded');
            expect(root.querySelector('[data-sidebar="trigger"] svg')?.classList.contains('lucide-panel-left-close')).toBe(true);
            expect(document.cookie).toContain('sidebar_state=true');
        } finally {
            client?.unmount();
            Object.defineProperty(window, 'matchMedia', {
                configurable: true,
                value: originalMatchMedia,
            });
        }
    });
});

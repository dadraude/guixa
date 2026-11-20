// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { nextTick } from 'vue';
import RolesIndex from './Index.vue';

const destroy = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    router: { delete: destroy, post: vi.fn(), reload: vi.fn() },
}));
vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string, replacements: Record<string, string> = {}) =>
        Object.entries(replacements).reduce(
            (text, [name, value]) => text.replace(`:${name}`, value),
            key,
        ),
}));

const holder = (accountId: string, name: string) => ({
    accountId,
    name,
    email: `${accountId}@example.org`,
    status: 'active' as const,
    isSelf: false,
    canRemove: true,
    removeBlockedReason: null,
});

// R26/S21 (T24): a role with no holder says so, a held role offers its remove control, and the page sends the
// existing removal request for the confirmed holder (the confirmation gate itself is RoleHolderRemove.spec.ts).
describe('role groups page', () => {
    let wrapper: ReturnType<typeof mountPage> | null = null;
    const mountPage = () =>
        mount(RolesIndex, {
            props: {
                groups: [
                    { role: 'finance', holders: [holder('anna', 'Anna')] },
                    { role: 'catalogue', holders: [] },
                ],
            } as never,
            attachTo: document.body,
        });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        document.body.innerHTML = '';
        destroy.mockReset();
    });

    it('says that a role nobody holds has no holder, and a held role offers its remove control', () => {
        wrapper = mountPage();
        const groups = wrapper.findAll('section');
        expect(groups).toHaveLength(2);
        expect(groups[0].text()).not.toContain('Nobody holds this role yet.');
        expect(groups[1].text()).toContain('Nobody holds this role yet.');
        expect(
            groups[0].find('[aria-label="Remove Finance from Anna"]').exists(),
        ).toBe(true);
    });

    it('sends the existing removal request for the confirmed holder', async () => {
        wrapper = mountPage();
        const remove = wrapper.get('[aria-label="Remove Finance from Anna"]');
        await remove.trigger('click');

        const confirm = [
            ...document.body.querySelectorAll('[role="dialog"] button'),
        ].find((button) => button.textContent?.trim() === 'Remove role');
        (confirm as HTMLElement).click();
        await nextTick();

        expect(destroy).toHaveBeenCalledTimes(1);
        expect(destroy.mock.calls[0][0]).toBe(
            '/manage/roles/finance/holders/anna',
        );
    });
});

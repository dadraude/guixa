// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { nextTick } from 'vue';
import RoleHolderRemove from './RoleHolderRemove.vue';

vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string, replacements: Record<string, string> = {}) =>
        Object.entries(replacements).reduce(
            (text, [name, value]) => text.replace(`:${name}`, value),
            key,
        ),
}));

const ownManagerWarning =
    'You are removing your own Manager role. You will lose access to the management screens right away.';

const holder = (overrides = {}) => ({
    accountId: 'anna',
    name: 'Anna',
    email: 'anna@example.org',
    status: 'active' as const,
    isSelf: false,
    canRemove: true,
    removeBlockedReason: null,
    ...overrides,
});

let wrapper: ReturnType<typeof mountRemove> | null = null;

function mountRemove(
    props: { holder?: object; role?: string; roleLabel?: string } = {},
) {
    return mount(RoleHolderRemove, {
        props: {
            holder: holder(),
            role: 'finance',
            roleLabel: 'Finance',
            processing: false,
            ...props,
        } as never,
        attachTo: document.body,
    });
}

const dialog = () => document.body.querySelector('[role="dialog"]');
const dialogButton = (label: string) =>
    [...(dialog()?.querySelectorAll('button') ?? [])].find(
        (button) => button.textContent?.trim() === label,
    );

async function open(w: ReturnType<typeof mountRemove>) {
    await w.get('button').trigger('click');
    await nextTick();
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    document.body.innerHTML = '';
});

describe('RoleHolderRemove', () => {
    it('emits nothing when the confirmation is cancelled', async () => {
        wrapper = mountRemove();
        await open(wrapper);

        expect(dialog()).not.toBeNull();
        dialogButton('Cancel')!.click();
        await nextTick();

        expect(wrapper.emitted('remove')).toBeUndefined();
    });

    it('emits the holder account id when confirmed', async () => {
        wrapper = mountRemove();
        await open(wrapper);

        dialogButton('Remove role')!.click();
        await nextTick();

        expect(wrapper.emitted('remove')).toEqual([['anna']]);
    });

    it('disables the button and links it to the visible reason when removal is blocked', () => {
        wrapper = mountRemove({
            holder: holder({
                canRemove: false,
                removeBlockedReason: 'Last active manager.',
            }),
        });

        const button = wrapper.get('button');
        expect(button.attributes('disabled')).toBeDefined();
        const reason = wrapper.get('p');
        expect(reason.text()).toBe('Last active manager.');
        expect(button.attributes('aria-describedby')).toBe(
            reason.attributes('id'),
        );
    });

    it('warns about lost access only when a manager removes their own Manager role', async () => {
        const warningFor = async (overrides: object, role: string) => {
            wrapper = mountRemove({
                holder: holder(overrides),
                role,
                roleLabel: role,
            });
            await open(wrapper);
            const text = dialog()?.textContent ?? '';
            wrapper.unmount();
            wrapper = null;
            document.body.innerHTML = '';
            return text.includes(ownManagerWarning);
        };

        expect(await warningFor({ isSelf: true }, 'manager')).toBe(true);
        expect(await warningFor({ isSelf: false }, 'manager')).toBe(false);
        expect(await warningFor({ isSelf: true }, 'finance')).toBe(false);
    });
});

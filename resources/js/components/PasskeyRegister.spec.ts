// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { reset } from 'laravel-vue-i18n';
import type { App } from 'vue';
import {
    PasskeyError,
    PasskeyExistsError,
    UserCancelledError,
} from '@laravel/passkeys';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import { installTranslations } from '@/lib/i18n';
import PasskeyRegister from './PasskeyRegister.vue';

const hook = vi.hoisted(() => ({ error: null as unknown }));
vi.mock('@laravel/passkeys/vue', async () => {
    const { ref } = await import('vue');
    return {
        usePasskeyRegister: () => ({
            register: vi.fn(),
            isLoading: ref(false),
            errorInstance: ref(hook.error),
            isSupported: ref(true),
        }),
    };
});

const mountRegister = () =>
    mount(PasskeyRegister, {
        global: {
            plugins: [{ install: (app: App) => installTranslations(app) }],
            directives: { focus: () => {} },
        },
    });
afterEach(() => {
    hook.error = null;
    reset();
    vi.unstubAllGlobals();
});

it.each([
    [
        new UserCancelledError(),
        'S’ha cancel·lat l’operació amb la clau d’accés.',
    ],
    [
        new PasskeyExistsError(),
        'Aquest dispositiu ja està registrat com a clau d’accés.',
    ],
    [
        new PasskeyError('Failed to fetch'),
        'S’ha produït un error amb la clau d’accés. Torna-ho a provar.',
    ],
    [
        new PasskeyError('No s’ha pogut registrar aquesta clau d’accés.'),
        'No s’ha pogut registrar aquesta clau d’accés.',
    ],
])(
    'renders translated browser registration failures',
    async (failure, text) => {
        hook.error = failure;
        const wrapper = mountRegister();
        await wrapper.get('button').trigger('click');
        expect(wrapper.text()).toContain(text);
        if (failure.message !== text)
            expect(wrapper.text()).not.toContain(failure.message);
    },
);

it('uses a translatable connector in the generated default name', async () => {
    vi.stubGlobal('navigator', {
        userAgent:
            'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
    });
    const wrapper = mountRegister();
    await wrapper.get('button').trigger('click');
    expect(
        (wrapper.get('#passkey-name').element as HTMLInputElement).value,
    ).toBe('Chrome a Mac');
});

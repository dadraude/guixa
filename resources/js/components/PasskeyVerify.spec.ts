// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { reset } from 'laravel-vue-i18n';
import type { App } from 'vue';
import {
    NotSupportedError,
    PasskeyError,
    UserCancelledError,
} from '@laravel/passkeys';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import { installTranslations } from '@/lib/i18n';
import PasskeyVerify from './PasskeyVerify.vue';

const hook = vi.hoisted(() => ({ error: null as unknown }));
vi.mock('@laravel/passkeys/vue', async () => {
    const { ref } = await import('vue');
    return {
        usePasskeyVerify: () => ({
            verify: vi.fn(),
            isLoading: ref(false),
            errorInstance: ref(hook.error),
            isSupported: ref(true),
        }),
    };
});

afterEach(() => {
    hook.error = null;
    reset();
});
const mountVerify = (props = {}) =>
    mount(PasskeyVerify, {
        props,
        global: {
            plugins: [{ install: (app: App) => installTranslations(app) }],
        },
    });

it.each([
    [
        new UserCancelledError(),
        'S’ha cancel·lat l’operació amb la clau d’accés.',
    ],
    [new NotSupportedError(), 'Aquest navegador no admet claus d’accés.'],
    [
        new PasskeyError('An unknown error occurred.'),
        'S’ha produït un error amb la clau d’accés. Torna-ho a provar.',
    ],
    [
        new PasskeyError('Unexpected transport error'),
        'S’ha produït un error amb la clau d’accés. Torna-ho a provar.',
    ],
    [
        new PasskeyError(
            'No s’ha pogut verificar la clau d’accés. Torna-ho a provar.',
        ),
        'No s’ha pogut verificar la clau d’accés. Torna-ho a provar.',
    ],
])('renders translated verification failures', (failure, text) => {
    hook.error = failure;
    const wrapper = mountVerify();
    expect(wrapper.text()).toContain(text);
    if (failure.message !== text)
        expect(wrapper.text()).not.toContain(failure.message);
});

it('renders translated caller labels without changing the passkey action', () => {
    const wrapper = mountVerify({
        label: 'Confirma amb una clau d’accés',
        loadingLabel: 'S’està confirmant...',
        separator: 'O confirma amb la contrasenya',
    });
    expect(wrapper.text()).toContain('O confirma amb la contrasenya');
    expect(wrapper.text()).toContain('Confirma amb una clau d’accés');
});

// @vitest-environment jsdom
import { renderToString } from 'vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import { expect, it, vi } from 'vite-plus/test';
import AuthSimpleLayout from './AuthSimpleLayout.vue';
import { installTranslations } from '@/lib/i18n';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
}));

it('translates auth headings at render after a cold component import', async () => {
    const app = createSSRApp(AuthSimpleLayout, {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    });
    installTranslations(app, 'ca');

    const html = await renderToString(app);

    expect(html).toContain('Inicia sessió al teu compte');
    expect(html).toContain(
        'Introdueix l’adreça electrònica i la contrasenya per iniciar sessió',
    );
    expect(html).not.toContain('Log in to your account');
});

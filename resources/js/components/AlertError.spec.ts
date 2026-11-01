// @vitest-environment jsdom
import { renderToString } from 'vue/server-renderer';
import { createSSRApp } from 'vue';
import { expect, it } from 'vite-plus/test';
import AlertError from './AlertError.vue';
import { installTranslations } from '@/lib/i18n';

it('translates the default alert title when the component was imported before locale setup', async () => {
    const app = createSSRApp(AlertError, {
        errors: ['A sample validation message'],
    });
    installTranslations(app, 'ca');

    const html = await renderToString(app);

    expect(html).toContain('Alguna cosa ha anat malament.');
    expect(html).not.toContain('Something went wrong.');
    expect(html).toContain('A sample validation message');
});

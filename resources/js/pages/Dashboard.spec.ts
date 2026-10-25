import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Dashboard from './Dashboard.vue';

vi.mock('@inertiajs/vue3', () => ({ Head: () => null }));

describe('dashboard presentation', () => {
    it('renders the existing translated dashboard title with the placeholders', async () => {
        const app = createSSRApp(Dashboard);
        installTranslations(app);
        const html = await renderToString(app);

        expect(html).toContain('Inici');
        expect(html.match(/pilot-panel/g)).toHaveLength(4);
    });
});

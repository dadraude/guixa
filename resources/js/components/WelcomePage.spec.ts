import { describe, expect, it } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import WelcomePage from './WelcomePage.vue';

async function render(actionHref: string) {
    const app = createSSRApp(WelcomePage, {
        actionHref,
    });
    installTranslations(app, 'ca');
    return renderToString(app);
}

describe('public cooperative landing', () => {
    it('shows the Catalan sections and remaining in-page links', async () => {
        const html = await render('/login');

        expect(html).toContain('Alimentem el canvi.');
        expect(html).toContain('Consumim amb sentit.');
        expect(html).toContain('De la comanda a la cistella.');
        expect(html).toContain('Ens vols conèixer?');
        expect(html).toContain('href="#cooperative"');
        expect(html).not.toContain('href="#how-it-works"');
        expect(html).not.toContain('Com funciona');
        expect(html).toContain('href="#contact"');
        expect(html).toContain('href="mailto:laguixamanresa@gmail.com"');
    });

    it.each(['/login', '/dashboard'])(
        'keeps member access at %s',
        async (href) => {
            const html = await render(href);

            expect(html).toContain('Accés de sòcies');
            expect(html).toContain(`href="${href}"`);
        },
    );
});

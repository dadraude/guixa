import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h('a', attrs, slots.default?.()),
    }),
}));

const row = {
    id: 'basket-one',
    householdName: 'La Figuera',
    state: 'validated',
    status: 'validated',
    totalCents: 2300,
    createdAt: '2026-10-30 10:00',
    validatedAt: '2026-11-05 08:15',
    pendingCollection: true,
};

async function render(baskets: App.Http.BasketSummaryRow[], pending: boolean) {
    const app = createSSRApp(Index, { baskets, pending });
    installTranslations(app);
    return renderToString(app);
}

describe('finance basket list', () => {
    it('titles the pending view, links back to all baskets and shows the validation date', async () => {
        const html = await render([row], true);

        expect(html).toMatch(/<h1[^>]*>\s*Recollides pendents/);
        expect(html).toMatch(
            /data-test="all-baskets"[^>]*>\s*Totes les cistelles/,
        );
        expect(html).toMatch(
            /data-test="validated-at"[^>]*>[^<]*5 de nov\. 2026, 8:15/,
        );
        expect(html).not.toContain('30 d’oct. 2026');
    });

    it('keeps the plain title, creation date and no back link in the full list', async () => {
        const html = await render([row], false);

        expect(html).toMatch(/<h1[^>]*>\s*Cistelles/);
        expect(html).not.toContain('data-test="all-baskets"');
        expect(html).toContain('30 d’oct. 2026');
    });
});

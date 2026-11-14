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
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ processing: false })),
    }),
}));

const item = {
    id: 'r-1',
    name: 'Ampolla 1 L',
    amount: '0.30',
    active: true,
};

async function render(items: (typeof item)[]) {
    const app = createSSRApp(Index, { items });
    installTranslations(app);
    return renderToString(app);
}

function row(html: string, name: string) {
    return html.match(
        new RegExp(`<tr[^>]*>(?:(?!</tr>).)*${name}.*?</tr>`),
    )![0];
}

describe('Return item list', () => {
    it('says Active or Deactivated on each item through its switch', async () => {
        const html = await render([
            item,
            { ...item, id: 'r-2', name: 'Pot 500 ml', active: false },
        ]);

        expect(row(html, 'Ampolla 1 L')).toContain('>Actiu<');
        expect(row(html, 'Pot 500 ml')).toContain('>Desactivat<');
    });

    it('keeps an inactive item as legible as an active one', async () => {
        const html = await render([{ ...item, active: false }]);

        expect(row(html, 'Ampolla 1 L')).not.toContain('text-muted-foreground');
    });

    it('aligns amounts as tabular figures', async () => {
        const html = await render([item]);

        expect(row(html, 'Ampolla 1 L')).toMatch(
            /<td[^>]*tabular-nums[^>]*>(?:(?!<\/td>).)*0,30/,
        );
    });

    it('offers an edit link and the create link', async () => {
        const html = await render([item]);

        expect(html).toContain('Article retornable nou');
        expect(html).toContain('aria-label="Edita: Ampolla 1 L"');
    });

    it('says when there are no return items', async () => {
        expect(await render([])).toContain(
            'Encara no hi ha articles retornables.',
        );
    });
});

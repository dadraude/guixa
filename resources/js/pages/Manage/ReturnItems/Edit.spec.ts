import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Edit from './Edit.vue';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ ...form })),
    }),
}));

const item = {
    id: 'r-1',
    name: 'Pot de vidre',
    amount: '0.25',
    active: true,
};

async function render(state: Partial<typeof form> = {}) {
    form.errors = state.errors ?? {};
    form.processing = state.processing ?? false;
    const app = createSSRApp(Edit, { item });
    installTranslations(app);
    return renderToString(app);
}

function submit(html: string) {
    return html.match(
        /<button[^>]*data-test="save-return-item".*?<\/button>/,
    )![0];
}

describe('Return item editing', () => {
    it('titles the page with the item and prefills its fields', async () => {
        const html = await render();
        expect(html).toContain('Edita Pot de vidre');
        expect(html).toMatch(/<input[^>]*name="name"[^>]*value="Pot de vidre"/);
        expect(html).toMatch(/<input[^>]*name="amount"[^>]*value="0.25"/);
    });

    it('offers an enabled save submit', async () => {
        const html = await render();
        expect(submit(html)).toMatch(
            />(<!--\[-->)*Desa(<!--\]-->)*<\/button>$/,
        );
        expect(submit(html)).not.toMatch(/ disabled[ >]/);
    });

    it.each([
        ['name', 'El nom ja existeix.'],
        ['amount', 'L’import ha de ser positiu.'],
    ])('shows the %s error right after its input', async (field, message) => {
        const html = await render({ errors: { [field]: message } });
        expect(html).toMatch(
            new RegExp(
                `<input[^>]*id="${field}"[^>]*>\\s*(<!---->)?<div[^>]*>\\s*<p class="text-sm text-destructive">\\s*${message}\\s*</p>`,
            ),
        );
    });

    it('disables the submit while processing', async () => {
        expect(submit(await render({ processing: true }))).toMatch(
            / disabled[ >]/,
        );
    });
});

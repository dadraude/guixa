import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Create from './Create.vue';

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

async function render(state: Partial<typeof form> = {}) {
    form.errors = state.errors ?? {};
    form.processing = state.processing ?? false;
    const app = createSSRApp(Create);
    installTranslations(app);
    return renderToString(app);
}

function submit(html: string) {
    return html.match(
        /<button[^>]*data-test="save-return-item".*?<\/button>/,
    )![0];
}

describe('Return item creation', () => {
    it('titles the page and offers an enabled create submit', async () => {
        const html = await render();
        expect(html).toContain('Article retornable nou');
        expect(submit(html)).toMatch(
            />(<!--\[-->)*Crea(<!--\]-->)*<\/button>$/,
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

    it('shows a domain error above the fields', async () => {
        const html = await render({ errors: { domain: 'No s’ha pogut.' } });
        expect(html).toContain('No s’ha pogut.');
    });

    it('disables the submit while processing', async () => {
        expect(submit(await render({ processing: true }))).toMatch(
            / disabled[ >]/,
        );
    });
});

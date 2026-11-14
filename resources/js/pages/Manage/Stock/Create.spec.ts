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
                h('form', slots.default?.({ ...form })),
    }),
}));

const producer = { id: 'producer-1', name: 'La Figuera' };

async function render(state: Partial<typeof form> = {}) {
    form.errors = state.errors ?? {};
    form.processing = state.processing ?? false;
    const app = createSSRApp(Create, { producer });
    installTranslations(app);
    return renderToString(app);
}

function submit(html: string) {
    return html.match(
        /<button[^>]*data-test="save-stock-product".*?<\/button>/,
    )![0];
}

const fieldErrors = {
    name: 'El nom ja existeix.',
    unit: 'La unitat és obligatòria.',
    price: 'El preu ha de ser un import positiu.',
    containers_per_unit: 'Els envasos han de ser un número.',
};

describe('Stock product creation in the Catàleg area', () => {
    it('titles the page and names the supplying producer', async () => {
        const html = await render();
        expect(html).toContain('Producte d’estoc nou');
        expect(html).toContain('La Figuera');
    });

    it('offers an enabled create submit with a persistent label per field', async () => {
        const html = await render();
        expect(submit(html)).toMatch(
            />(<!--\[-->)*Crea(<!--\]-->)*<\/button>$/,
        );
        expect(submit(html)).not.toMatch(/ disabled[ >]/);
        for (const id of ['name', 'unit', 'price', 'containers_per_unit'])
            expect(html).toContain(`for="${id}"`);
    });

    it.each(Object.entries(fieldErrors))(
        'shows the %s error right after its input',
        async (field, message) => {
            const html = await render({ errors: { [field]: message } });
            expect(html).toMatch(
                new RegExp(
                    `<input[^>]*id="${field}"[^>]*>\\s*(<!---->)?<div[^>]*>\\s*<p class="text-sm text-destructive">\\s*${message}\\s*</p>`,
                ),
            );
        },
    );

    it('shows all four errors at once', async () => {
        const html = await render({ errors: fieldErrors });
        for (const message of Object.values(fieldErrors))
            expect(html).toContain(message);
    });

    it('disables the submit while processing', async () => {
        const html = await render({ processing: true });
        expect(submit(html)).toMatch(/ disabled[ >]/);
    });
});

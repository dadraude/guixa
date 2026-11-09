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

const producer = {
    id: 'p-1',
    name: 'La Figuera',
    active: true,
    cutoffDaysBefore: 3,
    cutoffTime: '20:00',
    accountEmail: null,
    accountInvitationPending: false,
};

async function render(producers: (typeof producer)[]) {
    const app = createSSRApp(Index, { producers });
    installTranslations(app);
    return renderToString(app);
}

describe('Catàleg producer list', () => {
    it('marks deactivated producers and offers only the products link (R5)', async () => {
        const html = await render([
            producer,
            { ...producer, id: 'p-2', name: 'Closed dairy', active: false },
        ]);

        expect(html).toContain('La Figuera');
        expect(html).toContain('Actiu');
        expect(html).toContain('Closed dairy');
        expect(html).toContain('Desactivat');
        expect(html.match(/Consulta els productes de/g)).toHaveLength(2);
        expect(html).not.toContain('<form');
        expect(html).not.toContain('Convida');
        expect(html).not.toContain('data-test="create-producer"');
    });

    it('says when there are no producers', async () => {
        expect(await render([])).toContain('Encara no hi ha productors.');
    });
});

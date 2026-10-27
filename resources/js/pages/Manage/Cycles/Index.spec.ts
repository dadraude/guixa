// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ errors: {}, processing: false })),
    }),
}));

describe('manager cycles', () => {
    it('keeps closed history visible without cycle editing actions', async () => {
        const html = await renderToString(
            createSSRApp(Index, {
                cycles: [
                    {
                        id: 'cycle',
                        pickupDate: '2026-11-07',
                        cancelled: false,
                        closed: true,
                        participants: [
                            {
                                producerId: 'baker',
                                producerName: 'Forn',
                                participating: true,
                                cutoff: null,
                                open: false,
                            },
                        ],
                    },
                ],
                producers: [{ id: 'grower', name: 'Hort' }],
                pickupWeekday: 6,
            }),
        );
        expect(html).toContain('Recollida 07/11/2026');
        expect(html).toContain('Tancat');
        expect(html).toContain('data-test="summary-cycle"');
        expect(html).not.toContain('data-test="manage-cycle"');
        expect(html).not.toContain('data-test="add-cycle-grower"');
        expect(html).toContain('<dialog');
    });

    it('offers per-producer management and a real add-producer form for open cycles', async () => {
        const html = await renderToString(
            createSSRApp(Index, {
                cycles: [
                    {
                        id: 'cycle',
                        pickupDate: '2026-11-07',
                        cancelled: false,
                        closed: false,
                        participants: [
                            {
                                producerId: 'baker',
                                producerName: 'Forn',
                                participating: true,
                                cutoff: null,
                                open: true,
                            },
                        ],
                    },
                ],
                producers: [
                    { id: 'baker', name: 'Forn' },
                    { id: 'grower', name: 'Hort' },
                ],
                pickupWeekday: 6,
            }),
        );
        expect(html).toContain('data-test="manage-cycle-baker"');
        expect(html).toContain('data-test="add-cycle-grower"');
        expect(html).toContain('sense tancament programat');
    });
});

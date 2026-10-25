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

describe('closed cycles', () => {
    it('keeps history visible and removes cycle editing actions', async () => {
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
                                producerName: 'Baker',
                                participating: true,
                                cutoff: null,
                                open: false,
                            },
                        ],
                    },
                ],
                producers: [{ id: 'grower', name: 'Grower' }],
                pickupWeekday: 6,
            }),
        );
        expect(html).toContain('Cycle closed');
        expect(html).not.toContain('data-test="cancel-cycle"');
        expect(html).not.toContain('data-test="add-cycle-grower"');
        expect(html).not.toContain('data-test="close-now-cycle-baker"');
        expect(html).toContain('data-test="summary-cycle"');
    });
});

import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Head: () => null,
        Link: defineComponent({
            setup:
                (_, { slots }) =>
                () =>
                    h('a', slots.default?.()),
        }),
        Form: defineComponent({
            setup:
                (_, { attrs, slots }) =>
                () =>
                    h('form', attrs, slots.default?.()),
        }),
    };
});

describe('pickups index', () => {
    it('lists the pickups and offers to open one for a household without', async () => {
        const html = await renderToString(
            createSSRApp(Index, {
                cycleId: 'cycle',
                pickups: [
                    {
                        householdId: 'a',
                        householdName: 'Household A',
                        status: 'pending',
                        totalCents: 3200,
                    },
                ],
                households: [{ id: 'k', name: 'Household K' }],
            }),
        );

        expect(html).toContain('Household A');
        expect(html).toContain('Pending');
        expect(html).toContain('32,00 €');
        expect(html).toContain('data-test="open-k"');
    });

    it('says when no household has a pickup', async () => {
        const html = await renderToString(
            createSSRApp(Index, {
                cycleId: 'cycle',
                pickups: [],
                households: [],
            }),
        );

        expect(html).toContain('data-test="no-pickups"');
        expect(html).not.toContain('Open a pickup');
    });
});

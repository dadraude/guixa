import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Head: defineComponent({
            props: { title: String },
            setup: (props) => () => h('title', props.title),
        }),
        Link: defineComponent({
            setup:
                (_, { attrs, slots }) =>
                () =>
                    h('a', attrs, slots.default?.()),
        }),
    };
});

const cycle = {
    cycleId: 'cycle',
    pickupDate: '2026-11-07',
    closed: false,
    producers: [
        { producerId: 'baker', producerName: 'Baker', state: 'sent' },
        { producerId: 'dairy', producerName: 'Dairy', state: 'distributed' },
    ],
};

const render = (cycles: unknown[]) =>
    renderToString(createSSRApp(Index, { cycles }));

// The component name `Receptions/Index` is asserted by ReceptionPageTest.php (S7, R7).
describe('reception list page', () => {
    it('shows the title, one row per producer with its state and link, and marks only the closed cycle', async () => {
        const html = await render([
            cycle,
            { ...cycle, cycleId: 'closed-cycle', closed: true },
        ]);

        expect(html).toContain('<title>Receptions</title>');
        expect(html).toContain('data-test="cycle-cycle"');
        expect(html).toContain('data-test="producer-baker"');
        expect(html).toContain('data-test="producer-dairy"');
        expect(html).toContain('Sent');
        expect(html).toContain('Distributed');
        expect(html).toContain('Open reception of Baker');
        expect(html).toContain('data-test="cycle-closed-cycle"');
        expect(html.match(/Closed/g)).toHaveLength(1);
        expect(html).not.toContain('data-test="no-receptions"');
    });

    it('shows the empty state when no producer order has been sent', async () => {
        const html = await render([]);

        expect(html).toContain('data-test="no-receptions"');
        expect(html).toContain('No producer order has been sent yet.');
        expect(html).not.toContain('data-test="cycle-');
    });
});

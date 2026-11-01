import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
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
    const render = (props: Record<string, unknown>) => {
        const app = createSSRApp(Index, props);
        installTranslations(app);
        return renderToString(app);
    };

    it('shows a closed cycle without offering another pickup', async () => {
        const html = await render({
            cycleId: 'cycle',
            closed: true,
            pickups: [
                {
                    householdId: 'a',
                    householdName: 'A',
                    status: 'not_collected',
                    totalCents: 600,
                },
            ],
            households: [{ id: 'k', name: 'K' }],
        });
        expect(html).toContain('data-test="cycle-closed"');
        expect(html).not.toContain('data-test="open-k"');
        expect(html).toContain('No recollida');
    });

    it('lists the pickups and offers to open one for a household without', async () => {
        const html = await render({
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
        });

        expect(html).toContain('Household A');
        expect(html).toContain('Pendent');
        expect(html).toContain('32,00\u00a0€');
        expect(html).toContain('data-test="open-k"');
    });

    it('says when no household has a pickup', async () => {
        const html = await render({
            cycleId: 'cycle',
            pickups: [],
            households: [],
        });

        expect(html).toContain('data-test="no-pickups"');
        expect(html).not.toContain('Obre una recollida');
    });
});

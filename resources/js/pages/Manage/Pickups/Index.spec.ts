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

    it('shows a closed cycle that can still be collected', async () => {
        const html = await render({
            cycleId: 'cycle',
            closed: true,
            pickups: [
                {
                    basketId: 'basket-a',
                    householdId: 'a',
                    householdName: 'A',
                    status: 'collected',
                    totalCents: 600,
                },
            ],
        });
        expect(html).toContain('data-test="cycle-closed"');
        expect(html).toContain('Recollida');
    });

    it('lists each weekly basket with its state and total, validated ones pending collection', async () => {
        const html = await render({
            cycleId: 'cycle',
            pickups: [
                {
                    basketId: 'basket-a',
                    householdId: 'a',
                    householdName: 'Household A',
                    status: 'draft',
                    totalCents: 3200,
                },
                {
                    basketId: 'basket-b',
                    householdId: 'b',
                    householdName: 'Household B',
                    status: 'validated',
                    totalCents: 1000,
                },
                {
                    basketId: 'basket-c',
                    householdId: 'c',
                    householdName: 'Household C',
                    status: 'nothing_to_collect',
                    totalCents: 0,
                },
            ],
        });

        expect(html).toContain('Household A');
        expect(html).toContain('Esborrany');
        expect(html).toContain('Pendent de recollida');
        expect(html).toContain('Res per recollir');
        expect(html).toContain('32,00\u00a0€');
    });

    it('says when no household has a weekly basket', async () => {
        const html = await render({ cycleId: 'cycle', pickups: [] });

        expect(html).toContain('data-test="no-pickups"');
    });

    it('marks a late basket as its own row keyed by basket id (R19)', async () => {
        const row = (basketId: string, late: boolean) => ({
            basketId,
            householdId: 'a',
            householdName: 'Household A',
            status: 'draft',
            totalCents: 500,
            late,
        });
        const html = await render({
            cycleId: 'cycle',
            pickups: [row('basket-base', false), row('basket-late', true)],
        });

        expect(html).toContain('data-test="pickup-basket-base"');
        expect(html).toContain('data-test="pickup-basket-late"');
        expect(html).not.toContain('data-test="pickup-a"');
        expect(html).toContain('data-test="late-basket-basket-late"');
        expect(html).not.toContain('data-test="late-basket-basket-base"');
        expect(html).toContain('Cistella tardana');
    });
});

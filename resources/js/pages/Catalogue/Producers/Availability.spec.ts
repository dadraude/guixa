import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Availability from './Availability.vue';

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
                h('form', slots.default?.({ errors: {}, processing: false })),
    }),
}));

const producer = { id: 'producer-1', name: 'Q', active: true };
const cycle = (canChange: boolean) => ({
    cycleId: 'cycle-1',
    pickupDate: '2026-11-14',
    canChange,
    products: [
        { productId: 'p-1', name: 'Tomàquet', unit: 'kg', available: true },
        { productId: 'p-2', name: 'Bròquil', unit: 'kg', available: false },
    ],
});

async function render(props: {
    producer: typeof producer;
    cycles: ReturnType<typeof cycle>[];
}) {
    const app = createSSRApp(Availability, props);
    installTranslations(app);
    return renderToString(app);
}

describe('Producer availability in the Catàleg area', () => {
    it('offers a toggle per product that flips its current availability (S13)', async () => {
        const html = await render({ producer, cycles: [cycle(true)] });
        expect(html).toContain('data-test="toggle-p-1"');
        expect(html).toContain('Marca com a no disponible');
        expect(html).toContain('Marca com a disponible');
        expect(html).toMatch(/name="available" value="0"/);
        expect(html).toMatch(/name="available" value="1"/);
    });

    it('shows no toggle once the order is sent, only the state (R3c)', async () => {
        const html = await render({ producer, cycles: [cycle(false)] });
        expect(html).toContain('No disponible');
        expect(html).not.toContain('data-test="toggle-p-1"');
        expect(html).not.toContain('<form');
    });

    it('marks a deactivated producer and shows no cycle totals or actions (R5, D9)', async () => {
        const html = await render({
            producer: { ...producer, active: false },
            cycles: [cycle(true)],
        });
        expect(html).toContain('data-test="producer-deactivated"');
        expect(html).toContain('Desactivat');
        expect(html).not.toMatch(/total|quantity/i);
    });

    it('tells there is nothing to change when no cycle is open', async () => {
        const html = await render({ producer, cycles: [] });
        expect(html).toContain('data-test="no-cycles"');
    });
});

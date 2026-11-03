// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import {
    createApp,
    createSSRApp,
    defineComponent,
    h,
    nextTick,
    type Component,
} from 'vue';
import { router } from '@inertiajs/vue3';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Edit from './Edit.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: defineComponent({
        setup:
            (_, { attrs, slots }) =>
            () =>
                h('a', attrs, slots.default?.()),
    }),
    usePage: () => ({ props: { errors: {} } }),
    router: { put: vi.fn(), delete: vi.fn() },
}));

const order = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    cancelled: false,
    producers: [
        {
            producerId: 'baker',
            producerName: 'Forn de la plaça',
            open: true,
            products: [
                {
                    id: 'bread',
                    name: 'Pa de pagès',
                    unit: 'unitat',
                    price: '3.50',
                    variable: false,
                },
            ],
        },
    ],
    lines: [],
};

async function render(component: Component, props: Record<string, unknown>) {
    const app = createSSRApp(component, props);
    installTranslations(app);
    return renderToString(app);
}

describe('member order editor presentation', () => {
    it('keeps the explicit Enter save request and its partial reload', async () => {
        const put = vi.spyOn(router, 'put');
        const element = document.createElement('div');
        document.body.append(element);
        const app = createApp(Edit, { order });
        installTranslations(app);
        app.mount(element);

        const quantity =
            element.querySelector<HTMLInputElement>('#quantity-bread')!;
        quantity.value = '2';
        quantity.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
        expect(
            element.querySelector('[data-test="order-total"]')?.textContent,
        ).toContain('7,00');
        quantity.dispatchEvent(
            new KeyboardEvent('keydown', { bubbles: true, key: 'Enter' }),
        );

        expect(put).toHaveBeenCalledWith(
            '/cycles/cycle/order/lines/bread',
            { quantity: '2' },
            expect.objectContaining({
                only: ['order', 'errors'],
                preserveScroll: true,
            }),
        );
        app.unmount();
        element.remove();
    });

    it('labels quantity inputs and keeps explicit save and the running total', async () => {
        const html = await render(Edit, { order });

        expect(html).toContain('La teva comanda');
        expect(html).toContain('for="quantity-bread"');
        expect(html).toContain('id="quantity-bread"');
        expect(html).toContain('Desa');
        expect(html).toContain('0,00');
        expect(html).toMatch(
            /disabled[^>]*data-test="save-bread"|data-test="save-bread"[^>]*disabled/,
        );
    });

    it('names each row control after its product without hidden text', () => {
        const rowMarkup = async (order: unknown) => {
            const element = document.createElement('div');
            document.body.append(element);
            const app = createApp(Edit, { order });
            installTranslations(app);
            app.mount(element);
            await nextTick();
            const row = element.querySelector('li')!;
            const result = {
                hidden: row.querySelectorAll('.sr-only').length,
                names: [...row.querySelectorAll('[aria-label]')].map((node) =>
                    node.getAttribute('aria-label'),
                ),
            };
            app.unmount();
            element.remove();
            return result;
        };
        const marked = {
            ...order,
            producers: [{ ...order.producers[0], open: false }],
            lines: [
                {
                    productId: 'bread',
                    producerId: 'baker',
                    producerName: 'Forn de la plaça',
                    name: 'Pa de pagès',
                    unit: 'unitat',
                    price: '3.50',
                    variable: false,
                    quantity: '2.000',
                    notDelivered: true,
                },
            ],
        };

        return Promise.all([rowMarkup(order), rowMarkup(marked)]).then(
            ([plain, removable]) => {
                expect(plain.hidden).toBe(0);
                expect(plain.names).toEqual([
                    'Quantitat — Pa de pagès',
                    'Desa — Pa de pagès',
                ]);
                expect(removable.hidden).toBe(0);
                expect(removable.names).toContain('Elimina — Pa de pagès');
            },
        );
    });

    it('retains the cancelled notice without any product controls', async () => {
        const html = await render(Edit, {
            order: { ...order, cancelled: true },
        });

        expect(html).toContain('Aquest cicle s’ha cancel·lat');
        expect(html).not.toContain('quantity-bread');
    });

    it('keeps closed rows read-only and marked lines removable with a zero amount', async () => {
        const html = await render(Edit, {
            order: {
                ...order,
                producers: [{ ...order.producers[0], open: false }],
                lines: [
                    {
                        productId: 'bread',
                        producerId: 'baker',
                        producerName: 'Forn de la plaça',
                        name: 'Pa de pagès',
                        unit: 'unitat',
                        price: '3.50',
                        variable: false,
                        quantity: '2.000',
                        notDelivered: true,
                    },
                ],
            },
        });

        expect(html).toContain('No es lliurarà');
        expect(html).toContain('Elimina');
        expect(html).toContain('data-test="remove-bread"');
        expect(html).toMatch(
            /disabled[^>]*data-test="quantity-bread"|data-test="quantity-bread"[^>]*disabled/,
        );
        expect(html).toContain('0,00');
    });

    it('links the settled cycle to the household basket, where ordered versus actual is shown (R23a)', async () => {
        const html = await render(Edit, {
            order: { ...order, basketId: 'basket-1' },
        });

        expect(html).toContain('data-test="pickup"');
        expect(html).toContain('La teva cistella');
        expect(html).toContain('data-test="basket-link"');
    });

    it('shows no basket link before the cycle is settled', async () => {
        const html = await render(Edit, { order });

        expect(html).not.toContain('data-test="pickup"');
    });
});

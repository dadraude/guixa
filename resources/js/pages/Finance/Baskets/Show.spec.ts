// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, nextTick } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { installTranslations } from '@/lib/i18n';
import { reset } from 'laravel-vue-i18n';
import Show from './Show.vue';

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
        usePage: () => ({ props: { errors: {} } }),
        router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    };
});

const basket = {
    id: 'basket',
    householdId: 'household',
    householdName: 'Household K',
    state: 'draft',
    stockLines: [],
    returnLines: [
        {
            returnItemId: 'jar',
            name: 'Glass jar',
            amountCents: 50,
            quantity: 1,
            creditCents: 50,
        },
    ],
    totalCents: -50,
    effectiveTotalCents: -50,
    adjustments: [],
    collectionEvents: [],
    pendingCollection: true,
    weeklyLines: [],
    kind: 'stock',
    referenceCycleId: null,
    late: false,
    balanceCents: 0,
    projectedBalanceCents: 50,
    validatedAt: null,
    collectedAt: null,
};

describe('Finance basket detail', () => {
    it('titles a late weekly basket apart from the base weekly basket (R19)', async () => {
        const html = async (late: boolean) => {
            const app = createSSRApp(Show, {
                basket: { ...basket, kind: 'weekly', late },
                stockProducts: [],
                returnItems: [],
            });
            installTranslations(app);

            return renderToString(app);
        };

        expect(await html(false)).toContain('Cistella setmanal');
        expect(await html(false)).not.toContain('Cistella tardana');
        expect(await html(true)).toContain('Cistella tardana');
    });

    it('shows a never-collected validated basket as pending without history or correction form', async () => {
        const app = createSSRApp(Show, {
            basket: {
                ...basket,
                state: 'validated',
                collectionEvents: [],
                collectedAt: null,
            },
            stockProducts: [],
            returnItems: [],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).toContain('data-test="original-collection"');
        expect(html).toContain('Encara no s’ha recollit');
        expect(html).toContain('data-test="effective-collection"');
        expect(html).toContain('Pendent de recollida');
        expect(html).not.toContain('data-test="collection-history"');
        expect(html).not.toContain('data-test="collection-correction-form"');
        expect(html).toContain('data-test="collect-basket"');
    });

    it('posts a collection correction with a stable identity and resets on success (R23b)', async () => {
        const post = vi.spyOn(router, 'post');
        post.mockClear();
        const wrapper = mount(Show, {
            props: {
                basket: {
                    ...basket,
                    state: 'collected',
                    collectionEvents: [
                        {
                            id: 'event-1',
                            type: 'collected',
                            recordedBy: 'manager',
                            occurredAt: '2026-10-30T10:00:00+00:00',
                            reason: null,
                        },
                    ],
                },
                stockProducts: [],
                returnItems: [],
            },
            global: { plugins: [{ install: installTranslations }] },
        });
        try {
            const form = wrapper.get(
                '[data-test="collection-correction-form"]',
            );
            const reason =
                wrapper.get<HTMLTextAreaElement>('#collection-reason');
            await reason.setValue('  Wrong mark  ');
            await form.trigger('submit');
            expect(post).toHaveBeenCalledTimes(1);
            expect(post.mock.calls[0][0]).toBe(
                '/finance/baskets/basket/collection-corrections',
            );
            const payload = post.mock.calls[0][1] as {
                reason: string;
                correction_id: string;
            };
            expect(payload.reason).toBe('Wrong mark');
            expect(payload.correction_id).toMatch(/^[0-9a-f-]{36}$/);
            await form.trigger('submit');
            expect(
                (post.mock.calls[1][1] as { correction_id: string })
                    .correction_id,
            ).toBe(payload.correction_id);
            post.mock.calls[1][2]?.onSuccess?.({} as never);
            await nextTick();
            expect(reason.element.value).toBe('');
        } finally {
            wrapper.unmount();
            reset();
            post.mockRestore();
        }
    });

    it('submits a signed amount, trimmed reason and stable identity from the real correction form, then resets only on success (R23, R32)', async () => {
        const post = vi.spyOn(router, 'post');
        post.mockClear();
        const wrapper = mount(Show, {
            props: {
                basket: {
                    ...basket,
                    state: 'validated',
                    adjustments: [],
                    effectiveTotalCents: 1200,
                },
                stockProducts: [],
                returnItems: [],
            },
            global: { plugins: [{ install: installTranslations }] },
        });
        try {
            const form = wrapper.get('[data-test="basket-correction-form"]');
            const amount = wrapper.get<HTMLInputElement>('#correction-amount');
            const reason =
                wrapper.get<HTMLTextAreaElement>('#correction-reason');
            const button = form.get('button[type="submit"]');
            expect(button.attributes('disabled')).toBeDefined();

            await amount.setValue('0');
            await reason.setValue('Valid reason');
            await form.trigger('submit');
            await amount.setValue('1.5');
            await form.trigger('submit');
            await amount.setValue('-20');
            await reason.setValue('   ');
            await form.trigger('submit');
            expect(post).not.toHaveBeenCalled();

            await reason.setValue('  Corrected jar credit  ');
            await form.trigger('submit');
            expect(post).toHaveBeenCalledTimes(1);
            const first = post.mock.calls[0];
            expect(first[0]).toBe('/finance/baskets/basket/corrections');
            expect(first[1]).toMatchObject({
                amount_cents: -20,
                reason: 'Corrected jar credit',
            });
            const identity = (first[1] as { correction_id: string })
                .correction_id;
            expect(identity).toMatch(/^[0-9a-f-]{36}$/);

            first[2]?.onStart?.({} as never);
            await nextTick();
            expect(button.attributes('disabled')).toBeDefined();
            first[2]?.onFinish?.({} as never);
            await nextTick();
            await form.trigger('submit');
            expect(post).toHaveBeenCalledTimes(2);
            expect(
                (post.mock.calls[1][1] as { correction_id: string })
                    .correction_id,
            ).toBe(identity);

            post.mock.calls[1][2]?.onSuccess?.({} as never);
            await nextTick();
            expect(amount.element.value).toBe('');
            expect(reason.element.value).toBe('');
            expect(button.attributes('disabled')).toBeDefined();

            await amount.setValue('25');
            await reason.setValue('Second correction');
            await form.trigger('submit');
            expect(post).toHaveBeenCalledTimes(3);
            expect(post.mock.calls[2][0]).toBe(
                '/finance/baskets/basket/corrections',
            );
            expect(post.mock.calls[2][1]).toMatchObject({
                amount_cents: 25,
                reason: 'Second correction',
            });
            expect(
                (post.mock.calls[2][1] as { correction_id: string })
                    .correction_id,
            ).not.toBe(identity);
        } finally {
            wrapper.unmount();
            reset();
            post.mockRestore();
        }
    });

    it('renders original and effective totals, correction history and the staff form for a validated basket (R23, R32)', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                basket: {
                    ...basket,
                    state: 'validated',
                    totalCents: 1200,
                    effectiveTotalCents: 1000,
                    adjustments: [
                        {
                            id: 'correction-1',
                            amountCents: 200,
                            reason: 'Corrected price',
                            recordedBy: 'manager',
                            occurredAt: '2026-10-30T11:00:00+00:00',
                            lines: [],
                        },
                    ],
                },
                stockProducts: [],
                returnItems: [],
            }),
        );
        expect(html).toContain('data-test="original-basket-total"');
        expect(html).toContain('data-test="effective-basket-total"');
        expect(html).toContain('Corrected price');
        expect(html).toContain('data-test="basket-correction-form"');
    });

    it('renders a return-only credit and enables validation for real return contents (S88)', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                basket,
                stockProducts: [],
                returnItems: [
                    {
                        id: 'jar',
                        name: 'Glass jar',
                        amount: '0.50',
                        amountCents: 50,
                        active: true,
                    },
                ],
            }),
        );

        expect(html).toContain('data-test="return-lines"');
        expect(html).toContain('Glass jar');
        expect(html).toContain('-0,50');
        expect(html).toContain('−0,50');
        const validateButton = html.match(
            /<button[^>]*data-test="validate-basket"[^>]*>/,
        )?.[0];
        expect(validateButton).toBeTruthy();
        expect(validateButton).not.toMatch(/\sdisabled(?:\s|>)/);
    });

    it('keeps validation disabled for an empty draft while leaving cancellation available (S88)', async () => {
        const html = await renderToString(
            createSSRApp(Show, {
                basket: {
                    ...basket,
                    returnLines: [],
                    totalCents: 0,
                    projectedBalanceCents: 0,
                },
                stockProducts: [],
                returnItems: [],
            }),
        );

        const validateButton = html.match(
            /<button[^>]*data-test="validate-basket"[^>]*>/,
        )?.[0];
        expect(validateButton).toMatch(/\sdisabled(?:\s|>)/);
        expect(html).toContain('Cancel draft');
    });

    it('shows a fully reversed validated basket as nothing to collect, without a collect action (R24a)', async () => {
        const app = createSSRApp(Show, {
            basket: {
                ...basket,
                state: 'validated',
                pendingCollection: false,
                effectiveTotalCents: 0,
                stockLines: [
                    {
                        stockProductId: 'oil',
                        name: 'Olive oil',
                        unit: 'bottle',
                        quantity: '3.000',
                        unitPriceCents: 1200,
                        amountCents: 3600,
                        effectiveQuantity: '0.000',
                        effectiveAmountCents: 0,
                    },
                ],
            },
            stockProducts: [],
            returnItems: [],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).toContain('data-test="basket-status-reversed"');
        expect(html).toContain('data-test="goods-reversed"');
        expect(html).toContain('data-test="effective-quantity"');
        expect(html).not.toContain('data-test="collect-basket"');
        expect(html).not.toContain('data-test="returned-goods"');
    });

    it('posts the returned goods with the correction identity (S86)', async () => {
        const post = vi.spyOn(router, 'post');
        const wrapper = mount(Show, {
            props: {
                basket: {
                    ...basket,
                    state: 'validated',
                    stockLines: [
                        {
                            stockProductId: 'oil',
                            name: 'Olive oil',
                            unit: 'bottle',
                            quantity: '3.000',
                            unitPriceCents: 1200,
                            amountCents: 3600,
                            effectiveQuantity: '3.000',
                            effectiveAmountCents: 3600,
                        },
                    ],
                },
                stockProducts: [],
                returnItems: [],
            },
            global: { plugins: [{ install: installTranslations }] },
        });
        await wrapper.get('#correction-amount').setValue('1200');
        await wrapper.get('#correction-reason').setValue('Came back');
        await wrapper.get('[data-test="returned-oil"]').setValue('1');
        await wrapper
            .get('[data-test="basket-correction-form"]')
            .trigger('submit');

        expect(post).toHaveBeenLastCalledWith(
            expect.stringContaining('/corrections'),
            expect.objectContaining({
                amount_cents: 1200,
                reason: 'Came back',
                returned_goods: [{ stock_product_id: 'oil', quantity: '1' }],
            }),
            expect.anything(),
        );
    });

    it('keeps a basket with returned empties pending when its stock is fully reversed (R24a, S88)', async () => {
        const app = createSSRApp(Show, {
            basket: {
                ...basket,
                state: 'validated',
                pendingCollection: true,
                stockLines: [
                    {
                        stockProductId: 'oil',
                        name: 'Olive oil',
                        unit: 'bottle',
                        quantity: '1.000',
                        unitPriceCents: 1200,
                        amountCents: 1200,
                        effectiveQuantity: '0.000',
                        effectiveAmountCents: 0,
                    },
                ],
            },
            stockProducts: [],
            returnItems: [],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).toContain('data-test="basket-status-validated"');
        expect(html).toContain('data-test="collect-basket"');
        expect(html).not.toContain('data-test="goods-reversed"');
    });
});

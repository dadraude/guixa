// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { installTranslations } from '@/lib/i18n';
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
    it('keeps cycle cancellation separate from producer cutoff management', async () => {
        HTMLDialogElement.prototype.showModal = function () {
            this.open = true;
        };
        const wrapper = mount(Index, {
            props: {
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
                                cutoff: '2026-11-06T14:00:00',
                                open: true,
                            },
                        ],
                    },
                ],
                producers: [{ id: 'baker', name: 'Forn' }],
                pickupWeekday: 6,
                pendingCollections: {},
            },
            global: { plugins: [{ install: installTranslations }] },
        });
        try {
            const cycleTrigger = wrapper.get('[data-test="manage-cycle"]');
            const producerTrigger = wrapper.get(
                '[data-test="manage-cycle-baker"]',
            );
            expect(cycleTrigger.text()).toBe('');
            expect(cycleTrigger.attributes('aria-label')).toBe(
                'Gestiona el cicle del 07/11/2026',
            );
            expect(producerTrigger.text()).toBe('');
            expect(producerTrigger.attributes('aria-label')).toBe(
                'Gestiona Forn · 07/11/2026',
            );
            await cycleTrigger.trigger('click');
            expect(wrapper.find('#cutoff-date').exists()).toBe(false);
            expect(wrapper.text()).not.toContain('Tanca ara');
            expect(wrapper.get('[data-test="cancel-cycle"]').text()).toBe(
                'Cancel·la el cicle',
            );
            await wrapper.get('[data-test="cancel-cycle"]').trigger('click');
            expect(wrapper.text()).toContain('Aquesta acció no es pot desfer.');
            expect(
                wrapper.find('[data-test="confirm-cancel-cycle"]').exists(),
            ).toBe(true);
            await producerTrigger.trigger('click');
            expect(
                wrapper.get<HTMLInputElement>('#cutoff-date').element.value,
            ).toBe('2026-11-06');
            expect(
                wrapper.get<HTMLInputElement>('#cutoff-time').element.value,
            ).toBe('14:00');
            expect(wrapper.text()).toContain('Tanca ara');
            expect(wrapper.find('[data-test="cancel-cycle"]').exists()).toBe(
                false,
            );
        } finally {
            wrapper.unmount();
            vi.restoreAllMocks();
        }
    });

    it('keeps closed history visible without cycle editing actions', async () => {
        const app = createSSRApp(Index, {
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
            pendingCollections: { cycle: 2 },
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).toContain('Recollida del 07/11/2026');
        expect(html).toContain('Tancat');
        expect(html).toContain('data-test="summary-cycle"');
        expect(html).not.toContain('data-test="manage-cycle"');
        expect(html).not.toContain('data-test="add-cycle-grower"');
        expect(html).toContain('<dialog');
        expect(html).toMatch(
            /data-test="pending-collections"[^>]*>Recollides pendents: 2</,
        );
    });

    it('offers per-producer management and a real add-producer form for open cycles', async () => {
        const app = createSSRApp(Index, {
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
            pendingCollections: {},
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).toContain('data-test="manage-cycle-baker"');
        expect(html).toContain('data-test="add-cycle-grower"');
        expect(html).toContain('sense tancament programat');
    });
});

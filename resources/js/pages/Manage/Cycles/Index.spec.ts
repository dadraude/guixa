// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
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

describe('editable cycle cutoffs', () => {
    it('renders separate date and time controls with one submitted cutoff field', async () => {
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
                                producerId: 'grower',
                                producerName: 'Grower',
                                participating: true,
                                cutoff: null,
                                open: true,
                            },
                        ],
                    },
                ],
                producers: [{ id: 'grower', name: 'Grower' }],
                pickupWeekday: 6,
            }),
        );
        expect(html).toContain('data-test="cutoff-date-cycle-grower"');
        expect(html).toContain('data-test="cutoff-time-cycle-grower"');
        expect(html).toContain('type="hidden" name="cutoff"');
    });

    it('submits one unchanged cutoff value to the existing update destination', async () => {
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
                                producerId: 'grower',
                                producerName: 'Grower',
                                participating: true,
                                cutoff: null,
                                open: true,
                            },
                        ],
                    },
                ],
                producers: [{ id: 'grower', name: 'Grower' }],
                pickupWeekday: 6,
            },
        });
        const form = wrapper
            .get('[data-test="set-cutoff-cycle-grower"]')
            .element.closest('form') as HTMLFormElement;
        expect(form.action).toContain(
            '/manage/cycles/cycle/producers/grower/cutoff',
        );
        expect(new FormData(form).get('cutoff')).toBe('');
        expect(
            (
                wrapper.get('[data-test="cutoff-date-cycle-grower"]')
                    .element as HTMLInputElement
            ).required,
        ).toBe(true);
        expect(
            (
                wrapper.get('[data-test="cutoff-time-cycle-grower"]')
                    .element as HTMLInputElement
            ).required,
        ).toBe(true);

        await wrapper
            .get('[data-test="cutoff-date-cycle-grower"]')
            .setValue('2026-11-06');
        await wrapper
            .get('[data-test="cutoff-time-cycle-grower"]')
            .setValue('20:00');
        await nextTick();

        const cutoffFields = [...new FormData(form).entries()].filter(
            ([name]) => name === 'cutoff',
        );
        expect(cutoffFields).toEqual([['cutoff', '2026-11-06T20:00']]);
        wrapper.unmount();
    });
});

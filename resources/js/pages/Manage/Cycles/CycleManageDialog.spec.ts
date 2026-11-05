// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { installTranslations } from '@/lib/i18n';
import CycleManageDialog from './CycleManageDialog.vue';

vi.mock('@inertiajs/vue3', () => ({
    Form: defineComponent({
        props: { action: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h(
                    'form',
                    { 'data-action': props.action },
                    slots.default?.({ errors: {}, processing: false }),
                ),
    }),
}));

const participant = (canSendManually: boolean) => ({
    producerId: 'baker',
    producerName: 'Forn',
    participating: true,
    cutoff: null,
    open: false,
    sent: false,
    canSendManually,
});

function mountDialog(
    canSendManually: boolean,
    cycleOverrides: Record<string, unknown> = {},
) {
    HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    const cycle = {
        id: 'cycle',
        pickupDate: '2026-11-07',
        cancelled: false,
        closed: false,
        settled: false,
        participants: [participant(canSendManually)],
        ...cycleOverrides,
    };

    return mount(CycleManageDialog, {
        props: {
            open: true,
            cycle,
            participant: participant(canSendManually),
            returnFocusTo: null,
        },
        attachTo: document.body,
        global: { plugins: [{ install: installTranslations }] },
    });
}

describe('send producer order (R12a)', () => {
    it('offers no send action while the order cannot be sent', () => {
        const wrapper = mountDialog(false);

        expect(wrapper.find('[data-test="send-order-button"]').exists()).toBe(
            false,
        );
        wrapper.unmount();
    });

    it('offers no send action in a cancelled cycle', () => {
        const wrapper = mountDialog(true, { cancelled: true });

        expect(wrapper.find('[data-test="send-order"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('asks for confirmation before it can post the send', async () => {
        const wrapper = mountDialog(true);
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        await flushPromises();

        const button = wrapper.get('[data-test="send-order-button"]');
        expect(button.text()).toBe('Envia la comanda');
        expect(wrapper.find('[data-test="confirm-send-order"]').exists()).toBe(
            false,
        );

        await button.trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('El productor rebrà un correu');
        const confirm = wrapper.get('[data-test="confirm-send-order"]');
        expect(document.activeElement).toBe(confirm.element);
        expect(confirm.text()).toBe('Confirma l’enviament');
        expect(confirm.element.closest('form')?.dataset.action).toContain(
            '/manage/cycles/cycle/producers/baker/send',
        );

        await wrapper.get('[data-test="cancel-send-order"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-test="confirm-send-order"]').exists()).toBe(
            false,
        );
        expect(document.activeElement).toBe(
            wrapper.get('[data-test="send-order-button"]').element,
        );
        wrapper.unmount();
    });
});

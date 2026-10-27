// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import NativeDialog from './NativeDialog.vue';

const showModalMock = vi.fn(function (this: HTMLDialogElement) {
    this.setAttribute('open', '');
});
const closeMock = vi.fn(function (this: HTMLDialogElement) {
    this.removeAttribute('open');
    this.dispatchEvent(new Event('close'));
});

beforeEach(() => {
    showModalMock.mockClear();
    closeMock.mockClear();
    HTMLDialogElement.prototype.showModal = showModalMock;
    HTMLDialogElement.prototype.close = closeMock;
});

describe('native manager dialog', () => {
    it('opens modally and returns focus to its trigger after closing', async () => {
        const trigger = document.createElement('button');
        document.body.append(trigger);
        trigger.focus();
        const wrapper = mount(NativeDialog, {
            attachTo: document.body,
            props: {
                open: false,
                title: 'Gestiona',
                titleId: 'manage-title',
                returnFocusTo: trigger,
            },
        });
        await wrapper.setProps({ open: true });
        await nextTick();
        expect(showModalMock).toHaveBeenCalledOnce();
        expect(wrapper.get('dialog').attributes('aria-labelledby')).toBe(
            'manage-title',
        );

        trigger.blur();
        await wrapper.setProps({ open: false });
        await nextTick();
        expect(closeMock).toHaveBeenCalledOnce();
        expect(document.activeElement).toBe(trigger);
        wrapper.unmount();
        trigger.remove();
    });
});

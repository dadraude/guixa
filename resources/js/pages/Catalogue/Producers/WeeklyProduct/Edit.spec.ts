// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h, nextTick } from 'vue';
import { installTranslations } from '@/lib/i18n';
import Edit from './Edit.vue';

const router = vi.hoisted(() => ({ delete: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    router,
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

const producer = { id: 'producer-1', name: 'La Figuera' };
const product = {
    id: 'product-1',
    producerId: producer.id,
    name: 'Bread',
    unit: 'loaf',
    price: '3.50',
    variable: false,
    containersPerUnit: '2.000',
    active: true,
};

beforeEach(() => {
    router.delete.mockClear();
    HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
        this.setAttribute('open', '');
    };
    HTMLDialogElement.prototype.close = function (this: HTMLDialogElement) {
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
    };
});

function show(props: { used?: boolean; canDelete?: boolean } = {}) {
    return mount(Edit, {
        attachTo: document.body,
        props: { producer, product, used: false, canDelete: true, ...props },
        global: { plugins: [{ install: installTranslations }] },
    });
}

describe('Weekly product editing in the Catàleg area', () => {
    it('prefills every field and saves with the "Desa" submit', () => {
        const wrapper = show();
        const value = (name: string) =>
            wrapper.get<HTMLInputElement>(`input[name="${name}"]`).element
                .value;
        expect(value('name')).toBe('Bread');
        expect(value('unit')).toBe('loaf');
        expect(value('price')).toBe('3.50');
        expect(value('containers_per_unit')).toBe('2.000');
        expect(wrapper.get('h1').text()).toContain('Bread');
        expect(wrapper.get('[data-test="save-product"]').text()).toBe('Desa');
        wrapper.unmount();
    });

    it('offers delete only when allowed and never a classify control', () => {
        const allowed = show();
        expect(
            allowed.find('[data-test="delete-unused-product"]').exists(),
        ).toBe(true);
        expect(
            allowed
                .find('[data-test="change-product-classification"]')
                .exists(),
        ).toBe(false);
        allowed.unmount();

        const denied = show({ canDelete: false });
        expect(
            denied.find('[data-test="delete-unused-product"]').exists(),
        ).toBe(false);
        denied.unmount();
    });

    it('opens the confirmation dialog and sends nothing on Cancel', async () => {
        const wrapper = show();
        await wrapper
            .get('[data-test="delete-unused-product"]')
            .trigger('click');
        await nextTick();
        const dialog = wrapper.get<HTMLDialogElement>('dialog');
        expect(dialog.element.hasAttribute('open')).toBe(true);

        await wrapper
            .get('[data-test="cancel-product-action"]')
            .trigger('click');
        await nextTick();
        expect(dialog.element.hasAttribute('open')).toBe(false);
        expect(router.delete).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('sends the delete request only after Confirm', async () => {
        const wrapper = show();
        await wrapper
            .get('[data-test="delete-unused-product"]')
            .trigger('click');
        await nextTick();
        await wrapper
            .get('[data-test="confirm-product-action"]')
            .trigger('click');
        expect(router.delete).toHaveBeenCalledOnce();
        expect(router.delete.mock.calls[0][0]).toContain('/product-1/unused');
        wrapper.unmount();
    });
});

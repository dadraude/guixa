// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h, nextTick } from 'vue';
import { installTranslations } from '@/lib/i18n';
import { pilotVariantFor } from '@/lib/pilotTheme';
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

const product = {
    id: 'product-1',
    name: 'Pa de pagès',
    unit: 'unit',
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

function show(used = false) {
    return mount(Edit, {
        attachTo: document.body,
        props: { product, used },
        global: { plugins: [{ install: installTranslations }] },
    });
}

describe('producer product editing', () => {
    it('takes the landing identity', () => {
        expect(pilotVariantFor('Producer/Products/Edit')).toBe('landing');
    });

    it('prefills every field and saves with the "Desa" submit', () => {
        const wrapper = show();
        const value = (name: string) =>
            wrapper.get<HTMLInputElement>(`input[name="${name}"]`).element
                .value;
        expect(value('name')).toBe('Pa de pagès');
        expect(value('price')).toBe('3.50');
        expect(value('containers_per_unit')).toBe('2.000');
        expect(wrapper.get('[data-test="save-product"]').text()).toBe('Desa');
        wrapper.unmount();
    });

    it('offers delete-unused only while the product is unused', () => {
        const unused = show();
        expect(
            unused.find('[data-test="delete-unused-product"]').exists(),
        ).toBe(true);
        unused.unmount();

        const used = show(true);
        expect(used.find('[data-test="delete-unused-product"]').exists()).toBe(
            false,
        );
        used.unmount();
    });

    it('opens the dialog and sends no request on Cancel', async () => {
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

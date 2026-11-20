// @vitest-environment jsdom
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { installTranslations } from '@/lib/i18n';
import StockEditDialog from './StockEditDialog.vue';

const router = vi.hoisted(() => ({
    put: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router,
    Link: { template: '<a><slot /></a>' },
}));

const product = {
    id: 'stock-1',
    producerId: 'producer-1',
    name: 'Mel',
    unit: 'pot',
    price: '4.50',
    available: '12',
    active: true,
} as App.Http.StockLevelRow;

function mountDialog(overrides: Partial<App.Http.StockLevelRow> = {}) {
    HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function () {
        this.open = false;
    };
    return mount(StockEditDialog, {
        props: {
            open: true,
            product: { ...product, ...overrides },
            returnFocusTo: null,
        },
        attachTo: document.body,
        global: { plugins: [{ install: installTranslations }] },
    });
}

const setQuantity = (wrapper: ReturnType<typeof mountDialog>, value: string) =>
    wrapper.get('[data-test="stock-available"]').setValue(value);

// R21/R22/R26/S22 (T28): the edit dialog states the product's status as text, rejects a bad count before any request,
// and keeps the destructive status change behind a confirmation drawn as the danger action. Escape and focus return are
// the NativeDialog's (NativeDialog.spec.ts) and the manifest's.
// The first form in the dialog is the title bar's close form; the count form is the one holding the input.
const submit = (wrapper: ReturnType<typeof mountDialog>) =>
    wrapper
        .get('[data-test="stock-available"]')
        .element.closest('form')!
        .dispatchEvent(new Event('submit', { cancelable: true }));

describe('stock edit dialog', () => {
    beforeEach(() => {
        router.put.mockReset();
        router.delete.mockReset();
    });
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('reads the unit, price and status as text and offers the count prefilled', async () => {
        const wrapper = mountDialog({ active: false });
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });

        expect(wrapper.text()).toContain('pot · 4,50');
        expect(wrapper.text()).toContain('Inactiu');
        expect(
            (
                wrapper.get('[data-test="stock-available"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('12');
    });

    it('shows a text error and sends nothing for a count with too many digits', async () => {
        const wrapper = mountDialog();
        await setQuantity(wrapper, '12345678');
        await submit(wrapper);

        expect(wrapper.text()).toContain(
            'Introdueix una quantitat vàlida amb un màxim de tres decimals.',
        );
        expect(router.put).not.toHaveBeenCalled();
    });

    it('sends the new count and disables saving while the request runs', async () => {
        const wrapper = mountDialog();
        await setQuantity(wrapper, '15');
        await submit(wrapper);

        const [url, data, options] = router.put.mock.calls[0];
        expect(url).toContain('stock-1');
        expect(data).toEqual({ available: '15' });
        options.onStart();
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('[data-test="save-available"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('asks for confirmation, drawn as the danger action, before deactivating', async () => {
        const wrapper = mountDialog();
        expect(
            wrapper.find('[data-test="confirm-status-stock-1"]').exists(),
        ).toBe(false);

        await wrapper.findAll('details button').at(0)!.trigger('click');
        const confirm = wrapper.get('[data-test="confirm-status-stock-1"]');
        expect(confirm.classes()).toContain('manager-button-danger');
        expect(confirm.classes()).not.toContain('manager-button-secondary');
        expect(router.delete).not.toHaveBeenCalled();

        await confirm.trigger('click');
        expect(router.delete).toHaveBeenCalledOnce();
        router.delete.mock.calls[0][1].onStart();
        await wrapper.vm.$nextTick();
        expect(confirm.attributes('disabled')).toBeDefined();
    });
});

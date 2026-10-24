// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { nextTick } from 'vue';
import ReceivedTotalForm from './ReceivedTotalForm.vue';

const allocation = (id: string, name: string, byHand: boolean) => ({
    householdId: id,
    householdName: name,
    ordered: '4.000',
    allocated: '3.000',
    byHand,
});

const product = (hasManualAllocations: boolean) => ({
    productId: 'bread',
    name: 'Bread',
    unit: 'loaf',
    variable: false,
    nobodyOrdered: false,
    ordered: '20.000',
    received: '15.000',
    allocated: '14.000',
    difference: '1.000',
    balanced: false,
    hasManualAllocations,
    allocations: [
        allocation('a', 'Household A', false),
        allocation('c', 'Household C', hasManualAllocations),
    ],
});

const mounted = [] as ReturnType<typeof mount>[];

function form(hasManualAllocations: boolean) {
    const wrapper = mount(ReceivedTotalForm, {
        props: { product: product(hasManualAllocations), processing: false },
        attachTo: document.body,
    });
    mounted.push(wrapper);

    return wrapper;
}

const type = async (wrapper: ReturnType<typeof form>, value: string) => {
    await wrapper.find('input').setValue(value);
    await wrapper.find('form').trigger('submit');
    await nextTick();
};

const dialog = () => document.body.querySelector('[role="dialog"]');
const button = (label: string) =>
    [...document.body.querySelectorAll('button')].find((b) =>
        b.textContent?.includes(label),
    ) as HTMLButtonElement;

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.innerHTML = '';
});

describe('received total form', () => {
    it('saves at once when no allocation was set by hand (R15)', async () => {
        const wrapper = form(false);

        await type(wrapper, '14');

        expect(wrapper.emitted('save')).toEqual([['14', false]]);
        expect(dialog()).toBeNull();
    });

    it('warns instead of saving when an allocation was set by hand, naming the households (R15, S31)', async () => {
        const wrapper = form(true);

        await type(wrapper, '14');

        expect(wrapper.emitted('save')).toBeUndefined();
        expect(dialog()?.textContent).toContain('Household C');
        expect(dialog()?.textContent).not.toContain('Household A');
        expect(dialog()?.textContent).toContain('will be lost');
    });

    it('changes nothing on cancel and shows the stored total again (S31)', async () => {
        const wrapper = form(true);
        await type(wrapper, '14');

        button('Cancel').click();
        await nextTick();
        await nextTick();

        expect(wrapper.emitted('save')).toBeUndefined();
        expect((wrapper.find('input').element as HTMLInputElement).value).toBe(
            '15.000',
        );
    });

    it('saves the total with the overwrite flag once confirmed (S31)', async () => {
        const wrapper = form(true);
        await type(wrapper, '14');

        button('Replace them').click();
        await nextTick();

        expect(wrapper.emitted('save')).toEqual([['14', true]]);
    });
});

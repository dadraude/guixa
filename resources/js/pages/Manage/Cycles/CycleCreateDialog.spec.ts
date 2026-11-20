// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { installTranslations } from '@/lib/i18n';
import CycleCreateDialog from './CycleCreateDialog.vue';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
}));

vi.mock('@inertiajs/vue3', () => ({
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.(form)),
    }),
}));

const producers = [
    { id: 'baker', name: 'Forn' },
    { id: 'dairy', name: 'Formatges' },
];

function mountDialog(
    props: { producers?: typeof producers; pickupWeekday?: number | null } = {},
) {
    HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    return mount(CycleCreateDialog, {
        props: {
            open: true,
            returnFocusTo: null,
            producers,
            pickupWeekday: 4,
            ...props,
        },
        attachTo: document.body,
        global: { plugins: [{ install: installTranslations }] },
    });
}

// R21/R22/R26/S22 (T27): the create dialog reads as text, offers the weekday only while the cooperative has none, and
// keeps the submit action disabled while the request runs. Escape and focus return are the NativeDialog's (NativeDialog.spec.ts) and the manifest's.
describe('cycle create dialog', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        form.errors = {};
        form.processing = false;
    });

    it('lists every producer as a labelled checkbox and no weekday once the weekday is set', () => {
        const wrapper = mountDialog();

        const labels = wrapper
            .findAll('input[name="producer_ids[]"]')
            .map((box) => box.element.closest('label')?.textContent?.trim());
        expect(labels).toEqual(['Forn', 'Formatges']);
        expect(wrapper.find('#pickup-date').exists()).toBe(true);
        expect(wrapper.find('#cycle-weekday').exists()).toBe(false);
        expect(wrapper.find('input[name="permanent"]').exists()).toBe(false);
    });

    it('asks for the weekday and whether it is permanent while the cooperative has none', () => {
        const wrapper = mountDialog({ pickupWeekday: null });

        expect(wrapper.find('#cycle-weekday').exists()).toBe(true);
        expect(wrapper.findAll('input[name="permanent"]')).toHaveLength(2);
    });

    it('says in text that there are no active producers', () => {
        const wrapper = mountDialog({ producers: [] });

        expect(wrapper.text()).toContain('No hi ha productors actius.');
        expect(wrapper.find('input[name="producer_ids[]"]').exists()).toBe(
            false,
        );
    });

    it('disables the submit action only while the request runs', () => {
        const idle = mountDialog();
        const idleSubmit = idle.get('[data-test="open-cycle"]');
        expect(idleSubmit.attributes('disabled')).toBeUndefined();
        idle.unmount();

        form.processing = true;
        const busy = mountDialog();
        expect(
            busy.get('[data-test="open-cycle"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('shows a refusal and a field error as text', () => {
        form.errors = {
            domain: 'No s’ha pogut obrir el cicle.',
            pickup_date: 'El camp data és obligatori.',
        };
        const wrapper = mountDialog();

        expect(wrapper.text()).toContain('No s’ha pogut obrir el cicle.');
        expect(wrapper.text()).toContain('El camp data és obligatori.');
    });
});

// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import Settings from './Settings.vue';

const formState = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: {
        setup:
            (_: unknown, { slots }: { slots: Record<string, Function> }) =>
            () =>
                slots.default?.(formState),
    },
}));
vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string) => key,
}));

// R22/R26/S21 (T26): the pickup weekday form shows the saved weekday or says it is not set, names the effect of a change,
// and shows an invalid or server-refused value as text next to the field.
describe('manager settings page', () => {
    let wrapper: ReturnType<typeof mountPage> | null = null;
    const mountPage = (pickupWeekday: number | null) =>
        mount(Settings, {
            props: { pickupWeekday } as never,
            attachTo: document.body,
        });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        document.body.innerHTML = '';
        formState.errors = {};
        formState.processing = false;
    });

    it('shows the saved weekday and no "Not set" notice', () => {
        wrapper = mountPage(4);
        expect(wrapper.get('h1').text()).toBe('Settings');
        expect(wrapper.get('#pickup-weekday').text()).toContain('Thursday');
        expect(wrapper.text()).not.toContain(
            'Not set: opening a cycle will ask for it.',
        );
        expect(wrapper.text()).toContain(
            'Changing it affects only cycles opened afterwards',
        );
    });

    it('says the weekday is not set and that opening a cycle will ask for it', () => {
        wrapper = mountPage(null);
        expect(wrapper.get('#pickup-weekday').text()).toContain('Not set');
        expect(wrapper.text()).toContain(
            'Not set: opening a cycle will ask for it.',
        );
    });

    it.each([
        [{ pickup_weekday: 'Pick a weekday.' }, 'Pick a weekday.'],
        [
            { domain: 'Pickup weekday cannot be changed.' },
            'Pickup weekday cannot be changed.',
        ],
    ])('shows the error %j as text', (errors, message) => {
        formState.errors = errors;
        wrapper = mountPage(2);
        expect(wrapper.get('p.text-destructive').text()).toBe(message);
    });

    it('disables the save button while the request is processing', () => {
        formState.processing = true;
        wrapper = mountPage(2);
        expect(
            wrapper
                .get('[data-test="save-pickup-weekday"]')
                .attributes('disabled'),
        ).toBeDefined();
    });
});

// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import { createSSRApp, defineComponent, nextTick } from 'vue';
import { renderToString } from 'vue/server-renderer';
import FormSelect from './FormSelect.vue';

const FormWithSelect = defineComponent({
    components: { FormSelect },
    setup: () => ({
        options: [
            { value: 'north', label: 'North household' },
            { value: 'south', label: 'South household' },
        ],
    }),
    template: `
        <form>
            <label for="household">Household</label>
            <FormSelect
                id="household"
                name="household_id"
                required
                :options="options"
                default-value="south"
            />
        </form>
    `,
});

describe('FormSelect', () => {
    it('includes the initial selected label in server-rendered HTML', async () => {
        const html = await renderToString(
            createSSRApp(FormSelect, {
                options: [
                    { value: 'north', label: 'North household' },
                    { value: 'south', label: 'South household' },
                ],
                defaultValue: 'south',
            }),
        );

        expect(html).toContain('South household');
    });

    it('shows the selected label and includes its default value in FormData', async () => {
        const wrapper = mount(FormWithSelect, { attachTo: document.body });
        await nextTick();

        const trigger = wrapper.get('[role="combobox"]');
        const form = trigger.element.closest('form') as HTMLFormElement;
        expect(trigger.attributes('id')).toBe('household');
        expect(trigger.text()).toContain('South household');
        expect(trigger.classes()).toContain('min-h-11');
        expect(new FormData(form).get('household_id')).toBe('south');

        wrapper.unmount();
    });
});

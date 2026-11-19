// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h, nextTick, ref } from 'vue';
import { providePilotVariant } from '@/composables/usePilotTheme';
import type { PilotVariant } from '@/lib/pilotTheme';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '.';

const popupClassWith = async (variant: PilotVariant | null) => {
    const Page = defineComponent({
        setup() {
            providePilotVariant(ref(variant));
            return () =>
                h(Select, { defaultOpen: true, defaultValue: 'a' }, () => [
                    h(SelectTrigger, null, () => h(SelectValue)),
                    h(SelectContent, null, () =>
                        h(SelectItem, { value: 'a' }, () => 'Option A'),
                    ),
                ]);
        },
    });
    const wrapper = mount(Page, { attachTo: document.body });
    await nextTick();
    await nextTick();
    const popup = document.body.querySelector('[data-slot="select-content"]');
    wrapper.unmount();
    return popup?.className ?? null;
};

describe('SelectContent variant class (R24, S20)', () => {
    it('puts the variant class on the popup root when a variant is provided', async () => {
        expect(await popupClassWith('landing')).toMatch(/\blanding\b/);
    });

    it('adds no variant class when none is provided', async () => {
        const popup = await popupClassWith(null);
        expect(popup).not.toBeNull();
        expect(popup).not.toMatch(/\blanding\b/);
    });
});

// @vitest-environment jsdom
import { describe, expect, it } from 'vite-plus/test';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { installTranslations } from '@/lib/i18n';
import BasketCollectionHistory from './BasketCollectionHistory.vue';

const events = [
    {
        id: 'first',
        type: 'collected',
        recordedBy: 'manager',
        occurredAt: '2026-11-01T10:00:00+00:00',
        reason: null,
    },
    {
        id: 'correction',
        type: 'corrected',
        recordedBy: 'finance',
        occurredAt: '2026-11-01T11:00:00+00:00',
        reason: 'Marked by mistake',
    },
];

describe('basket collection history', () => {
    it('shows original and effective pending states without a correction action after correction', () => {
        const wrapper = mount(BasketCollectionHistory, {
            props: { state: 'validated', events, editable: true },
            global: { plugins: [{ install: installTranslations }] },
        });
        expect(
            wrapper.get('[data-test="original-collection"]').text(),
        ).toContain('manager');
        expect(
            wrapper.get('[data-test="effective-collection"]').text(),
        ).toContain('Pendent');
        expect(
            wrapper.findAll('[data-test="collection-history"] li'),
        ).toHaveLength(2);
        expect(
            wrapper.find('[data-test="collection-correction-form"]').exists(),
        ).toBe(false);
    });

    it('submits a trimmed reason and stable identity until success, then resets', async () => {
        const wrapper = mount(BasketCollectionHistory, {
            props: { state: 'collected', events: [events[0]], editable: true },
            global: { plugins: [{ install: installTranslations }] },
        });
        const form = wrapper.get('[data-test="collection-correction-form"]');
        const reason = wrapper.get<HTMLTextAreaElement>('#collection-reason');
        const button = form.get('button');
        expect(button.attributes('disabled')).toBeDefined();
        await reason.setValue('   ');
        await form.trigger('submit');
        expect(wrapper.emitted('submit')).toBeUndefined();
        await reason.setValue('  Marked by mistake  ');
        await form.trigger('submit');
        await form.trigger('submit');
        const submissions = wrapper.emitted('submit') as [string, string][];
        expect(submissions).toHaveLength(2);
        expect(submissions[0][0]).toBe('Marked by mistake');
        expect(submissions[0][1]).toBe(submissions[1][1]);
        expect(submissions[0][1]).toMatch(/^[0-9a-f-]{36}$/);
        (wrapper.vm as unknown as { reset: () => void }).reset();
        await nextTick();
        expect(reason.element.value).toBe('');
        expect(button.attributes('disabled')).toBeDefined();
    });

    it('keeps a household member read-only even when collected', () => {
        const wrapper = mount(BasketCollectionHistory, {
            props: { state: 'collected', events, editable: false },
            global: { plugins: [{ install: installTranslations }] },
        });
        expect(
            wrapper.find('[data-test="collection-correction-form"]').exists(),
        ).toBe(false);
        expect(
            wrapper.findAll('[data-test="collection-history"] li'),
        ).toHaveLength(2);
    });
});

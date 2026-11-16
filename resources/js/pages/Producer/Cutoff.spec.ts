import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import { pilotVariantFor } from '@/lib/pilotTheme';
import Cutoff from './Cutoff.vue';

const form = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ ...form })),
    }),
}));

async function render(state: Partial<typeof form> = {}) {
    form.errors = state.errors ?? {};
    form.processing = state.processing ?? false;
    const app = createSSRApp(Cutoff, {
        cutoff: { daysBefore: 3, time: '20:00' },
    });
    installTranslations(app);
    return renderToString(app);
}

function submit(html: string) {
    return html.match(/<button[^>]*data-test="save-cutoff".*?<\/button>/)![0];
}

function errorAfter(id: string, message: string) {
    return new RegExp(
        `<input[^>]*id="${id}"[^>]*>\\s*<div[^>]*>\\s*<p class="text-sm text-destructive">\\s*${message}\\s*</p>`,
    );
}

describe('producer default cutoff', () => {
    it('takes the landing identity', () => {
        expect(pilotVariantFor('Producer/Cutoff')).toBe('landing');
    });

    it('titles the page and prefills both fields', async () => {
        const html = await render();
        expect(html).toContain('Hora de tancament habitual');
        expect(html).toMatch(/<input[^>]*id="days_before"[^>]*value="3"/);
        expect(html).toMatch(/<input[^>]*id="time"[^>]*value="20:00"/);
        expect(html).toContain('for="days_before"');
        expect(html).toContain('for="time"');
    });

    it('shows the days_before error right after its input', async () => {
        const html = await render({
            errors: { days_before: 'Entre 1 i 6 dies.' },
        });
        expect(html).toMatch(errorAfter('days_before', 'Entre 1 i 6 dies\\.'));
    });

    it('shows the time error right after the time input', async () => {
        const html = await render({ errors: { time: 'Hora no vàlida.' } });
        expect(html).toMatch(errorAfter('time', 'Hora no vàlida\\.'));
    });

    it('falls back to the domain error on the time field', async () => {
        const html = await render({ errors: { domain: 'Regla no vàlida.' } });
        expect(html).toMatch(errorAfter('time', 'Regla no vàlida\\.'));
    });

    it('enables the save submit, and disables it while processing', async () => {
        expect(submit(await render())).not.toMatch(/ disabled[ >]/);
        expect(submit(await render({ processing: true }))).toMatch(
            / disabled[ >]/,
        );
    });
});

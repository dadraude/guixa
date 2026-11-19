import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import { pilotVariantFor } from '@/lib/pilotTheme';
import Movements from './Movements.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    router: { visit: vi.fn() },
}));

const line = (overrides: Partial<App.Http.StockMovementRow> = {}) => ({
    id: 'line-1',
    occurredAt: '2026-10-20 11:00',
    type: 'sale',
    quantity: '-3.000',
    balanceAfter: '6.000',
    note: null,
    authorName: 'Ana Buyer',
    cycleId: 'cycle',
    pickupDate: '2026-10-24',
    basketId: null,
    ...overrides,
});

const history = (
    lines: App.Http.StockMovementRow[],
): App.Http.StockMovementPageRow => ({
    lines,
    total: lines.length,
    page: 1,
    prevUrl: null,
    nextUrl: null,
});

const product = { name: 'Mel de farigola', unit: 'pot', available: '1234.500' };

async function render(rows: App.Http.StockMovementRow[]) {
    const app = createSSRApp(Movements, { product, history: history(rows) });
    installTranslations(app);
    return renderToString(app);
}

describe('Finance stock movement history (005 R21, R26, S19)', () => {
    it('is shown in the landing identity on its exact page name only', () => {
        expect(pilotVariantFor('Finance/Stock/Movements')).toBe('landing');
        expect(pilotVariantFor('Finance/Stock/Movements/Extra')).toBeNull();
    });

    it('titles the page with the product name and shows the available quantity line', async () => {
        const html = await render([line()]);
        expect(html).toMatch(/<h1[^>]*>\s*Mel de farigola\s*<\/h1>/);
        expect(html).toContain('1.234,500 pot disponibles');
    });

    it('renders the shared movement history with its lines', async () => {
        const html = await render([
            line(),
            line({ id: 'line-2', type: 'entry', quantity: '9.000' }),
        ]);
        expect(html).toContain('data-test="movement-history"');
        expect(html.match(/data-test="movement-line"/g)).toHaveLength(2);
    });

    it('is read-only for finance: no entry, adjustment or count control', async () => {
        const html = await render([line()]);
        expect(html).not.toMatch(/<(form|input|textarea|select|button)\b/);
        expect(html).not.toMatch(/data-test="[^"]*(entry|adjust|count)[^"]*"/);
    });
});

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

describe('Catalogue stock movement history (001 R28a, S6)', () => {
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
        expect(html).toContain('id="movement-history"');
        expect(html).toContain('data-test="movement-history"');
        expect(html.match(/data-test="movement-line"/g)).toHaveLength(2);
        expect(html).toContain('class="pilot-layout-page pilot-t44-page"');
    });

    it('keeps the quantity and balance cells on the tabular figures class', async () => {
        const html = await render([line()]);
        expect(html.match(/<td class="pilot-amount py-2">/g)).toHaveLength(2);
    });

    it('says so when there are no movements yet', async () => {
        const html = await render([]);
        expect(html).toContain('data-test="movement-history-empty"');
        expect(html).not.toContain('data-test="movement-line"');
    });

    it('is read-only: no entry, adjustment or count control, no form, no input', async () => {
        const html = await render([line()]);
        expect(html).not.toMatch(/<(form|input|textarea|select)\b/);
        expect(html).not.toMatch(/<button\b/);
        expect(html).not.toContain('name="quantity"');
        expect(html).not.toMatch(/data-test="[^"]*(entry|adjust|count)[^"]*"/);
    });

    it('is shown in the landing identity (005 R1)', () => {
        expect(pilotVariantFor('Catalogue/Stock/Movements')).toBe('landing');
    });
});

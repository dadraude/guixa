import { describe, expect, it } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import StockProductFields from '@/components/StockProductFields.vue';
import { installTranslations } from '@/lib/i18n';

const product = {
    name: 'Honey',
    unit: 'kg',
    price: '6.50',
    variable: true,
    containersPerUnit: '2.000',
};

async function fields(lockStockUnit: boolean) {
    const app = createSSRApp(StockProductFields, {
        product,
        errors: {},
        lockStockUnit,
    });
    installTranslations(app);
    return renderToString(app);
}

describe('stock metadata editing', () => {
    it('submits unchanged protected unit and variable values while allowing name, price and container edits', async () => {
        const html = await fields(true);
        expect(html).toMatch(/name="unit"[^>]*readonly/);
        expect(html).toMatch(/name="variable" value="1"/);
        expect(html).toMatch(/name="variable" value="1"[^>]*disabled/);
        expect(html).toContain('name="name"');
        expect(html).toContain('name="price"');
        expect(html).toContain('name="containers_per_unit"');
        expect(html).toContain(
            'La unitat i la quantitat variable queden fixades',
        );
    });

    it('keeps create and unused edit controls available', async () => {
        const html = await fields(false);
        expect(html).not.toMatch(/name="unit"[^>]*readonly/);
        expect(html).not.toMatch(/name="variable"[^>]*disabled/);
    });
});

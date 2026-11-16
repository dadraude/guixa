import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import { pilotVariantFor } from '@/lib/pilotTheme';
import CycleSummary from './CycleSummary.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.()),
    }),
}));

type Line = {
    productId: string;
    name: string;
    unit: string;
    available: boolean;
    quantity: string | null;
};

const line = (over: Partial<Line> = {}): Line => ({
    productId: 'p-1',
    name: 'Tomàquet de pera',
    unit: 'kg',
    available: true,
    quantity: null,
    ...over,
});

async function render(sent: boolean, products: Line[]) {
    const app = createSSRApp(CycleSummary, {
        summary: {
            cycleId: 'cycle-1',
            pickupDate: '2026-11-14',
            sent,
            products,
        },
    });
    installTranslations(app);
    return renderToString(app);
}

const toggle = (html: string, id: string) =>
    html.match(
        new RegExp(`<button[^>]*data-test="toggle-${id}".*?</button>`),
    )![0];

describe('producer cycle summary', () => {
    it('takes the landing identity', () => {
        expect(pilotVariantFor('Producer/CycleSummary')).toBe('landing');
        expect(pilotVariantFor('Producer/CycleSummary/Extra')).toBeNull();
    });

    it('shows the sent quantities read-only, with no toggle', async () => {
        const html = await render(true, [
            line({ quantity: '12.500' }),
            line({ productId: 'p-2', name: 'Pastanaga', quantity: '3.000' }),
        ]);
        expect(html).toContain('Què has de lliurar, per producte');
        expect(html).toMatch(/data-test="quantity"[^>]*>\s*12,500\s*kg/);
        expect(html).toMatch(/data-test="quantity"[^>]*>\s*3,000\s*kg/);
        expect(html).toContain('pilot-amount');
        expect(html).not.toContain('data-test="availability"');
        expect(html).not.toContain('data-test="toggle-');
        expect(html).not.toContain('type="submit"');
    });

    it('says there is nothing to deliver when a sent cycle has no lines', async () => {
        const html = await render(true, []);
        expect(html).toMatch(
            /data-test="no-products"[^>]*>\s*No hi ha res per lliurar\./,
        );
        expect(html).not.toContain('data-test="quantity"');
    });

    it('says there are no products to offer when a not-sent cycle has none', async () => {
        const html = await render(false, []);
        expect(html).toMatch(
            /data-test="no-products"[^>]*>\s*No tens cap producte per oferir\./,
        );
    });

    it('shows Available as text with the Mark unavailable button', async () => {
        const html = await render(false, [line({ available: true })]);
        expect(html).toMatch(/data-test="availability"[^>]*>\s*Disponible\s*</);
        expect(toggle(html, 'p-1')).toMatch(
            />\s*Marca com a no disponible\s*</,
        );
        expect(html).toContain('name="available" value="0"');
        expect(html).not.toContain('data-test="quantity"');
    });

    it('shows Unavailable as text with the Mark available button', async () => {
        const html = await render(false, [line({ available: false })]);
        expect(html).toMatch(
            /data-test="availability"[^>]*>\s*No disponible\s*</,
        );
        expect(toggle(html, 'p-1')).toMatch(/>\s*Marca com a disponible\s*</);
        expect(html).toContain('name="available" value="1"');
    });
});

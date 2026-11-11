import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Dashboard from './Dashboard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: (_props: unknown, { slots }: { slots: { default?: () => string } }) =>
        slots.default?.() ?? '',
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test User' } },
        },
    }),
}));

vi.mock('@/actions/App/Http/Controllers/OrderController', () => ({
    default: { index: () => '/' },
}));
vi.mock('@/actions/App/Http/Controllers/WalletController', () => ({
    default: { show: () => '/wallet' },
}));
vi.mock('@/actions/App/Http/Controllers/Manage/CycleController', () => ({
    default: { index: () => '/manage/cycles' },
}));
vi.mock('@/actions/App/Http/Controllers/Manage/HouseholdController', () => ({
    default: { index: () => '/manage/households' },
}));
vi.mock('@/actions/App/Http/Controllers/Manage/ProducerController', () => ({
    default: { index: () => '/manage/producers' },
}));
vi.mock('@/actions/App/Http/Controllers/Manage/StockProductController', () => ({
    default: { index: () => '/manage/stock' },
}));
vi.mock('@/actions/App/Http/Controllers/Manage/AccountController', () => ({
    default: { index: () => '/manage/accounts' },
}));
vi.mock(
    '@/actions/App/Http/Controllers/Catalogue/ReturnItemController',
    () => ({
        default: { index: () => '/catalogue/return-items' },
    }),
);
vi.mock('@/actions/App/Http/Controllers/Manage/SettingsController', () => ({
    default: { show: () => '/manage/settings' },
}));
vi.mock('@/actions/App/Http/Controllers/Producer/ProductController', () => ({
    default: { index: () => '/producer/products' },
}));
vi.mock('@/actions/App/Http/Controllers/Producer/CutoffController', () => ({
    default: { show: () => '/producer/cutoff' },
}));

describe('dashboard presentation', () => {
    it('links the Catalogue area from the header without adding a card (R15)', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['catalogue'],
            isManager: false,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            managerSummary: null,
            areaShortcuts: [
                {
                    role: 'catalogue',
                    label: 'Catalogue',
                    href: '/catalogue/producers',
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);

        expect(html).toContain('aria-label="Les teves àrees"');
        expect(html).toContain('Catàleg');
        expect(html).not.toContain('<article');
        expect(html).not.toContain('manager actions');
    });

    it('groups four-digit member and manager counts without changing summary values', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['member', 'manager'],
            isManager: true,
            isMember: true,
            nextPickup: null,
            openCycles: 1234,
            balance: null,
            managerSummary: {
                cycles: 2345,
                activeCycles: 1,
                households: 3456,
                activeHouseholds: 2,
                accounts: 4567,
                pendingInvitations: 0,
                producers: 1,
                activeProducers: 1,
            },
        });
        installTranslations(app);
        const html = await renderToString(app);

        for (const count of ['1.234', '2.345', '3.456', '4.567'])
            expect(html).toContain(count);
        expect(html).not.toContain('>1234<');
    });
    it('renders the translated dashboard title', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['member'],
            isManager: false,
            isMember: true,
            nextPickup: '2026-11-05',
            openCycles: 1,
            balance: 5000,
            managerSummary: null,
        });
        installTranslations(app);
        const html = await renderToString(app);

        expect(html).toContain('Inici');
    });

    it('shows member summary cards for a member', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['member'],
            isManager: false,
            isMember: true,
            nextPickup: '2026-11-05',
            openCycles: 2,
            balance: 5000,
            managerSummary: null,
        });
        installTranslations(app);
        const html = await renderToString(app);

        expect(html).toContain('Saldo');
        expect(html).toContain('recollida');
        expect(html).toContain('text-lg font-semibold');
    });

    it('shows manager summary for a manager', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['manager'],
            isManager: true,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            managerSummary: {
                cycles: 3,
                activeCycles: 1,
                households: 5,
                activeHouseholds: 4,
                accounts: 10,
                pendingInvitations: 2,
                producers: 6,
                activeProducers: 5,
            },
        });
        installTranslations(app);
        const html = await renderToString(app);

        expect(html).toContain('Cooperativa');
        expect(html).toContain('Cicles');
        expect(html).toContain('Comptes');
        expect(html.match(/text-lg font-semibold/g)).toHaveLength(2);
    });

    it('lists collection groups with context, visible counts and server links, and claims nothing when there are none (R2, R4, R14, S4)', async () => {
        const base = {
            roles: ['member'],
            isManager: false,
            isMember: true,
            nextPickup: null,
            openCycles: 0,
            balance: 5000,
            managerSummary: null,
        };
        const populated = createSSRApp(Dashboard, {
            ...base,
            attention: [
                {
                    key: 'collect_basket:cycle:c1',
                    action: 'collect_basket',
                    cycleId: 'c1',
                    scheduledDate: '2026-10-28',
                    count: 2,
                    href: '/finance/baskets?pending=1',
                },
                {
                    key: 'collect_basket:no-cycle',
                    action: 'collect_basket',
                    cycleId: null,
                    scheduledDate: null,
                    count: 1,
                    href: '/baskets/stock',
                },
            ],
        });
        installTranslations(populated);
        const html = await renderToString(populated);
        expect(html.match(/data-test="attention-group"/g)).toHaveLength(2);
        expect(html).toContain('Feina pendent');
        expect(html).toContain('Recollir cistelles');
        expect(html).toContain('2 pendents');
        expect(html).toContain('1 pendent<');
        expect(html).not.toContain('1 pendents');
        expect(html).toContain('Cap cicle');
        expect(html.match(/Veure cistelles/g)).toHaveLength(2);

        for (const attention of [[], undefined]) {
            const empty = createSSRApp(Dashboard, { ...base, attention });
            installTranslations(empty);
            const emptyHtml = await renderToString(empty);
            expect(emptyHtml).not.toContain('attention-list');
            expect(emptyHtml).not.toContain('Feina pendent');
            expect(emptyHtml).not.toContain('<ul class="pilot-list"');
        }
    });
});

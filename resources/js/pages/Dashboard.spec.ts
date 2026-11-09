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

vi.mock('@/actions/App/Http/Controllers/Member/BasketController', () => ({
    default: {
        index: { url: () => '/baskets?pending=1' },
        show: { url: () => '/baskets/basket' },
    },
}));
vi.mock('@/actions/App/Http/Controllers/Finance/BasketController', () => ({
    default: { index: { url: () => '/finance/baskets?pending=1' } },
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
            pendingPickups: [],
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
            pendingPickups: [],
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
            pendingPickups: [],
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
            pendingPickups: [],
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
            pendingPickups: [],
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

    it('lists pending collections from validated baskets, late and cycleless included, and links to all of them (R24, S42)', async () => {
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
            pendingPickups: [
                {
                    basketId: 'closed',
                    validatedAt: '2026-11-03',
                    weekly: true,
                    late: false,
                },
                {
                    basketId: 'late',
                    validatedAt: '2026-11-04',
                    weekly: true,
                    late: true,
                },
                {
                    basketId: 'stock',
                    validatedAt: '2026-11-05',
                    weekly: false,
                    late: false,
                },
            ],
        });
        installTranslations(populated);
        const html = await renderToString(populated);
        expect(html.match(/<li class="pilot-list-row"/g)).toHaveLength(3);
        expect(html).toContain('Cistella tardana');
        expect(html).toContain('Totes les recollides pendents');

        const empty = createSSRApp(Dashboard, { ...base, pendingPickups: [] });
        installTranslations(empty);
        expect(await renderToString(empty)).not.toContain(
            'data-test="pending-pickups"',
        );
    });

    it('shows Finance and Manager the exact total of pending collections only when there are some', async () => {
        const props = {
            roles: ['finance'],
            isManager: false,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            pendingPickups: [],
            managerSummary: null,
        };
        const some = createSSRApp(Dashboard, {
            ...props,
            staffPendingCollections: { count: 4 },
        });
        installTranslations(some);
        expect(await renderToString(some)).toContain('Recollides pendents: 4');

        const none = createSSRApp(Dashboard, {
            ...props,
            staffPendingCollections: { count: 0 },
        });
        installTranslations(none);
        expect(await renderToString(none)).not.toContain(
            'data-test="staff-pending-collections"',
        );
    });
});

// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, createSSRApp, h, nextTick, reactive } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Dashboard from './Dashboard.vue';

let renderLinkNames = false;
const reloadMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: (
        props: Record<string, unknown>,
        { slots }: { slots: { default?: () => string } },
    ) =>
        renderLinkNames
            ? h('a', props, slots.default?.() ?? '')
            : (slots.default?.() ?? ''),
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test User' } },
        },
    }),
    router: { reload: reloadMock },
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

    it('lists validation and collection groups as distinct actions with server links and no command control (R7, S7)', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['finance'],
            isManager: false,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            managerSummary: null,
            attention: [
                {
                    key: 'validate_basket:cycle:c1',
                    action: 'validate_basket',
                    cycleId: 'c1',
                    scheduledDate: '2026-11-07',
                    count: 2,
                    href: '/finance/baskets',
                },
                {
                    key: 'collect_basket:cycle:c1',
                    action: 'collect_basket',
                    cycleId: 'c1',
                    scheduledDate: '2026-11-07',
                    count: 1,
                    href: '/finance/baskets?pending=1&cycle=c1',
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html.match(/data-test="attention-group"/g)).toHaveLength(2);
        expect(html).toContain('Valida cistelles');
        expect(html).toContain('Recollir cistelles');
        expect(html.match(/Veure cistelles/g)).toHaveLength(2);
        expect(html).not.toContain('<button');
        expect(html).not.toContain('Tanca el cicle');
    });

    it('lists reception groups with the producer, blocker and a receptions link, without any command control (R5, R6, S5, S6)', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['finance'],
            isManager: false,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            managerSummary: null,
            attention: [
                {
                    key: 'check_delivery_and_allocations:cycle:c1',
                    action: 'check_delivery_and_allocations',
                    cycleId: 'c1',
                    scheduledDate: '2026-11-07',
                    count: 1,
                    href: '/receptions/c1/p1',
                    producerName: 'Forn de la Vila',
                    allocationDifferenceCount: 1,
                },
                {
                    key: 'mark_distributed:cycle:c1',
                    action: 'mark_distributed',
                    cycleId: 'c1',
                    scheduledDate: '2026-11-07',
                    count: 2,
                    href: '/receptions',
                    producerName: null,
                    allocationDifferenceCount: 0,
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html.match(/data-test="attention-group"/g)).toHaveLength(2);
        expect(html).toContain('Revisa l’entrega i els repartiments');
        expect(html).toContain('Forn de la Vila ·');
        expect(html).toContain(
            'El repartiment no coincideix amb el que s’ha rebut.',
        );
        expect(html.match(/data-test="attention-blocker"/g)).toHaveLength(1);
        expect(html).toContain('Marca com a distribuïda');
        expect(html.match(/Veure recepcions/g)).toHaveLength(2);
        expect(html).not.toContain('<form');
        expect(html).not.toContain('<button type="submit"');
    });

    it('lists a send group before reception work, labelled as a cycle link with no send control and no overdue wording (R9, R12, S8)', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['manager'],
            isManager: true,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            managerSummary: null,
            attention: [
                {
                    key: 'send_order:cycle:c1',
                    action: 'send_order',
                    cycleId: 'c1',
                    scheduledDate: '2026-11-07',
                    count: 1,
                    href: '/manage/cycles',
                    producerName: 'Forn de la Vila',
                    allocationDifferenceCount: 0,
                },
                {
                    key: 'check_delivery_and_allocations:cycle:c1',
                    action: 'check_delivery_and_allocations',
                    cycleId: 'c1',
                    scheduledDate: '2026-11-07',
                    count: 1,
                    href: '/receptions/c1/p2',
                    producerName: 'Hort',
                    allocationDifferenceCount: 0,
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html.match(/data-test="attention-group"/g)).toHaveLength(2);
        expect(html.indexOf('Envia les comandes als productors')).toBeLessThan(
            html.indexOf('Revisa l’entrega i els repartiments'),
        );
        expect(html).toContain('Forn de la Vila ·');

        expect(html).toContain('Veure cicles');
        expect(html).not.toContain('<form');
        expect(html).not.toContain('<button type="submit"');
        expect(html.toLowerCase()).not.toContain('endarrerida');
    });

    it('lists opportunities below the attention list, as available ordering with cutoffs and never as unfinished work (R11, R12, S3)', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['member'],
            isManager: false,
            isMember: true,
            nextPickup: null,
            openCycles: 1,
            balance: null,
            managerSummary: null,
            attention: [
                {
                    key: 'collect_basket:cycle:c0',
                    action: 'collect_basket',
                    cycleId: 'c0',
                    scheduledDate: '2026-10-31',
                    count: 1,
                    href: '/baskets?pending=1',
                    producerName: null,
                    allocationDifferenceCount: 0,
                },
            ],
            opportunities: [
                {
                    key: 'member_order:cycle:c1',
                    kind: 'member_order',
                    scheduledDate: '2026-11-07',
                    href: '/cycles/c1/order',
                    cutoffs: [
                        {
                            producerName: 'Forn de la Vila',
                            closesAt: '2026-11-04 20:00',
                        },
                    ],
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html.match(/data-test="opportunity"/g)).toHaveLength(1);
        expect(html.indexOf('data-test="attention-list"')).toBeLessThan(
            html.indexOf('data-test="opportunity-list"'),
        );
        expect(html).toContain('Es pot fer la comanda');
        expect(html).toContain('Forn de la Vila tanca el');
        expect(html).toContain('a les 20:00');
        expect(html).toContain('Obre la comanda');
        expect(html.toLowerCase()).not.toContain('sense enviar');
        expect(html.toLowerCase()).not.toContain('pendent de');
    });

    it('lists the producer review and Catalogue maintenance opportunities with no count or pending claim (R11, S10)', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['catalogue'],
            isManager: false,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            managerSummary: null,
            attention: [],
            opportunities: [
                {
                    key: 'producer_summary:cycle:c1',
                    kind: 'producer_summary',
                    scheduledDate: '2026-11-07',
                    href: '/producer/cycles/c1',
                    cutoffs: [],
                },
                {
                    key: 'catalogue_maintenance',
                    kind: 'catalogue_maintenance',
                    scheduledDate: null,
                    href: '/catalogue/producers',
                    cutoffs: [],
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html).not.toContain('data-test="attention-list"');
        expect(html.match(/data-test="opportunity"/g)).toHaveLength(2);
        expect(html).toContain('Revisa la comanda enviada');
        expect(html).toContain('Manté productes i disponibilitat');
        expect(html).not.toContain('data-test="attention-count"');
    });

    it('gives repeated opportunity rows distinct accessible link names by pickup date (R11, R16)', async () => {
        const row = (cycle: string, date: string) => ({
            key: `producer_summary:cycle:${cycle}`,
            kind: 'producer_summary',
            scheduledDate: date,
            href: `/producer/cycles/${cycle}`,
            cutoffs: [],
        });
        const app = createSSRApp(Dashboard, {
            roles: ['producer'],
            isManager: false,
            isMember: false,
            nextPickup: null,
            openCycles: 0,
            balance: null,
            managerSummary: null,
            attention: [],
            opportunities: [row('c1', '2026-11-07'), row('c2', '2026-11-14')],
        });
        installTranslations(app);
        renderLinkNames = true;
        const html = await renderToString(app).finally(() => {
            renderLinkNames = false;
        });
        const labels = [
            ...html.matchAll(/aria-label="(Obre el resum[^"]*)"/g),
        ].map((match) => match[1]);
        expect(labels).toHaveLength(2);
        expect(new Set(labels).size).toBe(2);
        expect(labels[0]).toContain('Recollida del');
    });

    it('shows one cutoff row per open producer even when two share a name (R12)', async () => {
        const app = createSSRApp(Dashboard, {
            roles: ['member'],
            isManager: false,
            isMember: true,
            nextPickup: null,
            openCycles: 1,
            balance: null,
            managerSummary: null,
            attention: [],
            opportunities: [
                {
                    key: 'member_order:cycle:c1',
                    kind: 'member_order',
                    scheduledDate: '2026-11-07',
                    href: '/cycles/c1/order',
                    cutoffs: [
                        {
                            producerName: 'Cal Pere',
                            closesAt: '2026-11-04 20:00',
                        },
                        {
                            producerName: 'Cal Pere',
                            closesAt: '2026-11-05 12:00',
                        },
                    ],
                },
            ],
        });
        installTranslations(app);
        const html = await renderToString(app);
        expect(html.match(/data-test="opportunity-cutoff"/g)).toHaveLength(2);
        expect(html).toContain('a les 20:00');
        expect(html).toContain('a les 12:00');
    });
});

type ReloadOptions = {
    onStart?: () => void;
    onSuccess?: () => void;
    onCancel?: () => void;
    onFinish?: () => void;
};

const collection = {
    key: 'collect_basket:cycle:c1',
    action: 'collect_basket',
    cycleId: 'c1',
    scheduledDate: '2026-11-14',
    count: 1,
    href: '/baskets?pending=1',
    producerName: null,
    allocationDifferenceCount: 0,
};

const baseProps = {
    roles: ['member'],
    isManager: false,
    isMember: true,
    nextPickup: null,
    openCycles: 0,
    balance: null,
    managerSummary: null,
};

const mounted: { unmount: () => void }[] = [];

function mountDashboard(props: Record<string, unknown>) {
    const state = reactive<Record<string, unknown>>({ ...baseProps, ...props });
    const root = document.createElement('div');
    document.body.appendChild(root);
    const app = createApp({ render: () => h(Dashboard, state as never) });
    installTranslations(app);
    app.mount(root);
    mounted.push(app);
    const sent = () => reloadMock.mock.calls.at(-1)?.[0] as ReloadOptions;

    return { state, root, app, sent };
}

describe('dashboard freshness (R15, R16, S11)', () => {
    afterEach(() => {
        mounted.splice(0).forEach((app) => app.unmount());
        reloadMock.mockReset();
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('shows the last successful read time and refreshes through an explicit reload with a busy message', async () => {
        const { root, sent } = mountDashboard({
            attention: [collection],
            opportunities: [],
            readFailed: false,
            lastUpdatedAt: '2026-11-12T09:30:00+01:00',
        });

        expect(
            root.querySelector('[data-test="freshness-time"]')?.textContent,
        ).toContain('09:30');
        expect(root.querySelector('[data-test="read-error"]')).toBeNull();
        expect(reloadMock).not.toHaveBeenCalled();

        root.querySelector<HTMLButtonElement>(
            '[data-test="refresh-button"]',
        )!.click();
        expect(reloadMock).toHaveBeenCalledTimes(1);

        sent().onStart?.();
        await nextTick();
        expect(root.querySelector('[role="status"]')?.textContent).not.toBe('');
        sent().onFinish?.();
    });

    it('keeps the last successful item and time, marked stale with an alert and retry, when a refresh fails to read', async () => {
        const { state, root } = mountDashboard({
            attention: [collection],
            opportunities: [],
            readFailed: false,
            lastUpdatedAt: '2026-11-12T09:30:00+01:00',
        });

        state.attention = null;
        state.opportunities = null;
        state.lastUpdatedAt = null;
        state.readFailed = true;
        await nextTick();

        expect(
            root.querySelectorAll('[data-test="attention-group"]'),
        ).toHaveLength(1);
        expect(
            root.querySelector('[data-test="freshness-time"]')?.textContent,
        ).toContain('09:30');
        expect(
            root.querySelector('[data-test="freshness-stale"]'),
        ).not.toBeNull();
        expect(root.querySelector('[role="alert"]')).not.toBeNull();
        expect(
            root.querySelector('[data-test="refresh-button"]')?.textContent,
        ).toContain('Torna-ho a provar');
    });

    it('keeps the prior data stale with retry when the request does not succeed (transport or non-success response)', async () => {
        const { root, sent } = mountDashboard({
            attention: [collection],
            opportunities: [],
            readFailed: false,
            lastUpdatedAt: '2026-11-12T09:30:00+01:00',
        });

        root.querySelector<HTMLButtonElement>(
            '[data-test="refresh-button"]',
        )!.click();
        sent().onStart?.();
        sent().onFinish?.();
        await nextTick();

        expect(
            root.querySelectorAll('[data-test="attention-group"]'),
        ).toHaveLength(1);
        expect(
            root.querySelector('[data-test="freshness-stale"]'),
        ).not.toBeNull();
        expect(root.querySelector('[role="alert"]')).not.toBeNull();
    });

    it('clears the stale state when a later read succeeds with the new time', async () => {
        const { state, root, sent } = mountDashboard({
            attention: [collection],
            opportunities: [],
            readFailed: false,
            lastUpdatedAt: '2026-11-12T09:30:00+01:00',
        });
        root.querySelector<HTMLButtonElement>(
            '[data-test="refresh-button"]',
        )!.click();
        sent().onStart?.();
        sent().onFinish?.();
        await nextTick();

        root.querySelector<HTMLButtonElement>(
            '[data-test="refresh-button"]',
        )!.click();
        sent().onStart?.();
        state.attention = [];
        state.lastUpdatedAt = '2026-11-12T09:41:00+01:00';
        sent().onSuccess?.();
        sent().onFinish?.();
        await nextTick();

        expect(
            root.querySelectorAll('[data-test="attention-group"]'),
        ).toHaveLength(0);
        expect(
            root.querySelector('[data-test="freshness-time"]')?.textContent,
        ).toContain('09:41');
        expect(root.querySelector('[data-test="freshness-stale"]')).toBeNull();
        expect(root.querySelector('[role="alert"]')).toBeNull();
    });

    it('shows only the error and retry on an initial failed read, never a list, zero or caught-up claim', async () => {
        const { root } = mountDashboard({
            attention: null,
            opportunities: null,
            readFailed: true,
            lastUpdatedAt: null,
        });

        expect(root.querySelector('[role="alert"]')).not.toBeNull();
        expect(
            root.querySelector('[data-test="refresh-button"]')?.textContent,
        ).toContain('Torna-ho a provar');
        expect(root.querySelector('[data-test="freshness-time"]')).toBeNull();
        expect(root.querySelector('[data-test="attention-list"]')).toBeNull();
        expect(root.querySelector('[data-test="opportunity-list"]')).toBeNull();
        expect(root.querySelector('[data-test="caught-up"]')).toBeNull();
    });

    it('reads once when the browser restores the page from history, and not for a fresh show', () => {
        mountDashboard({
            attention: [],
            opportunities: [],
            readFailed: false,
            lastUpdatedAt: '2026-11-12T09:30:00+01:00',
        });

        window.dispatchEvent(
            Object.assign(new Event('pageshow'), { persisted: false }),
        );
        expect(reloadMock).not.toHaveBeenCalled();

        window.dispatchEvent(
            Object.assign(new Event('pageshow'), { persisted: true }),
        );
        expect(reloadMock).toHaveBeenCalledTimes(1);
    });

    it('never polls while the page stays open', () => {
        vi.useFakeTimers();
        mountDashboard({
            attention: [],
            opportunities: [],
            readFailed: false,
            lastUpdatedAt: '2026-11-12T09:30:00+01:00',
        });

        vi.advanceTimersByTime(30 * 60 * 1000);

        expect(reloadMock).not.toHaveBeenCalled();
    });
});

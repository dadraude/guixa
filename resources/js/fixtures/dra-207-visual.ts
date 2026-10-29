import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const pageName = query.get('page') ?? 'Manage/Stock/Index';
const state = query.get('state') ?? 'populated';
const fixtureUrl = window.location.href;
const fixturePath = window.location.pathname + window.location.search;
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;

if (!components[`../pages/${pageName}.vue`])
    throw new Error(`Unknown page: ${pageName}`);

const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const producer = {
    id: '0198f0a0-0000-7000-8000-000000000001',
    name:
        state === 'long'
            ? 'La Figuera del barri de la Vall i de les terres altes'
            : 'La Figuera',
    active: true,
    cutoffDaysBefore: 3,
    cutoffTime: '20:00',
    accountEmail: null,
    accountInvitationPending: false,
};
const stock = {
    id: '0198f0a0-0000-7000-8000-000000000002',
    producerId: producer.id,
    name:
        state === 'long'
            ? 'Mel artesanal de romaní de la Figuera del barri de la Vall'
            : 'Mel de romaní',
    unit: 'pot',
    price: '6.50',
    variable: false,
    containersPerUnit: '2.000',
    active: true,
    available: state === 'empty' ? '0.000' : '12.000',
};
const longProducts = Array.from({ length: 8 }, (_, index) => ({
    ...stock,
    id: `0198f0a0-0000-7000-8000-${String(index + 10).padStart(12, '0')}`,
    name: `${stock.name} ${index + 1}`,
}));
const propsByPage: Record<string, Record<string, unknown>> = {
    'Manage/Producers/Index': {
        producers:
            state === 'empty'
                ? []
                : state === 'long'
                  ? [
                        { ...producer, name: producer.name },
                        ...Array.from({ length: 5 }, (_, index) => ({
                            ...producer,
                            id: `producer-${index}`,
                            name: `${producer.name} ${index + 1}`,
                        })),
                    ]
                  : [producer],
    },
    'Manage/Stock/Create': { producer },
    'Manage/Stock/Index': {
        products:
            state === 'empty' ? [] : state === 'long' ? longProducts : [stock],
    },
    'Manage/Stock/Edit': { product: stock },
    'Manage/Stock/Movements': {
        product: stock,
        cycles:
            state === 'empty'
                ? []
                : [{ id: 'cycle-1', pickupDate: '2026-11-07' }],
    },
};

const page = {
    component: pageName,
    url: fixturePath,
    version: 'dra-207',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user: {
                id: 'manager-1',
                name: 'Gestora',
                email: 'gestora@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: ['manager'],
        },
        roleAreas: [
            { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
        ],
        sidebarOpen: true,
        ...propsByPage[pageName],
    },
};

if (state === 'error' || state === 'disabled') {
    http.setClient({
        request: async () => {
            if (state === 'disabled') return new Promise(() => {});
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    url: fixturePath,
                    props: {
                        ...page.props,
                        errors: {
                            domain: 'No s’ha pogut desar el canvi. Torna-ho a provar.',
                        },
                    },
                }),
                headers: { 'x-inertia': 'true' },
            };
        },
    });
}

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: () => AppLayout,
    withApp: (app, { page }) => installTranslations(app, page.props.locale),
    progress: false,
}).then(async () => {
    const waitFor = async (selector: string): Promise<HTMLElement> => {
        for (let attempt = 0; attempt < 100; attempt++) {
            const element = document.querySelector<HTMLElement>(selector);
            if (element) return element;
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
        throw new Error(`Visual element missing: ${selector}`);
    };
    await waitFor('.coop-manager-shell');
    if (window.matchMedia('(max-width: 767px)').matches) {
        const toggle = await waitFor('button[data-sidebar="trigger"]');
        toggle.click();
        await waitFor('a[href="/manage/stock"]');
        toggle.click();
        for (let attempt = 0; attempt < 100; attempt++) {
            if (
                !document.querySelector(
                    '[data-sidebar="sidebar"][data-mobile="true"]',
                )
            )
                break;
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
        if (
            document.querySelector(
                '[data-sidebar="sidebar"][data-mobile="true"]',
            )
        ) {
            throw new Error('Mobile Manager navigation did not close');
        }
    } else {
        await waitFor('.coop-manager-shell a[href="/manage/stock"]');
    }
    if (
        !page.props.auth.roles.includes('manager') ||
        page.props.roleAreas[0]?.role !== 'manager'
    ) {
        throw new Error('Manager role context missing');
    }

    if (state === 'search-empty' && pageName === 'Manage/Stock/Index') {
        const search = (await waitFor('#stock-search')) as HTMLInputElement;
        search.value = 'cap coincidència';
        search.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (state === 'edit-dialog' && pageName === 'Manage/Stock/Index') {
        (await waitFor(`button[aria-label*="${stock.name}"]`)).click();
        await waitFor('dialog[open]');
    }
    if (state === 'error' || state === 'disabled') {
        const form = (await waitFor('#main-content form')) as HTMLFormElement;
        for (const input of form.querySelectorAll<HTMLInputElement>(
            'input[required]',
        )) {
            input.value =
                input.id === 'price'
                    ? '6.50'
                    : input.type === 'number'
                      ? '1'
                      : 'Exemple';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        form.requestSubmit();
    }
    if (state === 'error') await waitFor('#main-content p.text-destructive');
    if (state === 'disabled') await waitFor('#main-content button:disabled');

    const focusRoot =
        state === 'edit-dialog'
            ? await waitFor('dialog[open]')
            : await waitFor('#main-content');
    const focusable = [
        ...focusRoot.querySelectorAll<HTMLElement>(
            'a[href],button,input,select,textarea,summary',
        ),
    ].filter(
        (element) =>
            element.getClientRects().length > 0 &&
            element.tabIndex >= 0 &&
            !element.matches(':disabled') &&
            !element.closest('details:not([open])'),
    );
    if (focusable.length === 0)
        throw new Error(`No focusable controls: ${pageName}/${state}`);
    const prefix = state === 'edit-dialog' ? 'overlay' : 'page';
    focusable[0].setAttribute(`data-visual-${prefix}-focus-first`, '');
    focusable.at(-1)?.setAttribute(`data-visual-${prefix}-focus-last`, '');
    if (state === 'edit-dialog') {
        const pageControls = [
            ...(await waitFor('#main-content')).querySelectorAll<HTMLElement>(
                'a[href],button,input',
            ),
        ].filter((element) => element.tabIndex >= 0);
        pageControls[0]?.setAttribute('data-visual-page-focus-first', '');
        pageControls.at(-1)?.setAttribute('data-visual-page-focus-last', '');
    }
    document.body.dataset.visualReady = state;
    window.history.replaceState(window.history.state, '', fixtureUrl);
});

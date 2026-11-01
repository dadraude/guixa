import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const pageName = query.get('page') ?? 'Manage/Producers/Products';
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

const managerPage = pageName.startsWith('Manage/');
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
const weekly = {
    id: '0198f0a0-0000-7000-8000-000000000003',
    producerId: producer.id,
    name:
        state === 'long'
            ? 'Pa de massa mare de cereals integrals elaborat a la Vall'
            : 'Pa de massa mare',
    unit: 'barra',
    price: '3.50',
    variable: false,
    containersPerUnit: null,
    active: true,
    stock: false,
    used: state === 'used-denied',
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
    stock: true,
    used: state === 'used-denied',
    available: '12.000',
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
                        producer,
                        ...Array.from({ length: 5 }, (_, i) => ({
                            ...producer,
                            id: `producer-${i}`,
                        })),
                    ]
                  : [producer],
    },
    'Manage/Producers/Products': {
        producer,
        products:
            state === 'empty'
                ? []
                : state === 'long'
                  ? [weekly, ...longProducts]
                  : [weekly, stock],
    },
    'Manage/Stock/Create': { producer },
    'Manage/Stock/Index': {
        products:
            state === 'empty' ? [] : state === 'long' ? longProducts : [stock],
    },
    'Manage/Stock/Edit': { product: stock, used: state === 'used-denied' },
    'Producer/Products/Index': {
        products:
            state === 'empty'
                ? []
                : state === 'long'
                  ? [
                        weekly,
                        ...longProducts.map((item) => ({
                            ...item,
                            stock: false,
                        })),
                    ]
                  : [weekly],
    },
    'Producer/Products/Create': {},
    'Producer/Products/Edit': {
        product: weekly,
        used: state === 'used-denied',
    },
};
const roles = managerPage
    ? state === 'mixed-role'
        ? ['manager', 'producer']
        : ['manager']
    : ['producer'];
const roleAreas = managerPage
    ? [
          { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
          ...(state === 'mixed-role'
              ? [
                    {
                        role: 'producer',
                        label: 'Producer',
                        href: '/producer/products',
                    },
                ]
              : []),
      ]
    : [{ role: 'producer', label: 'Producer', href: '/producer/products' }];
const page = {
    component: pageName,
    url: fixturePath,
    version: 'dra-208',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user: {
                id: 'actor-1',
                name: managerPage ? 'Gestora' : 'Productora',
                email: 'actor@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles,
        },
        roleAreas,
        sidebarOpen: true,
        ...propsByPage[pageName],
    },
};

if (state === 'error' || state.startsWith('action-error-')) {
    http.setClient({
        request: async () => ({
            status: 200,
            data: JSON.stringify({
                ...page,
                url: fixturePath,
                props: {
                    ...page.props,
                    errors: {
                        domain: state.startsWith('action-error-')
                            ? 'Aquest producte ja s’ha utilitzat. Actualitza la pàgina i torna-ho a provar.'
                            : 'No s’ha pogut desar el canvi. Torna-ho a provar.',
                    },
                },
            }),
            headers: { 'x-inertia': 'true' },
        }),
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
    await waitFor(managerPage ? '.coop-manager-shell' : '#main-content');
    if (window.matchMedia('(max-width: 767px)').matches) {
        const toggle = await waitFor('button[data-sidebar="trigger"]');
        toggle.click();
        await waitFor('[data-sidebar="sidebar"][data-mobile="true"]');
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
        )
            throw new Error('Mobile navigation did not close');
    }
    if (state === 'edit-dialog' && pageName === 'Manage/Stock/Index') {
        (await waitFor(`button[aria-label*="${stock.name}"]`)).click();
        await waitFor('dialog[open]');
    }
    if (state === 'confirm-classification') {
        (await waitFor('[data-test="change-product-classification"]')).click();
        await waitFor('dialog[open]');
    }
    if (state === 'confirm-delete') {
        (await waitFor('[data-test="delete-unused-product"]')).click();
        await waitFor('dialog[open]');
    }
    if (state === 'error') {
        const form = (await waitFor('#main-content form')) as HTMLFormElement;
        for (const input of form.querySelectorAll<HTMLInputElement>(
            'input[required]',
        )) {
            input.value = input.id === 'price' ? '6.50' : 'Exemple';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        form.requestSubmit();
        await waitFor('#main-content p.text-destructive');
    }
    if (state.startsWith('action-error-')) {
        const selector = state.endsWith('classification')
            ? '[data-test="change-product-classification"]'
            : '[data-test="delete-unused-product"]';
        (await waitFor(selector)).click();
        await waitFor('dialog[open]');
        (await waitFor('[data-test="confirm-product-action"]')).click();
        let rejectionShown = false;
        for (let attempt = 0; attempt < 100; attempt++) {
            rejectionShown = [
                ...document.querySelectorAll<HTMLElement>(
                    '#main-content p.text-destructive',
                ),
            ].some(
                (message) =>
                    message.getClientRects().length > 0 &&
                    message.textContent?.includes(
                        'Aquest producte ja s’ha utilitzat',
                    ),
            );
            if (rejectionShown) break;
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
        if (!rejectionShown) {
            throw new Error(
                `Action rejection was not shown: ${pageName}/${state}`,
            );
        }
        // The runner captures the full page from its document origin. Restore that
        // origin after confirming the real callback so sticky headers appear once.
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
        window.scrollTo(0, 0);
        await new Promise((resolve) =>
            requestAnimationFrame(() => requestAnimationFrame(resolve)),
        );
    }
    const overlay = state === 'edit-dialog' || state.startsWith('confirm-');
    const focusRoot = overlay
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
    const prefix = overlay ? 'overlay' : 'page';
    focusable[0].setAttribute(`data-visual-${prefix}-focus-first`, '');
    focusable.at(-1)?.setAttribute(`data-visual-${prefix}-focus-last`, '');
    document.body.dataset.visualReady = state;
    window.history.replaceState(window.history.state, '', fixtureUrl);
});

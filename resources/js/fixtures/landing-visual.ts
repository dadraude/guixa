import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import { defineComponent, h, nextTick, type DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { trans } from 'laravel-vue-i18n';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

/*
 * Deterministic review fixture for T84 (landing). Only the HTTP client is mocked: the real
 * Inertia router, layouts, pages and portals run. It proves presentation, never persistence or
 * authorization.
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Manage/Stock/Index';
const state = query.get('state') ?? 'default';
const role = query.get('role') ?? 'manager';
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
if (!components[`../pages/${pageName}.vue`]) {
    throw new Error(`Unknown visual page: ${pageName}`);
}

const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'light' || mode === 'dark' ? mode : 'system',
);
initializeTheme();
if (query.get('fallback') === '1')
    document.documentElement.dataset.fontFallback = 'true';
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const longName =
    'Mongetes seques de la varietat tradicional del Ganxet cultivades en terrasses amb reg de pluja';
const products = Array.from({ length: 20 }, (_, index) => ({
    id: `stock-${index}`,
    producerId: 'producer-1',
    name: index === 2 && state === 'long-name' ? longName : `Producte ${index}`,
    unit: 'pot',
    variable: false,
    price: '5.00',
    containersPerUnit: '1.000',
    available: '9.000',
    active: index !== 0,
}));
const pageProps: Record<string, Record<string, unknown>> = {
    'Manage/Stock/Index': { products: state === 'empty' ? [] : products },
    'Orders/Index': {
        cycles: [
            {
                cycleId: 'cycle-1',
                pickupDate: '2026-11-05',
                open: true,
                cancelled: false,
                lineCount: 1,
            },
        ],
    },
    'Orders/Edit': {
        order: {
            cycleId: 'cycle-1',
            pickupDate: '2026-11-05',
            cancelled: false,
            producers: [
                {
                    producerId: 'producer-1',
                    producerName: 'Hort de la Vall',
                    open: true,
                    products: [
                        {
                            id: 'product-1',
                            name: 'Tomàquets',
                            unit: 'kg',
                            price: '3.20',
                            variable: true,
                            containersPerUnit: '1',
                            active: true,
                        },
                    ],
                },
            ],
            lines: [
                {
                    productId: 'product-1',
                    producerId: 'producer-1',
                    producerName: 'Hort de la Vall',
                    name: 'Tomàquets',
                    unit: 'kg',
                    price: '3.20',
                    variable: true,
                    quantity: '2',
                    notDelivered: false,
                },
            ],
            basketId: null,
        },
        preview: { totalCents: 0, amountCents: {} },
    },
    'Manage/Accounts/Index': { accounts: [], households: [] },
    Welcome: {},
};
const areas = [
    { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
    { role: 'member', label: 'Member', href: '/orders' },
    { role: 'catalogue', label: 'Catalogue', href: '/catalogue/producers' },
];
const urls: Record<string, string> = {
    'Manage/Stock/Index': '/manage/stock',
    'Orders/Index': '/',
    'Orders/Edit': '/cycles/cycle-1/order',
    'Manage/Accounts/Index': '/manage/accounts',
    Welcome: '/',
};
const page = {
    component: pageName,
    url: urls[pageName] ?? '/manage/stock',
    version: 'landing',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user:
                pageName === 'Welcome'
                    ? null
                    : {
                          id: 'account-1',
                          name: 'Maria Soler',
                          email: 'maria@example.test',
                          avatar: '',
                          email_verified_at: '2026-10-01T12:00:00Z',
                          created_at: '2026-10-01T12:00:00Z',
                          updated_at: '2026-10-01T12:00:00Z',
                      },
            roles: pageName === 'Welcome' ? [] : [role],
        },
        roleAreas:
            pageName === 'Welcome'
                ? []
                : areas.filter((area) => area.role === role),
        sidebarOpen: true,
        ...pageProps[pageName],
    },
};

const errorResponse = (errors: Record<string, string>) => ({
    status: 200,
    data: JSON.stringify({
        ...page,
        url: window.location.pathname + window.location.search,
        props: { ...page.props, errors },
    }),
    headers: { 'x-inertia': 'true' },
});
if (state === 'dialog-server-error') {
    http.setClient({
        request: async () =>
            errorResponse({ available: 'La quantitat no és vàlida.' }),
    });
}
if (state === 'dialog-processing') {
    http.setClient({ request: () => new Promise(() => {}) });
}

// Request spy: interaction and navigation states count every request the real router sends.
const requestLog: string[] = [];
const pagesByPath: Record<string, string> = {
    '/': 'Orders/Index',
    '/cycles/cycle-1/order': 'Orders/Edit',
    '/manage/accounts': 'Manage/Accounts/Index',
    '/manage/stock': 'Manage/Stock/Index',
};
if (
    state.startsWith('nav-') ||
    ['dialog-confirm-cancel', 'dialog-escape', 'dialog-unchanged'].includes(
        state,
    )
) {
    http.setClient({
        request: async (config: { url: string; method?: string }) => {
            const url = new URL(config.url, window.location.origin);
            const path = url.pathname;
            requestLog.push(`${config.method ?? 'get'} ${path}`);
            // '/' is the member's order list, or Welcome for a guest (OrderController::index).
            const guest =
                path === '/' && url.searchParams.get('as') === 'guest';
            const component = guest
                ? 'Welcome'
                : (pagesByPath[path] ?? 'Manage/Stock/Index');
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    component,
                    url: path,
                    props: {
                        ...page.props,
                        ...(guest
                            ? { auth: { user: null, roles: [] }, roleAreas: [] }
                            : {}),
                        ...pageProps[component],
                    },
                }),
                headers: { 'x-inertia': 'true' },
            };
        },
    });
}

// Reversion fixture: the same route with the pilot theme but without the variant.
const RevertedLayout = defineComponent({
    inheritAttrs: false,
    setup:
        (_, { attrs, slots }) =>
        () =>
            h(
                AppSidebarLayout,
                { ...attrs, pilotTheme: true, managerTheme: true },
                slots,
            ),
});

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

const sleep = (ms: number) =>
    new Promise((resolve) => window.setTimeout(resolve, ms));
const waitFor = async <T>(find: () => T | null | undefined): Promise<T> => {
    for (let attempt = 0; attempt < 120; attempt++) {
        const found = find();
        if (found) return found;
        await sleep(25);
    }
    throw new Error(`Visual target missing in state ${state}`);
};
const visible = (element: HTMLElement) => element.getClientRects().length > 0;
const editTarget = state === 'dialog-inactive' ? 'Producte 0' : 'Producte 1';
const editButton = () =>
    [...document.querySelectorAll<HTMLElement>('button')].find(
        (button) =>
            new RegExp(`^Edita:? ${editTarget}$`).test(
                button.getAttribute('aria-label') ?? '',
            ) && visible(button),
    );
const rgbOf = (element: Element, value: string) => {
    const probe = document.createElement('span');
    probe.style.color = value;
    element.append(probe);
    const color = getComputedStyle(probe).color;
    probe.remove();
    return color;
};
const measured: Record<string, string> = {
    shell: '.reference-style',
    // The mobile sidebar is the SheetContent itself (data-slot is overridden by Sidebar.vue).
    sheet: '[data-sidebar="sidebar"][data-mobile="true"]',
    menu: '[data-slot="dropdown-menu-content"]',
    dialog: 'dialog[open]',
};
// Records the resolved primary and background of the shell and any open overlay as body attributes.
// Colour transitions on the shell and sheet must finish before a value is recorded.
const settle = async () => {
    await sleep(50);
    for (let attempt = 0; attempt < 40; attempt++) {
        const running = document
            .getAnimations()
            .filter((animation) => animation.playState === 'running');
        if (running.length === 0) return;
        await Promise.allSettled(
            running.map((animation) => animation.finished),
        );
        await sleep(25);
    }
};
const snapshot = async (label: string) => {
    await settle();
    for (const [name, selector] of Object.entries(measured)) {
        const element = document.querySelector(selector);
        if (!element) continue;
        document.body.setAttribute(
            `data-${label}-${name}-primary`,
            rgbOf(element, 'var(--primary)'),
        );
        document.body.setAttribute(
            `data-${label}-${name}-bg`,
            getComputedStyle(element).backgroundColor,
        );
    }
};
const heading = () => document.querySelector('#main-content h1')?.textContent;
const visit = async (path: string, title: string) => {
    router.visit(path);
    await waitFor(() => heading() === title);
    await sleep(150);
};
// A guest at '/' gets Welcome: no shell, so no sheet, menu or landing class may remain.
const visitWelcome = async () => {
    router.visit('/?as=guest');
    await waitFor(() => document.querySelector('.welcome-page'));
    await sleep(150);
    const welcome = getComputedStyle(document.querySelector('.welcome-page')!);
    const count = (selector: string) =>
        String(document.querySelectorAll(selector).length);
    const set = (name: string, value: string) =>
        document.body.setAttribute(`data-w-${name}`, value);
    set('landing', count('.landing'));
    set('sheet', count(measured.sheet));
    set('menu', count(measured.menu));
    set('paper', welcome.getPropertyValue('--welcome-paper').trim());
    set('blue', welcome.getPropertyValue('--welcome-blue').trim());
};
const openSheet = async () => {
    if (document.querySelector(measured.sheet)) return;
    (
        await waitFor(() =>
            document.querySelector<HTMLElement>(
                'button[data-sidebar="trigger"]',
            ),
        )
    ).click();
    await waitFor(() => document.querySelector(measured.sheet));
    await sleep(600);
};
const openMenu = async () => {
    if (document.querySelector(measured.menu)) return;
    const trigger = await waitFor(() =>
        [
            ...document.querySelectorAll<HTMLElement>(
                '[data-test="sidebar-menu-button"]',
            ),
        ].find(visible),
    );
    trigger.dispatchEvent(
        new KeyboardEvent('keydown', {
            key: 'ArrowDown',
            code: 'ArrowDown',
            bubbles: true,
            cancelable: true,
        }),
    );
    await waitFor(() => document.querySelector(measured.menu));
    await sleep(300);
};
// Two frames let any request queued by the action under test reach the spy before it is read.
const nextFrames = async () => {
    await nextTick();
    await new Promise((resolve) => requestAnimationFrame(resolve));
    await new Promise((resolve) => requestAnimationFrame(resolve));
};
const buttonWithText = (root: ParentNode, text: string) =>
    [...root.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.trim() === text && visible(button),
    );
const describeActive = () => {
    const active = document.activeElement as HTMLElement | null;
    return (
        active?.getAttribute('aria-label') ??
        active?.textContent?.trim() ??
        'none'
    );
};
const setValue = (input: HTMLInputElement, value: string) => {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
};

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: (name) => {
        if (name === 'Welcome') return null;
        return state === 'variant-off' ? RevertedLayout : AppLayout;
    },
    withApp: (app, { page }) => {
        installTranslations(app, page.props.locale);
        app.directive('focus', {
            mounted: (element: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) element.focus();
            },
        });
    },
    progress: false,
}).then(async () => {
    if (state === 'search' || state === 'no-match') {
        const input = await waitFor(() =>
            document.querySelector<HTMLInputElement>('#stock-search'),
        );
        setValue(input, state === 'search' ? 'Producte 1' : 'oli');
        await nextFrames();
    }
    if (state.startsWith('dialog')) {
        const trigger = await waitFor(editButton);
        trigger.focus();
        trigger.click();
        const dialog = await waitFor(() =>
            document.querySelector<HTMLDialogElement>('dialog[open]'),
        );
        const field = await waitFor(() =>
            dialog.querySelector<HTMLInputElement>('#stock-available'),
        );
        field.focus();
        if (state === 'dialog-error-local') setValue(field, '9,1234');
        if (['dialog-server-error', 'dialog-processing'].includes(state))
            setValue(field, '9,125');
        await nextFrames();
        if (
            state !== 'dialog' &&
            state !== 'dialog-inactive' &&
            state !== 'dialog-confirm' &&
            state !== 'dialog-confirm-cancel' &&
            state !== 'dialog-escape'
        ) {
            const form =
                dialog.querySelector<HTMLFormElement>('form:not([method])');
            // Chrome's native pattern check blocks "9,1234" before Vue sees it; bypass it so the
            // component's own message can be measured.
            if (state === 'dialog-error-local' && form) form.noValidate = true;
            form?.requestSubmit();
        }
        if (state === 'dialog-processing')
            await waitFor(() =>
                dialog.querySelector('[data-test="save-available"]:disabled'),
            );
        if (state === 'dialog-error-local')
            await waitFor(() =>
                dialog.textContent?.includes(
                    trans(
                        'Enter a valid quantity with at most three decimal places.',
                    ),
                ),
            );
        if (state === 'dialog-server-error')
            await waitFor(() =>
                dialog.textContent?.includes('La quantitat no és vàlida.'),
            );
        if (state === 'dialog-unchanged') {
            // Positive signal: the dialog closed and focus returned to its trigger.
            await waitFor(
                () => !dialog.open && document.activeElement === trigger,
            );
            await nextFrames();
        }
    }
    if (state === 'dialog-confirm' || state === 'dialog-confirm-cancel') {
        const dialog = document.querySelector('dialog[open]') as HTMLElement;
        dialog.querySelector('summary')?.click();
        const status = await waitFor(() =>
            buttonWithText(dialog, trans('Deactivate')),
        );
        status.click();
        // The confirmation is identified by its semantic action button, not by styling.
        const confirmation = await waitFor(() =>
            dialog.querySelector('[data-test^="confirm-status-"]'),
        );
        document.body.dataset.confirmShown = String(
            visible(confirmation as HTMLElement),
        );
    }
    if (state === 'nav-sheet' || state === 'nav-menu') {
        const open = state === 'nav-sheet' ? openSheet : openMenu;
        await open();
        await snapshot('s1');
        await visit('/manage/accounts', 'Comptes');
        await open();
        await snapshot('s2');
        await visit('/manage/stock', 'Estoc');
        await open();
        await snapshot('s3');
        await visitWelcome();
        const [back, title] =
            pageName === 'Orders/Edit'
                ? ['/cycles/cycle-1/order', 'La teva comanda']
                : ['/', 'Comandes'];
        await visit(back, title);
        await open();
        await snapshot('s4');
        document.body.dataset.navRequests = requestLog.join(',');
    }
    if (state === 'dialog-confirm-cancel') {
        const dialog = document.querySelector('dialog[open]') as HTMLElement;
        const back = await waitFor(() => buttonWithText(dialog, trans('Back')));
        back.click();
        // Positive signal: the confirmation is gone and the status action is offered again.
        await waitFor(
            () =>
                !dialog.querySelector('[data-test^="confirm-status-"]') &&
                buttonWithText(dialog, trans('Deactivate')),
        );
        await nextFrames();
        document.body.dataset.confirmVisible = String(
            !!dialog.querySelector('[data-test^="confirm-status-"]'),
        );
    }
    if (state === 'dialog-escape') {
        // A script cannot send a trusted Escape; requestClose() runs the same cancel-then-close steps.
        const dialog = await waitFor(() =>
            document.querySelector<HTMLDialogElement>('dialog[open]'),
        );
        if (dialog.requestClose) dialog.requestClose();
        else dialog.close();
        // Positive signal: the dialog closed and focus is back on the trigger.
        await waitFor(
            () => !dialog.open && describeActive() === 'Edita Producte 1',
        );
        await nextFrames();
    }
    if (state === 'account-menu') {
        const trigger = await waitFor(() =>
            [
                ...document.querySelectorAll<HTMLElement>(
                    '[data-test="sidebar-menu-button"]',
                ),
            ].find(visible),
        );
        trigger.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'ArrowDown',
                code: 'ArrowDown',
                bubbles: true,
                cancelable: true,
            }),
        );
        await waitFor(() =>
            document.querySelector('[data-slot="dropdown-menu-content"]'),
        );
    }
    if (query.get('drawer') === '1') {
        const trigger = await waitFor(() =>
            document.querySelector<HTMLElement>(
                'button[data-sidebar="trigger"]',
            ),
        );
        trigger.click();
        await waitFor(() =>
            document.querySelector(
                '[data-sidebar="sidebar"][data-mobile="true"]',
            ),
        );
    }
    await snapshot('now');
    document.body.dataset.requests = String(requestLog.length);
    document.body.dataset.dialogOpen = String(
        !!document.querySelector('dialog[open]'),
    );
    document.body.dataset.active = describeActive();
    const welcome = document.querySelector('.welcome-page');
    if (welcome) {
        const welcomeStyle = getComputedStyle(welcome);
        document.body.dataset.welcomePaper = welcomeStyle
            .getPropertyValue('--welcome-paper')
            .trim();
        document.body.dataset.welcomeBlue = welcomeStyle
            .getPropertyValue('--welcome-blue')
            .trim();
    }
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

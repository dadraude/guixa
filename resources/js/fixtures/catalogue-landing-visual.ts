import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import { defineComponent, h, type DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';
import { initializeFlashToast } from '@/lib/flashToast';

/*
 * Deterministic review fixture for the Catalogue pages in the landing identity (spec 005). Only the
 * HTTP client is mocked: the real Inertia router, layouts, pages and portals run. It proves
 * presentation, never persistence or authorization. The role query parameter only picks the shell
 * navigation (catalogue, or manager for the Manager variant and the Manager-only control page).
 */
const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const pageName = query.get('page') ?? 'Catalogue/Producers/Index';
const state = query.get('state') ?? 'default';
const role = query.get('role') ?? 'catalogue';
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
    'Cooperativa agrícola de la Vall de les Hortes i del Ganxet tradicional de la plana';
const makeProducer = (id: string, name: string, active = true) => ({
    id,
    name,
    active,
    cutoffDaysBefore: 3,
    cutoffTime: '20:00',
    accountEmail: null,
    accountInvitationPending: false,
});
const producersByState: Record<string, ReturnType<typeof makeProducer>[]> = {
    default: [
        makeProducer('producer-1', 'Hort de la Vall'),
        makeProducer('producer-2', 'Formatges del Montseny'),
        makeProducer('producer-3', 'Forn de Sant Joan', false),
    ],
    // The deactivated producer comes first: the first checkpoint measures this state.
    deactivated: [
        makeProducer('producer-3', 'Forn de Sant Joan', false),
        makeProducer('producer-1', 'Hort de la Vall'),
    ],
    empty: [],
    'long-name': [
        makeProducer('producer-1', longName),
        makeProducer('producer-2', longName, false),
        makeProducer('producer-3', 'Hort de la Vall'),
    ],
};
const makeProduct = (
    id: string,
    name: string,
    over: Record<string, unknown> = {},
) => ({
    id,
    producerId: 'producer-1',
    name,
    unit: 'unitat',
    price: '3.50',
    variable: false,
    containersPerUnit: null,
    active: true,
    stock: false,
    used: false,
    can: { edit: true, changeClassification: true, delete: false },
    available: null as string | null,
    ...over,
});
const readOnly = {
    can: { edit: false, changeClassification: false, delete: false },
};
const productsByState: Record<string, ReturnType<typeof makeProduct>[]> = {
    default: [
        makeProduct('product-1', 'Pa de pagès', { price: '3.50' }),
        makeProduct('product-2', 'Mel de farigola', {
            stock: true,
            unit: 'pot',
            price: '8.90',
            available: '12.000',
        }),
        makeProduct('product-3', 'Brioix de l’àvia', {
            active: false,
            price: '2.25',
        }),
        makeProduct('product-4', 'Coca de recapte', { used: true }),
    ],
    // The inactive product comes first: the first checkpoint measures this state.
    checkpoint: [
        makeProduct('product-3', 'Brioix de l’àvia', {
            active: false,
            price: '2.25',
        }),
        makeProduct('product-2', 'Mel de farigola', {
            stock: true,
            unit: 'pot',
            price: '8.90',
            available: '12.000',
        }),
    ],
    empty: [],
    'long-name': [
        makeProduct('product-1', longName, {
            stock: true,
            unit: 'pot',
            price: '1234.50',
            available: '1234567.000',
        }),
        makeProduct('product-2', longName, { active: false }),
    ],
    'read-only': [
        makeProduct('product-2', 'Mel de farigola', {
            ...readOnly,
            stock: true,
            unit: 'pot',
            price: '8.90',
            available: '12.000',
        }),
        makeProduct('product-1', 'Pa de pagès', readOnly),
    ],
};
// Error and busy states reuse the checkpoint products; navigation and reversion states the default ones.
const productsState =
    { error: 'checkpoint', busy: 'checkpoint' }[state] ??
    state.replace(/^(?:variant-off|nav-.*)$/, 'default');
const productRows = (
    productsByState[productsState] ?? productsByState.default
).map((product) => ({ ...product }));
const pageProps: Record<string, Record<string, unknown>> = {
    'Catalogue/Producers/Index': {
        producers: producersByState[state] ?? producersByState.default,
    },
    'Catalogue/Producers/Products': {
        producer: { id: 'producer-1', name: 'Hort de la Vall' },
        products: productRows,
        canCreateStock: state !== 'read-only',
    },
    // Unmigrated consumers of the shared dialog, for the leakage probe.
    'Producer/Products/Edit': {
        product: {
            id: 'product-1',
            name: 'Pa de pagès',
            unit: 'unitat',
            price: '3.50',
            variable: false,
            containersPerUnit: null,
            active: true,
        },
        used: false,
    },
    'Manage/Cycles/Index': {
        cycles: [],
        producers: [],
        pickupWeekday: null,
        pendingCollections: {},
    },
    'Manage/Accounts/Index': { accounts: [], households: [] },
    Welcome: {},
};
const areas = [
    { role: 'producer', label: 'Producer', href: '/producer/products' },
    { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
    { role: 'catalogue', label: 'Catalogue', href: '/catalogue/producers' },
];
const urls: Record<string, string> = {
    'Catalogue/Producers/Index': '/catalogue/producers',
    'Catalogue/Producers/Products': '/catalogue/producers/producer-1/products',
    'Producer/Products/Edit': '/producer/products/product-1/edit',
    'Manage/Cycles/Index': '/manage/cycles',
    'Manage/Accounts/Index': '/manage/accounts',
    Welcome: '/',
};
const page = {
    component: pageName,
    url: urls[pageName] ?? '/catalogue/producers',
    version: 'catalogue-landing',
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
        sidebarOpen: query.get('tooltip') !== '1',
        ...pageProps[pageName],
    },
};

// Request spy: navigation states count every request the real router sends.
const requestLog: string[] = [];
const pagesByPath: Record<string, string> = {
    '/catalogue/producers/producer-1/products': 'Catalogue/Producers/Products',
    '/catalogue/producers': 'Catalogue/Producers/Index',
    '/manage/accounts': 'Manage/Accounts/Index',
};
const flashMessage = 'Classificació del producte actualitzada.';
http.setClient({
    request: async (config: { url: string; method?: string }) => {
        const path = new URL(config.url, window.location.origin).pathname;
        const method = (config.method ?? 'get').toLowerCase();
        requestLog.push(`${method} ${path}`);
        const reply = (body: Record<string, unknown>) => ({
            status: 200,
            data: JSON.stringify(body),
            headers: { 'x-inertia': 'true' },
        });
        // Busy state: the request never answers, so the page stays in `processing`.
        if (method === 'put' && state === 'busy') return new Promise(() => {});
        if (method === 'put' && state === 'error')
            return reply({
                ...page,
                props: {
                    ...page.props,
                    errors: {
                        domain: 'No s’ha pogut canviar la classificació.',
                    },
                },
            });
        if (method === 'put') {
            // Classification saved: the real router applies the page and fires the flash event.
            const product = productRows.find((row) => path.includes(row.id));
            if (product) product.stock = !product.stock;
            return reply({
                ...page,
                props: { ...page.props, products: productRows },
                flash: { toast: { type: 'success', message: flashMessage } },
            });
        }
        const component = pagesByPath[path] ?? 'Catalogue/Producers/Index';
        return reply({
            ...page,
            component,
            url: path,
            props: { ...page.props, ...pageProps[component] },
        });
    },
});
initializeFlashToast();

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
};
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
const describeActive = () => {
    const active = document.activeElement as HTMLElement | null;
    return (
        active?.getAttribute('aria-label') ??
        active?.textContent?.trim() ??
        'none'
    );
};

// Non-text contrast (3:1) is not measured by the runner's `contrast` rule, which covers text only.
// The fixture resolves each colour through a canvas (any CSS colour syntax), composites it over the
// opaque background behind the element, and records whether the 3:1 floor holds.
type Rgba = [number, number, number, number];
const canvasContext = document
    .createElement('canvas')
    .getContext('2d', { willReadFrequently: true })!;
const toRgba = (css: string): Rgba => {
    canvasContext.clearRect(0, 0, 1, 1);
    canvasContext.fillStyle = '#000';
    canvasContext.fillStyle = css;
    canvasContext.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = canvasContext.getImageData(0, 0, 1, 1).data;
    return [r, g, b, a / 255];
};
const over = (top: Rgba, bottom: Rgba): Rgba => {
    const alpha = top[3] + bottom[3] * (1 - top[3]);
    const mix = (index: number) =>
        (top[index] * top[3] + bottom[index] * bottom[3] * (1 - top[3])) /
        alpha;
    return [mix(0), mix(1), mix(2), alpha];
};
const backdropOf = (element: Element): Rgba => {
    let backdrop: Rgba = [255, 255, 255, 1];
    const chain: Element[] = [];
    // The element's own fill is not what its border, dot edge or focus ring sits against.
    for (
        let node: Element | null = element.parentElement;
        node;
        node = node.parentElement
    )
        chain.unshift(node);
    for (const node of chain) {
        const layer = toRgba(getComputedStyle(node).backgroundColor);
        if (layer[3] > 0) backdrop = over(layer, backdrop);
    }
    return backdrop;
};
const luminance = ([r, g, b]: Rgba) => {
    const [lr, lg, lb] = [r, g, b].map((channel) => {
        const unit = channel / 255;
        return unit <= 0.03928 ? unit / 12.92 : ((unit + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * lr + 0.7152 * lg + 0.0722 * lb;
};
const ratio = (first: Rgba, second: Rgba) => {
    const [high, low] = [luminance(first), luminance(second)].sort(
        (a, b) => b - a,
    );
    return (high + 0.05) / (low + 0.05);
};
// The runner presses Tab only after its assertions, and a scripted focus() does not match
// :focus-visible in an unfocused headless page. The keyboard focus outline is therefore resolved
// from the stylesheet: the last unlayered :focus-visible rule that applies to the link and sets a
// solid outline of at least 2px. An unlayered rule beats the layered Button utilities, so this is
// the outline the cascade gives a keyboard-focused link. The colour is resolved on the link itself.
const focusOutlineColour = (link: HTMLElement): string | null => {
    let colour: string | null = null;
    const visit = (rules: CSSRuleList, layered: boolean) => {
        for (const rule of Array.from(rules)) {
            const nestedLayered = layered || rule instanceof CSSLayerBlockRule;
            if (rule instanceof CSSStyleRule && !layered) {
                const applies = rule.selectorText
                    .split(',')
                    .map((part) => part.trim())
                    .filter((part) => part.includes(':focus-visible'))
                    .some((part) => {
                        const base = part.replaceAll(':focus-visible', '');
                        const target = /[\s>+~]$/.test(base)
                            ? `${base}*`
                            : base || '*';
                        try {
                            return link.matches(target);
                        } catch {
                            // A selector the engine cannot evaluate here does not apply to the link.
                            return false;
                        }
                    });
                const outline = rule.style.getPropertyValue('outline');
                const match = outline.match(/^(\d+(?:\.\d+)?)px solid (.+)$/);
                if (applies && match && parseFloat(match[1]) >= 2)
                    colour = rgbOf(link, match[2]);
            }
            if ('cssRules' in rule)
                visit(rule.cssRules as CSSRuleList, nestedLayered);
        }
    };
    for (const sheet of Array.from(document.styleSheets)) {
        try {
            visit(sheet.cssRules, false);
        } catch {
            // Cross-origin sheets cannot be read; the fixture has none.
        }
    }
    return colour;
};
// Records, per graphic class of the producer list, the weakest 3:1 ratio against the backdrop
// behind it (outline-button border, status dot, keyboard focus outline). A class with no nodes
// records "false": an empty list can never pass.
const recordGraphicContrast = () => {
    const nodes = (selector: string) => [
        ...document.querySelectorAll<HTMLElement>(selector),
    ];
    const report = (name: string, ratios: number[]) => {
        const weakest = ratios.length ? Math.min(...ratios) : 0;
        document.body.setAttribute(
            `data-ev-${name}-count`,
            String(ratios.length),
        );
        document.body.setAttribute(`data-ev-${name}-ratio`, weakest.toFixed(2));
        document.body.setAttribute(
            `data-ev-${name}-ok`,
            String(ratios.length > 0 && weakest >= 3),
        );
    };
    const against = (colour: string, node: HTMLElement) =>
        ratio(over(toRgba(colour), backdropOf(node)), backdropOf(node));
    const links = nodes('#main-content .pilot-list a');
    report(
        'border',
        links.map((link) =>
            against(getComputedStyle(link).borderTopColor, link),
        ),
    );
    report(
        'dot',
        nodes('#main-content .pilot-list .material-status-dot').map((dot) =>
            against(getComputedStyle(dot).backgroundColor, dot),
        ),
    );
    report(
        'focus',
        links.flatMap((link) => {
            const colour = focusOutlineColour(link);
            return colour ? [against(colour, link)] : [];
        }),
    );
};

// Overlay probes for the products page (spec 005 T03). The classify dialog is the NativeDialog of
// ProductAdministrationActions: it renders inside the shell DOM and is promoted to the top layer.
const dialogParam = query.get('dialog');
// Each product renders its own closed dialog; the open one is the one under measurement.
const dialogElement = () =>
    document.querySelector<HTMLDialogElement>('dialog.manager-dialog[open]') ??
    document.querySelector<HTMLDialogElement>('dialog.manager-dialog');
const dataTest = (element: Element | null) =>
    element?.getAttribute('data-test') ??
    element?.tagName.toLowerCase() ??
    'none';
// A focused button is activated by Enter or Space as a click, which is what the opener receives.
const openFrom = async (selector: string, label: string) => {
    const opener = await waitFor(() =>
        document.querySelector<HTMLElement>(selector),
    );
    opener.focus();
    opener.click();
    await waitFor(() => (dialogElement()?.open ? dialogElement() : null));
    await sleep(350);
    document.body.setAttribute('data-ev-opener', label);
};
const recordDialog = () => {
    const dialog = dialogElement();
    if (!dialog) return;
    const style = getComputedStyle(dialog);
    const buttons = [...dialog.querySelectorAll('button')].map((button) => {
        const own = getComputedStyle(button);
        return [
            own.backgroundColor,
            own.color,
            own.borderTopColor,
            own.borderTopWidth,
        ].join(' | ');
    });
    const set = (name: string, value: string) =>
        document.body.setAttribute(`data-now-dialog-${name}`, value);
    set('modal', String(dialog.open && dialog.matches(':modal')));
    set('bg', style.backgroundColor);
    set('ink', style.color);
    set('border', style.borderTopColor);
    set('buttons', buttons.join(' ; '));
    const confirm = dialog.querySelector(
        '[data-test="confirm-product-action"]',
    );
    if (confirm) {
        set('confirm-bg', getComputedStyle(confirm).backgroundColor);
        set('confirm-ink', getComputedStyle(confirm).color);
    }
};
// Non-text contrast (3:1) and keyboard focus outline for the controls of the products page and of
// its dialog. Each class records its node count; a class with no nodes can never pass.
const recordControlContrast = (scope: string, prefix: string) => {
    const nodes = (selector: string) => [
        ...document.querySelectorAll<HTMLElement>(
            selector
                .split(',')
                .map((part) => `${scope} ${part.trim()}`)
                .join(','),
        ),
    ];
    const report = (name: string, ratios: number[]) => {
        const weakest = ratios.length ? Math.min(...ratios) : 0;
        document.body.setAttribute(
            `data-ev-${prefix}-${name}-count`,
            String(ratios.length),
        );
        document.body.setAttribute(
            `data-ev-${prefix}-${name}-ratio`,
            weakest.toFixed(2),
        );
        document.body.setAttribute(
            `data-ev-${prefix}-${name}-ok`,
            String(ratios.length > 0 && weakest >= 3),
        );
    };
    const against = (colour: string, node: HTMLElement) =>
        ratio(over(toRgba(colour), backdropOf(node)), backdropOf(node));
    const outlined = nodes('[data-slot="button"]').filter(
        (button) => parseFloat(getComputedStyle(button).borderTopWidth) > 0,
    );
    report(
        'border',
        outlined.map((button) =>
            against(getComputedStyle(button).borderTopColor, button),
        ),
    );
    const toggles = nodes('input[type="checkbox"]');
    report(
        'switch',
        toggles.map((toggle) =>
            against(getComputedStyle(toggle).borderTopColor, toggle),
        ),
    );
    // The thumb is the ::before of the checkbox; it sits on the toggle's own fill.
    report(
        'thumb',
        toggles.map((toggle) => {
            const track = over(
                toRgba(getComputedStyle(toggle).backgroundColor),
                backdropOf(toggle),
            );
            return ratio(
                over(
                    toRgba(
                        getComputedStyle(toggle, '::before').backgroundColor,
                    ),
                    track,
                ),
                track,
            );
        }),
    );
    const focusable = nodes('a, button, input[type="checkbox"]').filter(
        (node) => visible(node),
    );
    report(
        'focus',
        focusable.flatMap((node) => {
            const colour = focusOutlineColour(node);
            return colour ? [against(colour, node)] : [];
        }),
    );
    document.body.setAttribute(
        `data-ev-${prefix}-focus-targets`,
        String(focusable.length),
    );
};

void createInertiaApp({
    resolve: (name) => components[`../pages/${name}.vue`].default,
    layout: (name) => {
        if (name === 'Welcome') return null;
        return state === 'variant-off' ? RevertedLayout : AppLayout;
    },
    withApp: (app, { page }) => {
        installTranslations(app, page.props.locale);
    },
    progress: false,
}).then(async () => {
    if (state === 'nav-sheet' || state === 'nav-menu') {
        // S8 first states: Producers (landing) -> Accounts (unmigrated) -> Producers again.
        const open = state === 'nav-sheet' ? openSheet : openMenu;
        await open();
        await snapshot('s1');
        await visit('/manage/accounts', 'Comptes');
        await open();
        await snapshot('s2');
        await visit('/catalogue/producers', 'Productors');
        await open();
        await snapshot('s3');
        document.body.dataset.navRequests = requestLog.join(',');
    }
    if (state === 'account-menu') await openMenu();
    if (query.get('tooltip') === '1') {
        // Collapsed icon sidebar: the pointer resting on a menu button opens its tooltip.
        const button = await waitFor(() =>
            [
                ...document.querySelectorAll<HTMLElement>(
                    '[data-sidebar="menu-button"]:not([data-test="sidebar-menu-button"])',
                ),
            ].find(visible),
        );
        button.dispatchEvent(
            new PointerEvent('pointermove', {
                pointerType: 'mouse',
                bubbles: true,
            }),
        );
        await waitFor(() =>
            document.querySelector('[data-slot="tooltip-content"]'),
        );
        await sleep(400);
    }
    if (query.get('drawer') === '1') await openSheet();
    if (
        dialogParam === 'classify' ||
        dialogParam === 'escape' ||
        dialogParam === 'confirm'
    )
        await openFrom(
            '[data-test="change-product-classification"]',
            'change-product-classification',
        );
    if (dialogParam === 'create')
        await openFrom('[data-test="open-create-cycle"]', 'open-create-cycle');
    if (dialogParam === 'delete')
        await openFrom(
            '[data-test="delete-unused-product"]',
            'delete-unused-product',
        );
    if (dialogParam === 'escape') {
        // requestClose() is the close request Escape sends: cancel, then close, then the focus return.
        dialogElement()?.requestClose();
        await waitFor(() => (dialogElement()?.open ? null : document.body));
        await sleep(150);
        document.body.setAttribute(
            'data-ev-escape-requests',
            String(requestLog.length),
        );
        document.body.setAttribute(
            'data-ev-escape-open',
            String(dialogElement()?.open ?? false),
        );
        document.body.setAttribute(
            'data-ev-escape-focus',
            dataTest(document.activeElement),
        );
    }
    if (dialogParam === 'confirm') {
        dialogElement()
            ?.querySelector<HTMLElement>('[data-test="confirm-product-action"]')
            ?.click();
        const outcome =
            {
                error: '#main-content div[role="alert"] p',
                busy: '[data-test="change-product-classification"][disabled]',
            }[state] ?? '[data-sonner-toast]';
        await waitFor(() => document.querySelector(outcome));
        await sleep(600);
        document.body.setAttribute(
            'data-ev-confirm-requests',
            requestLog.join(','),
        );
        document.body.setAttribute(
            'data-ev-confirm-open',
            String(dialogElement()?.open ?? false),
        );
        document.body.setAttribute(
            'data-ev-confirm-focus',
            dataTest(document.activeElement),
        );
    }
    recordDialog();
    await snapshot('now');
    if (
        pageName === 'Catalogue/Producers/Index' &&
        !document.querySelector(measured.sheet) &&
        !document.querySelector(measured.menu)
    )
        recordGraphicContrast();
    if (pageName === 'Catalogue/Producers/Products' && !dialogParam)
        recordControlContrast('#main-content .pilot-page', 'page');
    if (dialogElement()?.open) recordControlContrast('dialog[open]', 'dialog');
    document.body.dataset.requests = String(requestLog.length);
    document.body.dataset.active = describeActive();
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

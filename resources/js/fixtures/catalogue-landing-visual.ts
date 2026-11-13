import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import { defineComponent, h, type DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { installTranslations } from '@/lib/i18n';

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
const pageProps: Record<string, Record<string, unknown>> = {
    'Catalogue/Producers/Index': {
        producers: producersByState[state] ?? producersByState.default,
    },
    'Manage/Accounts/Index': { accounts: [], households: [] },
    Welcome: {},
};
const areas = [
    { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
    { role: 'catalogue', label: 'Catalogue', href: '/catalogue/producers' },
];
const urls: Record<string, string> = {
    'Catalogue/Producers/Index': '/catalogue/producers',
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
        sidebarOpen: true,
        ...pageProps[pageName],
    },
};

// Request spy: navigation states count every request the real router sends.
const requestLog: string[] = [];
const pagesByPath: Record<string, string> = {
    '/catalogue/producers': 'Catalogue/Producers/Index',
    '/manage/accounts': 'Manage/Accounts/Index',
};
if (state.startsWith('nav-')) {
    http.setClient({
        request: async (config: { url: string; method?: string }) => {
            const path = new URL(config.url, window.location.origin).pathname;
            requestLog.push(`${config.method ?? 'get'} ${path}`);
            const component = pagesByPath[path] ?? 'Catalogue/Producers/Index';
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    component,
                    url: path,
                    props: { ...page.props, ...pageProps[component] },
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
    if (query.get('drawer') === '1') await openSheet();
    await snapshot('now');
    if (
        !document.querySelector(measured.sheet) &&
        !document.querySelector(measured.menu)
    )
        recordGraphicContrast();
    document.body.dataset.requests = String(requestLog.length);
    document.body.dataset.active = describeActive();
    document.body.dataset.visualReady = state;
    const restore = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restore();
    window.setTimeout(restore, 100);
});

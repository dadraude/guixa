import { createApp, defineComponent, h } from 'vue';
import { layoutProps, page } from './inertia';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import Login from '@/pages/auth/Login.vue';
import ForgotPassword from '@/pages/auth/ForgotPassword.vue';
import ResetPassword from '@/pages/auth/ResetPassword.vue';
import ConfirmPassword from '@/pages/auth/ConfirmPassword.vue';
import TwoFactorChallenge from '@/pages/auth/TwoFactorChallenge.vue';
import VerifyEmail from '@/pages/auth/VerifyEmail.vue';
import AcceptInvitation from '@/pages/auth/AcceptInvitation.vue';
import Profile from '@/pages/settings/Profile.vue';
import Security from '@/pages/settings/Security.vue';
import Appearance from '@/pages/settings/Appearance.vue';
import OrderIndex from '@/pages/Orders/Index.vue';
import OrderEdit from '@/pages/Orders/Edit.vue';
import Dashboard from '@/pages/Dashboard.vue';
import ProducerCutoff from '@/pages/Producer/Cutoff.vue';
import Cycles from '@/pages/Manage/Cycles/Index.vue';
import Welcome from '@/pages/Welcome.vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import { dashboard } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { index as manageCycles } from '@/routes/manage/cycles';
import { edit as editOrder } from '@/routes/orders';
import { show as producerCutoff } from '@/routes/producer/cutoff';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { installTranslations } from '@/lib/i18n';
import { initializeTheme, updateTheme } from '@/composables/useAppearance';
import '../../resources/css/app.css';
import '../../public/theme-colors.css';

const query = new URLSearchParams(location.search);
const scenario = query.get('scenario') ?? 'login';
const flag = (name: string) => query.get(name) === '1';
const role = query.get('role') ?? 'member';
const long = flag('long');
const longText = (letter: string) => letter.repeat(59);

const appearance =
    query.get('appearance') === 'system' || query.get('mode') === 'system'
        ? 'system'
        : query.get('mode') === 'dark'
          ? 'dark'
          : 'light';
const persistence = flag('persist');
if (!persistence) localStorage.setItem('appearance', appearance);

const roleAreas: Record<string, { role: string; label: string; href: string }> =
    {
        member: { role: 'member', label: 'Member', href: '/cycles' },
        producer: {
            role: 'producer',
            label: 'Producer',
            href: '/producer/cutoff',
        },
        manager: { role: 'manager', label: 'Manager', href: '/manage/cycles' },
    };
page.props.roleAreas = roleAreas[role] ? [roleAreas[role]] : [];

const user = {
    id: 1,
    name: long ? longText('H') : 'Maria Riera',
    email: long ? `${longText('m')}@example.test` : 'maria@example.test',
    email_verified_at: flag('unverified') ? null : '2026-10-01T10:00:00Z',
};
const statusText = flag('status') ? 'verification-link-sent' : undefined;
const passkeyName = long ? longText('P') : 'MacBook Pro';
const passkeys =
    query.get('passkeys') === '0'
        ? []
        : [
              {
                  id: 1,
                  name: passkeyName,
                  authenticator: 'Touch ID',
                  created_at_diff: 'fa 2 mesos',
                  last_used_at_diff: 'fa 3 dies',
              },
              {
                  id: 2,
                  name: 'iPhone',
                  authenticator: null,
                  created_at_diff: 'fa 1 setmana',
                  last_used_at_diff: null,
              },
          ];
const manage = query.get('manage') !== '0';

const orderProduct = {
    id: 'bread',
    name: 'Pa de pagès',
    unit: 'unitat',
    price: '3.50',
    variable: false,
};
const producer = {
    producerId: 'baker',
    producerName: 'Forn de la plaça',
    open: true,
    products: [orderProduct],
};
const orderLine = (quantity: string, notDelivered = false) => ({
    productId: 'bread',
    producerId: 'baker',
    producerName: producer.producerName,
    name: long ? longText('P') : orderProduct.name,
    unit: orderProduct.unit,
    price: orderProduct.price,
    variable: false,
    quantity,
    notDelivered,
});
const pickup = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    householdId: 'home',
    householdName: '',
    status: 'pending',
    totalCents: 1050,
    balanceCents: null,
    resultingBalanceCents: null,
    lines: [
        {
            productId: 'bread',
            name: 'Pa de pagès',
            unit: 'unitat',
            variable: false,
            unitPriceCents: 350,
            ordered: '4.000',
            allocated: '4.000',
            delivered: '3.000',
            adjusted: true,
            amountCents: 1050,
        },
    ],
    stockLines: [],
    returnLines: [],
};
const baseOrder = {
    cycleId: 'cycle',
    pickupDate: '2026-10-30',
    cancelled: false,
    producers: [producer],
    lines: [] as unknown[],
    pickup: null as unknown,
};
function orderFor(state: string) {
    if (state === 'ordered')
        return { ...baseOrder, lines: [orderLine('4.000')] };
    if (state === 'marked')
        return {
            ...baseOrder,
            producers: [{ ...producer, open: false }],
            lines: [orderLine('2.000', true)],
        };
    if (state === 'closed')
        return {
            ...baseOrder,
            producers: [{ ...producer, open: false }],
            lines: [orderLine('4.000')],
        };
    if (state === 'cancelled') return { ...baseOrder, cancelled: true };
    if (state === 'pickup') return { ...baseOrder, pickup };
    return baseOrder;
}
const cycle = {
    id: 'cycle',
    pickupDate: '2026-11-07',
    cancelled: false,
    closed: false,
    participants: [
        {
            producerId: 'grower',
            producerName: 'Grower',
            participating: true,
            cutoff: '2026-11-06 20:00',
            open: true,
        },
    ],
};

type View = {
    component: string;
    view: unknown;
    props: Record<string, unknown>;
    layout?: 'auth' | 'settings' | 'app' | 'none';
};
const views: Record<string, View> = {
    login: {
        component: 'auth/Login',
        view: Login,
        props: {
            status: flag('status') ? 'Hem enviat l’enllaç.' : undefined,
            canResetPassword: query.get('reset') !== '0',
        },
        layout: 'auth',
    },
    'forgot-password': {
        component: 'auth/ForgotPassword',
        view: ForgotPassword,
        props: { status: flag('status') ? 'Hem enviat l’enllaç.' : undefined },
        layout: 'auth',
    },
    'reset-password': {
        component: 'auth/ResetPassword',
        view: ResetPassword,
        props: {
            token: 'token',
            email: 'maria@example.test',
            passwordRules: '',
        },
        layout: 'auth',
    },
    'confirm-password': {
        component: 'auth/ConfirmPassword',
        view: ConfirmPassword,
        props: {},
        layout: 'auth',
    },
    'two-factor-challenge': {
        component: 'auth/TwoFactorChallenge',
        view: TwoFactorChallenge,
        props: {},
        layout: 'auth',
    },
    'verify-email': {
        component: 'auth/VerifyEmail',
        view: VerifyEmail,
        props: { status: statusText },
        layout: 'auth',
    },
    'accept-invitation': {
        component: 'auth/AcceptInvitation',
        view: AcceptInvitation,
        props: { token: 'token', passwordRules: '' },
        layout: 'auth',
    },
    'settings-profile': {
        component: 'settings/Profile',
        view: Profile,
        props: {},
        layout: 'settings',
    },
    'settings-security': {
        component: 'settings/Security',
        view: Security,
        props: {
            passwordRules: '',
            canManageTwoFactor: manage,
            requiresConfirmation: query.get('setup') === 'confirm',
            twoFactorEnabled: flag('twofactor'),
            canManagePasskeys: manage,
            passkeys,
        },
        layout: 'settings',
    },
    'settings-appearance': {
        component: 'settings/Appearance',
        view: Appearance,
        props: {},
        layout: 'settings',
    },
    'regression-orders-index': {
        component: 'Orders/Index',
        view: OrderIndex,
        props: {
            cycles: flag('empty')
                ? []
                : [
                      {
                          cycleId: 'open',
                          pickupDate: '2026-10-30',
                          open: true,
                          cancelled: false,
                          lineCount: 2,
                      },
                      {
                          cycleId: 'closed',
                          pickupDate: '2026-10-23',
                          open: false,
                          cancelled: false,
                          lineCount: 0,
                      },
                      {
                          cycleId: 'cancelled',
                          pickupDate: '2026-10-16',
                          open: false,
                          cancelled: true,
                          lineCount: 1,
                      },
                  ],
        },
        layout: 'app',
    },
    'regression-orders-edit': {
        component: 'Orders/Edit',
        view: OrderEdit,
        props: { order: orderFor(query.get('order') ?? 'empty') },
        layout: 'app',
    },
    'regression-dashboard': {
        component: 'Dashboard',
        view: Dashboard,
        props: {},
        layout: 'app',
    },
    'regression-producer-cutoff': {
        component: 'Producer/Cutoff',
        view: ProducerCutoff,
        props: { cutoff: { daysBefore: 3, time: '20:00' } },
        layout: 'app',
    },
    'regression-manager-cycles': {
        component: 'Manage/Cycles/Index',
        view: Cycles,
        props: {
            cycles: [cycle],
            producers: [{ id: 'grower', name: 'Grower' }],
            pickupWeekday: 6,
        },
        layout: 'app',
    },
    welcome: {
        component: 'Welcome',
        view: Welcome,
        props: {},
        layout: 'none',
    },
};
const selected = views[scenario] ?? views.login;
page.component = selected.component;
// Real routes, so the selected navigation state is rendered and measured.
const routeUrls: Record<string, string> = {
    login: '/login',
    'forgot-password': '/forgot-password',
    'reset-password': '/reset-password/token',
    'confirm-password': '/user/confirm-password',
    'two-factor-challenge': '/two-factor-challenge',
    'verify-email': '/email/verify',
    'accept-invitation': '/invitations/token',
    'settings-profile': editProfile().url,
    'settings-security': editSecurity().url,
    'settings-appearance': editAppearance().url,
    'regression-orders-index': OrderController.index().url,
    'regression-orders-edit': editOrder('cycle').url,
    'regression-dashboard': dashboard().url,
    'regression-producer-cutoff': producerCutoff().url,
    'regression-manager-cycles': manageCycles().url,
    welcome: '/',
};
page.url = routeUrls[scenario] ?? '/' + scenario;
page.props.mustVerifyEmail = flag('unverified');
page.props.status = statusText;
page.props.auth.user =
    selected.layout === 'auth' || scenario === 'welcome' ? null : user;
if (selected.layout === 'app' && flag('error'))
    page.props.errors.domain = 'El valor no és vàlid.';

if (persistence) initializeTheme();
else updateTheme(appearance);
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '200%' : '100%';
if (flag('passkeyError')) {
    window.fetch = () => Promise.reject(new Error('passkey options failed'));
}

const layoutOptions = (selected.view as { layout?: Record<string, unknown> })
    .layout;
const Root = defineComponent({
    setup() {
        return () => {
            const content = () => h(selected.view as never, selected.props);
            if (selected.layout === 'auth')
                return h(
                    AuthLayout,
                    { ...layoutOptions, ...layoutProps },
                    { default: content },
                );
            if (selected.layout === 'settings')
                return h(AppLayout, null, {
                    default: () =>
                        h(SettingsLayout, null, { default: content }),
                });
            if (selected.layout === 'app')
                return h(AppLayout, null, { default: content });
            return content();
        };
    },
});

const app = createApp(Root);
app.config.globalProperties.$page = page;
installTranslations(app);
app.directive('focus', {
    mounted: (el: HTMLElement, binding) => {
        if (binding.value !== false) el.focus();
    },
});
app.mount('#app');
document.documentElement.dataset.fixtureQuery = location.search;

const click = (selector: string, text?: string): void => {
    const candidates = [...document.querySelectorAll<HTMLElement>(selector)];
    const target = text
        ? candidates.find((el) => el.textContent?.includes(text))
        : candidates[0];
    target?.click();
};
const pause = (ms: number) => new Promise((done) => setTimeout(done, ms));

async function prepareVisualState(): Promise<void> {
    if (scenario === 'regression-orders-edit' && flag('dirty')) {
        const input =
            document.querySelector<HTMLInputElement>('#quantity-bread');
        if (input) {
            input.value = '5';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    if (scenario === 'settings-profile' && flag('dialog'))
        click('[data-test="delete-user-button"]');
    if (scenario === 'settings-security') {
        if (query.get('setup')) {
            click('button', 'Enable 2FA');
            await pause(400);
            if (query.get('setup') === 'confirm')
                click('[data-slot="dialog-content"] button', 'Continue');
        }
        if (flag('dialog')) click('button[aria-label="Remove"]');
        if (flag('register')) click('button', 'Add passkey');
        if (flag('recoveryCodes')) {
            const card = [
                ...document.querySelectorAll<HTMLElement>('[data-slot="card"]'),
            ].find((element) =>
                element.textContent?.includes('2FA recovery codes'),
            );
            const viewButton = [
                ...(card?.querySelectorAll<HTMLButtonElement>('button') ?? []),
            ].find((element) =>
                element.textContent?.includes('View recovery codes'),
            );
            if (!card || !viewButton)
                throw new Error('Recovery-code card is unavailable');
            viewButton.click();
            let hideButton: HTMLButtonElement | undefined;
            let regenerateButton: HTMLButtonElement | undefined;
            let codes: HTMLElement | null = null;
            for (let attempt = 0; attempt < 50; attempt++) {
                const buttons = [
                    ...card.querySelectorAll<HTMLButtonElement>('button'),
                ];
                hideButton = buttons.find((element) =>
                    element.textContent?.includes('Hide recovery codes'),
                );
                regenerateButton = buttons.find((element) =>
                    element.textContent?.includes('Regenerate codes'),
                );
                codes = card.querySelector<HTMLElement>('.grid.font-mono');
                if (
                    hideButton &&
                    regenerateButton &&
                    codes &&
                    [
                        'aaaaa-bbbbb',
                        'ccccc-ddddd',
                        'eeeee-fffff',
                        'ggggg-hhhhh',
                    ].every((code) => codes?.textContent?.includes(code))
                )
                    break;
                await pause(20);
            }
            if (
                !hideButton ||
                !regenerateButton ||
                !codes ||
                ![
                    'aaaaa-bbbbb',
                    'ccccc-ddddd',
                    'eeeee-fffff',
                    'ggggg-hhhhh',
                ].every((code) => codes?.textContent?.includes(code))
            )
                throw new Error('Recovery codes did not expand');
            await pause(350);
            hideButton.dataset.visualRecoveryToggle = '';
            regenerateButton.dataset.visualRecoveryRegenerate = '';
            codes.dataset.visualRecoveryCodes = '';
            document.documentElement.dataset.visualRecoveryExpanded = 'true';
        }
    }
    if (flag('passkeyError')) {
        click('button[type="button"]', 'passkey');
        await pause(400);
    }
    if (query.get('recovery') === '1')
        click('button[type="button"]', 'recovery code');
    await pause(500);
    document.documentElement.dataset.visualReady = 'true';
}

requestAnimationFrame(() =>
    requestAnimationFrame(() => void prepareVisualState()),
);

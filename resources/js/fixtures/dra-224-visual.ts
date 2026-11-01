import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const role = query.get('role') ?? 'finance';
const state = query.get('state') ?? 'populated';
const fixtureUrl = window.location.href;
const fixturePath = window.location.pathname + window.location.search;
const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'dark' || mode === 'light' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const accountId = '0198f0a0-0000-7000-8000-000000000031';
const householdId = '0198f0a0-0000-7000-8000-000000000032';
const receiptId = '0198f0a0-0000-7000-8000-000000000033';
const chargeOneId = '0198f0a0-0000-7000-8000-000000000034';
const chargeTwoId = '0198f0a0-0000-7000-8000-000000000035';
const longText =
    'La Figuera de la Vall de les terres altes amb un nom extraordinàriament llarg';
const longState = state === 'long' || state.startsWith('selector-');
const householdName = longState ? longText : 'La Figuera';
const note = longState ? `${longText} ${longText}` : 'Duplicat';
const movement = (
    id: string,
    type: string,
    amount: string,
    occurredAt: string,
    movementNote: string | null,
    sourceMovementId: string | null,
    basketId: string | null,
) => ({
    id,
    type,
    amount,
    occurredAt,
    note: movementNote,
    sourceMovementId,
    basketId,
    cycleId: null,
    recordedBy: accountId,
});
const movements =
    state === 'empty' || state === 'disabled'
        ? []
        : [
              movement(
                  '0198f0a0-0000-7000-8000-000000000036',
                  'correction',
                  '-50.00',
                  '2026-10-30 11:00',
                  note,
                  receiptId,
                  null,
              ),
              movement(
                  receiptId,
                  'top_up',
                  '50.00',
                  '2026-10-30 10:00',
                  longState ? longText : 'Efectiu',
                  null,
                  null,
              ),
              movement(
                  '0198f0a0-0000-7000-8000-000000000037',
                  'charge',
                  '-18.00',
                  '2026-10-29 10:00',
                  'Cistella',
                  null,
                  chargeOneId,
              ),
              movement(
                  '0198f0a0-0000-7000-8000-000000000038',
                  'charge',
                  '-12.00',
                  '2026-10-28 10:00',
                  'Cistella',
                  null,
                  chargeTwoId,
              ),
          ];
const components = import.meta.glob('../pages/**/*.vue', {
    eager: true,
}) as Record<string, { default: DefineComponent }>;
const page = {
    component: 'Wallet/Statement',
    url: fixturePath,
    version: 'dra-224',
    props: {
        name: 'Guixa',
        locale: 'ca',
        sidebarOpen: true,
        errors:
            state === 'error' ? { source_movement_id: 'Tria un ingrés.' } : {},
        auth: {
            user: {
                id: accountId,
                name: 'Marta Soler',
                email: 'marta@example.test',
                avatar: '',
                email_verified_at: '2026-10-01T12:00:00Z',
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: [role],
        },
        roleAreas:
            role === 'finance'
                ? [{ role, label: 'Finance', href: '/finance/baskets' }]
                : role === 'member'
                  ? [{ role, label: 'Orders', href: '/' }]
                  : [{ role, label: 'Management', href: '/manage/cycles' }],
        wallet: {
            balance:
                state === 'empty' || state === 'disabled' ? '0.00' : '-30.00',
            movements,
        },
        household:
            role === 'member' ? null : { id: householdId, name: householdName },
        canManageReceipts: role !== 'member',
        ...(role === 'manager'
            ? { householdReturnHref: `/manage/households/${householdId}` }
            : {}),
        receiptAction: `/${role === 'manager' ? 'manage' : 'finance'}/households/${householdId}/wallet/top-ups`,
        correctionAction: `/${role === 'manager' ? 'manage' : 'finance'}/households/${householdId}/wallet/corrections`,
        correctionId: '0198f0a0-0000-7000-8000-000000000039',
        status: state === 'populated' ? 'Ingrés registrat.' : null,
    },
};
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
    for (let attempt = 0; attempt < 100; attempt++) {
        const main = document.querySelector<HTMLElement>('#main-content');
        if (
            main &&
            main.getClientRects().length > 0 &&
            getComputedStyle(main).pointerEvents !== 'none'
        )
            break;
        await new Promise((resolve) => window.setTimeout(resolve, 25));
    }
    if (state.startsWith('selector-')) {
        const trigger =
            document.querySelector<HTMLElement>('#correction-source');
        await document.fonts.ready;
        await new Promise((resolve) => window.setTimeout(resolve, 100));
        trigger?.scrollIntoView({ block: 'center' });
        trigger?.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'ArrowDown',
                code: 'ArrowDown',
                bubbles: true,
            }),
        );
        for (let attempt = 0; attempt < 100; attempt++) {
            if (
                document.querySelector(
                    '[data-slot="select-content"][data-state="open"]',
                )
            )
                break;
            await new Promise((resolve) => window.setTimeout(resolve, 25));
        }
        if (state === 'selector-selected') {
            document
                .querySelector<HTMLElement>('[data-slot="select-item"]')
                ?.dispatchEvent(
                    new PointerEvent('pointerup', { bubbles: true }),
                );
            for (let attempt = 0; attempt < 100; attempt++) {
                const form = trigger?.closest('form');
                if (
                    form &&
                    new FormData(form).get('source_movement_id') ===
                        receiptId &&
                    !document.querySelector('[data-slot="select-content"]')
                )
                    break;
                await new Promise((resolve) => window.setTimeout(resolve, 25));
            }
        }
    }
    const form = document
        .querySelector<HTMLElement>('#correction-source')
        ?.closest('form');
    const selectionReady =
        !state.startsWith('selector-') ||
        (state === 'selector-open'
            ? !!document.querySelector(
                  '[data-slot="select-content"][data-state="open"]',
              )
            : !!form &&
              new FormData(form).get('source_movement_id') === receiptId);
    document.body.dataset.visualReady = selectionReady
        ? state
        : 'selector-failed';
    window.history.replaceState(window.history.state, '', fixtureUrl);
});

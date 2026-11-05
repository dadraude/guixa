import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CyclesIndex from '@/pages/Manage/Cycles/Index.vue';
import ProducersIndex from '@/pages/Manage/Producers/Index.vue';
import { installTranslations } from '@/lib/i18n';

const query = new URLSearchParams(window.location.search);
const fixtureUrl = window.location.href;
const screen = query.get('screen') === 'producers' ? 'producers' : 'cycles';
const state = query.get('state');
const producerName =
    state === 'long'
        ? 'Cooperativa de pagesia agroecològica de la vall del riu i les muntanyes'
        : 'La Figuera';

const mode = query.get('mode');
localStorage.setItem(
    'appearance',
    mode === 'light' || mode === 'dark' ? mode : 'system',
);
document.documentElement.classList.toggle('dark', mode === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const page = {
    component:
        screen === 'cycles' ? 'Manage/Cycles/Index' : 'Manage/Producers/Index',
    url: screen === 'cycles' ? '/manage/cycles' : '/manage/producers',
    version: 'reference-pilot',
    props: {
        name: 'Guixa',
        locale: 'ca',
        errors: {},
        auth: {
            user: {
                id: 'manager-1',
                name: 'Cooperative manager',
                email: 'manager@example.test',
                avatar: '',
                email_verified_at: null,
                created_at: '2026-10-01T12:00:00Z',
                updated_at: '2026-10-01T12:00:00Z',
            },
            roles: ['manager'],
        },
        roleAreas: [
            { role: 'manager', label: 'Cooperative', href: '/manage/cycles' },
        ],
        sidebarOpen: true,
        ...(screen === 'cycles'
            ? {
                  cycles:
                      state === 'empty'
                          ? []
                          : [
                                {
                                    id: 'cycle-1',
                                    pickupDate: '2026-11-05',
                                    cancelled: false,
                                    closed: false,
                                    participants: [
                                        {
                                            producerId: 'producer-1',
                                            producerName,
                                            participating: true,
                                            cutoff: '2026-11-03T20:00:00+01:00',
                                            open: true,
                                        },
                                    ],
                                },
                            ],
                  producers: [{ id: 'producer-1', name: producerName }],
                  pickupWeekday: 4,
                  pendingCollections: {},
              }
            : {
                  producers:
                      state === 'empty'
                          ? []
                          : [
                                {
                                    id: 'producer-1',
                                    name: producerName,
                                    active: true,
                                    cutoffDaysBefore: 3,
                                    cutoffTime: '20:00',
                                    accountEmail: null,
                                    accountInvitationPending: false,
                                },
                            ],
              }),
    },
};

const initialPage = document.createElement('script');
initialPage.type = 'application/json';
initialPage.dataset.page = 'app';
initialPage.textContent = JSON.stringify(page);
document.body.append(initialPage);

void createInertiaApp({
    resolve: (name) =>
        // The fixture supplies the required page props; Inertia's resolver type
        // erases the page component's specific props.
        (name === 'Manage/Cycles/Index'
            ? CyclesIndex
            : ProducersIndex) as unknown as DefineComponent,
    layout: () => AppLayout,
    withApp: (app, { page }) => installTranslations(app, page.props.locale),
    progress: false,
}).then(() => {
    const restoreFixtureUrl = () =>
        window.history.replaceState(window.history.state, '', fixtureUrl);
    restoreFixtureUrl();
    window.setTimeout(restoreFixtureUrl, 100);
    window.setTimeout(restoreFixtureUrl, 500);
    if (
        ![
            'dialog',
            'drawer',
            'cycle-management',
            'producer-management',
        ].includes(state ?? '')
    )
        return;
    const openRequestedState = () => {
        const trigger = document.querySelector<HTMLButtonElement>(
            state === 'cycle-management'
                ? '[data-test="manage-cycle-1"]'
                : state === 'producer-management'
                  ? '[data-test="manage-cycle-1-producer-1"]'
                  : state === 'dialog'
                    ? '[data-test="open-create-cycle"]'
                    : 'button[data-sidebar="trigger"]',
        );
        if (!trigger) return false;
        trigger.click();
        if (state === 'cycle-management') {
            window.setTimeout(() => {
                const details = document.querySelector<HTMLDetailsElement>(
                    'dialog[open] details',
                );
                if (details) details.open = true;
            }, 50);
        }
        return true;
    };
    if (openRequestedState()) return;
    const observer = new MutationObserver(() => {
        if (openRequestedState()) observer.disconnect();
    });
    observer.observe(document.querySelector('#app')!, {
        childList: true,
        subtree: true,
    });
});

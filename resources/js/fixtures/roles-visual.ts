import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp, http } from '@inertiajs/vue3';
import { createApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import RolesIndex from '@/pages/Manage/Roles/Index.vue';

// Deterministic data for the role management screen (DRA-249). `state` picks the case:
// populated (default), results, no-results, empty, long, error (a grant rejected, R13),
// confirm (the removal dialog of a Finance holder) and confirm-self (a manager removing their own role, S3).
const query = new URLSearchParams(window.location.search);
const state = query.get('state') ?? 'populated';
const long = state === 'long' || state === 'error';
const removeFails = state === 'remove-error';
const selfConfirm = state === 'confirm-self';
const blockedReason =
    'És el darrer gestor actiu: cal que un altre compte tingui el rol de gestor i hagi acceptat la invitació.';

document.documentElement.classList.toggle('dark', query.get('mode') === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

type Holder = App.Http.Controllers.Manage.RoleHolderRow;
type Status = Guixa.Membership.Domain.AccountStatus;

const longName =
    'Administrador de la cooperativa amb un nom extraordinàriament llarg';
const longEmail =
    'eduard.membre.amb.una.adreca.extraordinariament.llarga@draude.cat';

const holder = (
    accountId: string,
    name: string,
    email: string,
    status: Status = 'active',
    removal: { isSelf?: boolean; blocked?: boolean } = {},
): Holder => ({
    accountId,
    name,
    email,
    status,
    isSelf: removal.isSelf ?? false,
    canRemove: !removal.blocked,
    removeBlockedReason: removal.blocked ? blockedReason : null,
});

const groups: App.Http.Controllers.Manage.RoleGroupRow[] =
    state === 'empty'
        ? (['manager', 'finance', 'catalogue', 'receiver'] as const).map(
              (role) => ({ role, holders: [] }),
          )
        : [
              {
                  role: 'manager',
                  holders: [
                      // Maria is the only accepted manager unless Marc has accepted too (S3).
                      holder(
                          'maria',
                          long ? longName : 'Maria',
                          long ? longEmail : 'maria@draude.cat',
                          'active',
                          { isSelf: true, blocked: !selfConfirm },
                      ),
                      ...(selfConfirm
                          ? [holder('marc', 'Marc', 'marc@draude.cat')]
                          : []),
                  ],
              },
              {
                  role: 'finance',
                  holders: [
                      holder(
                          'eva',
                          'Eva',
                          'eva@draude.cat',
                          'invitation_pending',
                      ),
                  ],
              },
              {
                  role: 'catalogue',
                  holders: [
                      holder('anna', 'Anna', 'anna@draude.cat'),
                      holder(
                          'dora',
                          long ? longName : 'Dora',
                          long ? longEmail : 'dora@draude.cat',
                          'invitation_expired',
                      ),
                  ],
              },
              {
                  role: 'receiver',
                  holders: [
                      holder(
                          'carla',
                          long ? longName : 'Carla',
                          long ? longEmail : 'carla@draude.cat',
                          'deactivated',
                      ),
                  ],
              },
          ];

const candidates: App.Http.Controllers.Manage.RoleCandidateRow[] =
    state === 'results' || state === 'error'
        ? [
              {
                  accountId: 'bernat',
                  name: long ? longName : 'Bernat',
                  email: long ? longEmail : 'bernat@draude.cat',
                  status: 'invitation_pending',
              },
              {
                  accountId: 'dora-2',
                  name: 'Dora Expirada',
                  email: 'dora.exp@draude.cat',
                  status: 'invitation_expired',
              },
              {
                  accountId: 'edu',
                  name: 'Edu',
                  email: 'edu@draude.cat',
                  status: 'active',
              },
          ]
        : [];

const page = {
    component: 'Manage/Roles/Index',
    props: { groups, candidates, errors: {} },
    url: window.location.pathname + window.location.search,
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rescuedProps: [],
    flash: {},
    rememberedState: {},
};

if (state === 'error' || removeFails) {
    http.setClient({
        request: async () => {
            return {
                status: 200,
                data: JSON.stringify({
                    ...page,
                    props: {
                        ...page.props,
                        errors: {
                            domain: removeFails
                                ? 'Aquest compte ja no té aquest rol.'
                                : 'Aquest compte ja té aquest rol.',
                        },
                    },
                }),
                headers: { 'x-inertia': 'true' },
            };
        },
    });
}

async function markReady() {
    await document.fonts.ready;

    if (state === 'confirm' || state === 'confirm-self') {
        const label =
            state === 'confirm'
                ? 'Remove Finance from Eva'
                : 'Remove Manager from Maria';
        const remove = await waitFor(() =>
            document.querySelector<HTMLButtonElement>(
                `button[aria-label="${label}"]`,
            ),
        );
        remove.click();
        await waitFor(() => document.querySelector('[role="dialog"]'));
        await new Promise((resolve) => setTimeout(resolve, 400));
    }

    if (removeFails) {
        const remove = await waitFor(() =>
            document.querySelector<HTMLButtonElement>(
                'button[aria-label="Remove Finance from Eva"]',
            ),
        );
        remove.click();
        const dialog = await waitFor(() =>
            document.querySelector('[role="dialog"]'),
        );
        [...dialog.querySelectorAll('button')]
            .find((button) => button.textContent?.trim() === 'Remove role')!
            .click();
        await waitFor(() =>
            document.body.textContent?.includes(
                'Aquest compte ja no té aquest rol.',
            ) && !document.querySelector('[role="dialog"]')
                ? document.body
                : null,
        );
    }

    if (state === 'error') {
        const grant = await waitFor(() =>
            document.querySelector<HTMLButtonElement>(
                'button[aria-label^="Grant Catalogue to"]',
            ),
        );
        grant.click();
        await waitFor(() =>
            document.body.textContent?.includes(
                'Aquest compte ja té aquest rol.',
            )
                ? document.body
                : null,
        );
    }

    document.body.dataset.visualReady = state;
}

async function waitFor<T>(find: () => T | null): Promise<T> {
    for (let attempt = 0; attempt < 100; attempt++) {
        const found = find();
        if (found) return found;
        await new Promise((resolve) => setTimeout(resolve, 50));
    }
    throw new Error('Visual fixture state never became ready.');
}

// One stable component, so the page swap after the mocked response keeps the form's errors.
const fixturePage = defineComponent({
    render: () => h(RolesIndex, { groups, candidates }),
});

void createInertiaApp({
    page,
    resolve: () => fixturePage as unknown as DefineComponent,
    layout: () => null,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
        void markReady();
    },
});

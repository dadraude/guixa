import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, defineComponent, h } from 'vue';
import AccountsIndex from '@/pages/Manage/Accounts/Index.vue';

const query = new URLSearchParams(window.location.search);
document.documentElement.classList.toggle('dark', query.get('mode') === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const long = query.has('long');
const empty = query.has('empty');
const accounts: App.Http.AccountRow[] = empty
    ? []
    : [
          {
              accountId: 'admin',
              name: long
                  ? 'Administrador de la cooperativa amb un nom extraordinàriament llarg'
                  : 'Admin',
              email: 'admin@draude.cat',
              roles: ['manager', 'finance', 'catalogue'],
              householdId: null,
              householdName: null,
              active: true,
              invitationPending: false,
          },
          {
              accountId: 'member',
              name: 'Edu',
              email: long
                  ? 'eduard.membre.amb.una.adreca.extraordinariament.llarga@draude.cat'
                  : 'edu@draude.cat',
              roles: [],
              householdId: 'casa-1',
              householdName: 'Casa 1',
              active: true,
              invitationPending: false,
          },
          {
              accountId: 'producer',
              name: 'Productor 1',
              email: 'productor@draude.cat',
              roles: ['producer', 'receiver'],
              householdId: null,
              householdName: null,
              active: false,
              invitationPending: true,
          },
      ];

const households: App.Http.HouseholdSummaryRow[] = [
    {
        id: 'casa-1',
        name: long
            ? 'Casa amb un nom molt llarg per provar la disposició dels controls'
            : 'Casa 1',
        active: true,
        memberCount: 1,
    },
];

const page = {
    component: 'Manage/Accounts/Index',
    props: {
        accounts,
        households,
        errors: {},
    },
    url: window.location.pathname + window.location.search,
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rescuedProps: [],
    flash: {},
    rememberedState: {},
};

void createInertiaApp({
    page,
    resolve: () =>
        defineComponent({
            render: () => h(AccountsIndex, { accounts, households }),
        }),
    layout: () => null,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});

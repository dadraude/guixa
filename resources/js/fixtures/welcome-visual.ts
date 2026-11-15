import '@/../css/app.css';
import '../../../public/theme-colors.css';
import { createApp } from 'vue';
import { installTranslations } from '@/lib/i18n';
import WelcomePage from '@/components/WelcomePage.vue';

const query = new URLSearchParams(window.location.search);
document.documentElement.classList.toggle('dark', query.get('mode') === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

const app = createApp(WelcomePage, {
    actionHref: query.has('authenticated') ? '/dashboard' : '/login',
});
installTranslations(app, 'ca');
app.mount('#app');

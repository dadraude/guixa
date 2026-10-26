import '@/../css/app.css';
import { createApp } from 'vue';
import SelectVisual from './SelectVisual.vue';

const query = new URLSearchParams(window.location.search);
document.documentElement.classList.toggle('dark', query.get('mode') === 'dark');
document.documentElement.style.fontSize =
    query.get('text') === '200' ? '32px' : '16px';

createApp(SelectVisual).mount('#app');

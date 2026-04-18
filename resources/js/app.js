import './bootstrap';
import 'element-plus/dist/index.css';

import ElementPlus from 'element-plus';
import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import { dashboardIcons } from './icons';
import router from './router';
import { registerDashboardTheme } from './theme';

const app = createApp(App);
const pinia = createPinia();

registerDashboardTheme();

app.use(pinia);
app.use(router);
app.use(ElementPlus, {
    size: 'default',
    zIndex: 3000,
});

for (const [name, component] of Object.entries(dashboardIcons)) {
    app.component(name, component);
}

app.mount('#app');

import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import { route } from 'ziggy-js';

const appName = 'Solar';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : `${appName} - Financas Pessoais`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
    },
});

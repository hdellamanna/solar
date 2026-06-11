import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';
import { route } from 'ziggy-js';
import VueApexCharts from 'vue3-apexcharts';

// PWA service worker — registered only in production builds. The module
// self-checks `import.meta.env.PROD` and short-circuits in dev, so the
// import is safe to keep unconditional at the entry point.
if (import.meta.env && import.meta.env.PROD) {
    import('./sw-register.js');
}

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
            .component('Apexchart', VueApexCharts)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
    },
});

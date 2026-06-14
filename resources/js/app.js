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
        const initial = props.initialPage;
        // FASE 4D — set the <html> data-motion attributes from the
        // server-resolved motion preference on the very first paint,
        // so there's no FOUC. The composable re-syncs on subsequent
        // navigations.
        try {
            if (typeof document !== 'undefined' && initial?.props?.motion) {
                const m = initial.props.motion;
                document.documentElement.setAttribute('data-motion', m.preference ?? 'auto');
                document.documentElement.setAttribute('data-motion-backdrop', m.backdrop === false ? '0' : '1');
                document.documentElement.setAttribute('data-motion-spring', m.spring === false ? '0' : '1');
                document.documentElement.setAttribute('data-motion-parallax', m.parallax === false ? '0' : '1');
            }
        } catch (e) { /* SSR safety */ }

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(ZiggyVue)
            .component('Apexchart', VueApexCharts);

        // FASE 4D — mount the AI agent slot (FASE 8 chrome placeholder)
        // at the document level so it floats across every page.
        if (typeof document !== 'undefined') {
            const slotEl = document.createElement('div');
            slotEl.id = 'ai-agent-slot-root';
            document.body.appendChild(slotEl);
            import('./Components/AiAgentSlot.vue').then(({ default: AiAgentSlot }) => {
                createApp({ render: () => h(AiAgentSlot) }).mount(slotEl);
            });
        }

        return app.mount(el);
    },
    progress: {
        color: '#f59e0b',
    },
});

import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useShortcuts(handlers = {}) {
    let pendingG = false;
    let gTimer = null;

    const isInputFocused = () => {
        const el = document.activeElement;
        if (!el) return false;
        const tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || el.isContentEditable;
    };

    const onKey = (e) => {
        if (isInputFocused()) return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;

        // g-prefix combos
        if (pendingG) {
            pendingG = false;
            clearTimeout(gTimer);
            const map = { d: 'dashboard', t: 'transactions', a: 'accounts' };
            const routeName = map[e.key];
            if (routeName && handlers[routeName]) {
                e.preventDefault();
                router.visit(route(routeName));
            }
            return;
        }
        if (e.key === 'g') {
            pendingG = true;
            gTimer = setTimeout(() => { pendingG = false; }, 800);
            return;
        }

        // simple keys
        if (e.key === 'n' && handlers.newTransaction) {
            e.preventDefault();
            handlers.newTransaction();
        } else if (e.key === '/' && handlers.search) {
            e.preventDefault();
            handlers.search();
        } else if (e.key === '?' && handlers.help) {
            e.preventDefault();
            handlers.help();
        }
    };

    onMounted(() => window.addEventListener('keydown', onKey));
    onUnmounted(() => window.removeEventListener('keydown', onKey));
}

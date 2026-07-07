import { ref, watch, onMounted } from 'vue';

const isDark = ref(true); // Default to dark — Solar's brand reads better in dark
const STORAGE_KEY = 'solar-theme';

export function useTheme() {
    const apply = (dark) => {
        if (typeof document === 'undefined') return;
        document.documentElement.classList.toggle('dark', dark);
        isDark.value = dark;
    };

    const init = (userTheme) => {
        if (typeof window === 'undefined') return;
        // 1. User's stored preference always wins (they explicitly chose)
        const stored = localStorage.getItem(STORAGE_KEY);
        let effective = stored;
        // 2. Otherwise the dark default we picked for the brand
        if (!effective) {
            effective = 'dark';
        }
        apply(effective === 'dark');
        // Persist 'dark' as the resolved choice so future reloads stay dark
        // even if localStorage was empty (first-visit default).
        if (!stored) {
            try { localStorage.setItem(STORAGE_KEY, 'dark'); } catch (e) { /* noop */ }
        }
    };

    const toggle = () => {
        const next = !isDark.value;
        apply(next);
        if (typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, next ? 'dark' : 'light');
        }
    };

    onMounted(() => init());

    return { isDark, init, toggle };
}

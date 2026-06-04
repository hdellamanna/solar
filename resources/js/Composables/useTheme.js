import { ref, watch, onMounted } from 'vue';

const isDark = ref(false);
const STORAGE_KEY = 'solar-theme';

export function useTheme() {
    const apply = (dark) => {
        if (typeof document === 'undefined') return;
        document.documentElement.classList.toggle('dark', dark);
        isDark.value = dark;
    };

    const init = (userTheme) => {
        if (typeof window === 'undefined') return;
        const stored = localStorage.getItem(STORAGE_KEY);
        let effective = userTheme;
        if (stored) effective = stored;
        if (!effective || effective === 'system') {
            effective = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        apply(effective === 'dark');
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

<script setup>
/**
 * AppFooter — the copyright + nav footer that renders on every layout.
 * Reads build version + locale from Inertia shared props (appMeta).
 *
 * The footer slides up on first scroll (one-shot per session) when
 * `motion.spring` is enabled. When reduced, it's static from the first paint.
 */
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useMotionPreference } from '@/Composables/useMotionPreference';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const { spring } = useMotionPreference();

const buildVersion = computed(() => page.props.appMeta?.build_version ?? '0.11.0');
const locale = computed(() => page.props.appMeta?.locale ?? 'pt-BR');
const year = new Date().getFullYear();

// One-shot slide-up on first scroll
const visible = ref(false);
const sessionKey = 'solar:footer-shown';

function onScroll() {
    if (visible.value) return;
    if (typeof window === 'undefined') return;
    if (window.scrollY > 80) {
        visible.value = true;
        try { window.sessionStorage.setItem(sessionKey, '1'); } catch (e) { /* noop */ }
        window.removeEventListener('scroll', onScroll);
    }
}

onMounted(() => {
    if (typeof window === 'undefined') return;
    try {
        if (window.sessionStorage.getItem(sessionKey) === '1') {
            visible.value = true;
            return;
        }
    } catch (e) { /* noop */ }
    if (spring.value) {
        window.addEventListener('scroll', onScroll, { passive: true });
    } else {
        visible.value = true;
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('scroll', onScroll);
    }
});
</script>

<template>
    <footer
        class="app-footer"
        :class="{ 'app-footer--animated': spring && visible, 'app-footer--static': !spring || visible }"
        role="contentinfo"
    >
        <div class="app-footer__inner">
            <div class="app-footer__copy">
                &copy; {{ year }} HDMA Ltda. Todos os direitos reservados.
            </div>
            <nav class="app-footer__nav" aria-label="Rodape">
                <a :href="route('about')" class="app-footer__link">Sobre</a>
                <a :href="route('tutorial')" class="app-footer__link">Tutorial</a>
                <a :href="route('about')" class="app-footer__link" title="Em breve">Privacidade</a>
            </nav>
            <div class="app-footer__meta">
                <span class="app-footer__build">v{{ buildVersion }}</span>
                <span class="app-footer__locale" aria-label="Idioma">{{ locale }}</span>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.app-footer {
    width: 100%;
    margin-top: 2.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    background: rgba(11, 15, 26, 0.55);
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.8125rem;
    opacity: 0;
    transform: translateY(8px);
    transition: opacity 200ms ease-out, transform 200ms ease-out;
    z-index: 30;
}
.app-footer--animated,
.app-footer--static {
    opacity: 1;
    transform: translateY(0);
}
.app-footer__inner {
    max-width: 80rem;
    margin: 0 auto;
    padding: 1rem 1.25rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.app-footer__copy { flex-shrink: 0; }
.app-footer__nav {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
}
.app-footer__link {
    color: rgba(255, 255, 255, 0.78);
    text-decoration: none;
    transition: color 120ms ease-out;
}
.app-footer__link:hover { color: #f59e0b; }
.app-footer__meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: rgba(255, 255, 255, 0.55);
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.75rem;
}
.app-footer__locale::before {
    content: '\2022';
    margin-right: 0.5rem;
    color: rgba(255, 255, 255, 0.3);
}
html[data-motion="reduced"] .app-footer {
    transition: none !important;
    opacity: 1 !important;
    transform: none !important;
}
</style>

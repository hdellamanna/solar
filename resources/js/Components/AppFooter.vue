<script setup>
/**
 * AppFooter — the copyright + nav footer that renders on every layout.
 * Reads build version + locale from Inertia shared props (appMeta).
 *
 * Always visible — copyright/legal info should not be hidden behind
 * a scroll trigger. Subtle fade-in only on first paint when motion
 * is enabled, otherwise static from the start.
 */
import { computed, onMounted, ref } from 'vue';
import { useMotionPreference } from '@/Composables/useMotionPreference';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const { spring } = useMotionPreference();

const buildVersion = computed(() => page.props.appMeta?.build_version ?? '0.11.0');
const locale = computed(() => page.props.appMeta?.locale ?? 'pt-BR');
const year = new Date().getFullYear();

// One-shot fade-in on mount (only if spring motion enabled).
// No scroll-trigger — copyright is always visible.
const mounted = ref(false);
onMounted(() => {
    // Defer to next frame so the transition runs on first paint.
    requestAnimationFrame(() => { mounted.value = true; });
});
</script>

<template>
    <footer
        class="app-footer"
        :class="{ 'app-footer--ready': mounted }"
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
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    background: rgba(11, 15, 26, 0.55);
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.8125rem;
    /* Always visible — no scroll-trigger */
    opacity: 1;
    z-index: 30;
    /* Subtle fade-in if motion is enabled */
    opacity: 0;
    transform: translateY(4px);
    transition: opacity 250ms ease-out, transform 250ms ease-out;
}
.app-footer--ready {
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
/* Reduced motion: no animation */
html[data-motion="reduced"] .app-footer {
    opacity: 1 !important;
    transform: none !important;
    transition: none !important;
}
</style>

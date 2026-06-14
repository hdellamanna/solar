<script setup>
/**
 * Generic chapter page wrapper. Renders a hero, intro prose, the demo, and
 * a "Ver no Solar" deep-link button. Each chapter is a thin Vue page that
 * passes its own copy and imports its own demo.
 */
import { defineProps, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    title: { type: String, required: true },
    intro: { type: String, default: '' },
    paragraphs: { type: Array, default: () => [] },
    deepLink: { type: String, default: '/dashboard' },
    deepLinkLabel: { type: String, default: 'Ver no Solar' },
    ctaLoggedIn: { type: Boolean, default: false },
});

const authed = computed(() => {
    if (typeof window === 'undefined') return false;
    return !!window?.page?.props?.auth?.user;
});
</script>

<template>
    <Head :title="title + ' - Tutorial Solar Money'" />
    <PublicLayout>
        <article class="chapter">
            <header class="chapter__hero">
                <h1 class="chapter__title">{{ title }}</h1>
            </header>
            <section class="chapter__prose">
                <p v-if="intro" class="chapter__lede">{{ intro }}</p>
                <p v-for="(p, i) in paragraphs" :key="i">{{ p }}</p>
            </section>
            <section class="chapter__demo">
                <slot name="demo" />
            </section>
            <section class="chapter__cta">
                <a
                    :href="authed ? deepLink : route('login')"
                    class="chapter__btn"
                >
                    {{ authed ? deepLinkLabel : 'Entrar e abrir' }} &rarr;
                </a>
            </section>
        </article>
    </PublicLayout>
</template>

<style scoped>
.chapter { max-width: 48rem; margin: 0 auto; }
.chapter__hero { margin-bottom: 1.5rem; }
.chapter__title {
    font-family: 'Manrope', system-ui, sans-serif;
    font-size: 2.25rem;
    font-weight: 800;
    margin: 0 0 0.5rem;
    background: linear-gradient(120deg, #f59e0b 0%, #fbbf24 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.chapter__prose { color: rgba(255, 255, 255, 0.78); line-height: 1.7; }
.chapter__prose p { margin: 0 0 1rem; }
.chapter__lede { font-size: 1.125rem; color: rgba(255, 255, 255, 0.85); }
.chapter__demo {
    margin: 2rem 0;
    padding: 1.5rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.chapter__cta { margin-top: 1.5rem; text-align: center; }
.chapter__btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    background: #f59e0b;
    color: #0b0f1a;
    font-weight: 600;
    text-decoration: none;
    transition: transform 200ms ease-out, background 200ms ease-out;
}
.chapter__btn:hover {
    background: #fbbf24;
    transform: translateY(-1px);
}
html[data-motion="reduced"] .chapter__btn { transition: none !important; }
</style>

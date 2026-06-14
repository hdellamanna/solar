<script setup>
/**
 * PublicLayout — minimal layout for unauthenticated public pages
 * (currently /about and /tutorial). No sidebar, no auth check.
 * Top bar has logo + 2 nav links (Sobre / Tutorial) + login button.
 * Renders the AppFooter at the bottom.
 */
import { Link } from '@inertiajs/vue3';
import AppFooter from '@/Components/AppFooter.vue';
</script>

<template>
    <div class="public-layout">
        <header class="public-layout__bar">
            <div class="public-layout__bar-inner">
                <Link :href="route('about')" class="public-layout__brand" aria-label="Solar Money - Sobre">
                    <span class="public-layout__brand-mark" aria-hidden="true">&#9728;</span>
                    <span class="public-layout__brand-text">Solar Money</span>
                </Link>
                <nav class="public-layout__nav" aria-label="Principal">
                    <Link :href="route('about')" class="public-layout__link">Sobre</Link>
                    <Link :href="route('tutorial')" class="public-layout__link">Tutorial</Link>
                </nav>
                <div class="public-layout__cta">
                    <Link :href="route('login')" class="public-layout__login">Entrar</Link>
                </div>
            </div>
        </header>
        <main class="public-layout__main">
            <slot />
        </main>
        <AppFooter />
    </div>
</template>

<style scoped>
.public-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, #0b0f1a 0%, #11182a 100%);
    color: rgba(255, 255, 255, 0.92);
}
.public-layout__bar {
    position: sticky;
    top: 0;
    z-index: 40;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    background: rgba(11, 15, 26, 0.6);
}
.public-layout__bar-inner {
    max-width: 80rem;
    margin: 0 auto;
    padding: 0.75rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}
.public-layout__brand {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.95);
    font-weight: 600;
}
.public-layout__brand-mark {
    color: #f59e0b;
    font-size: 1.5rem;
    line-height: 1;
}
.public-layout__nav {
    display: flex;
    gap: 1.25rem;
    margin-left: auto;
}
.public-layout__link {
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    font-size: 0.95rem;
    transition: color 120ms ease-out;
}
.public-layout__link:hover { color: #f59e0b; }
.public-layout__login {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    background: #f59e0b;
    color: #0b0f1a;
    font-weight: 600;
    text-decoration: none;
    transition: transform 120ms ease-out, background 120ms ease-out;
}
.public-layout__login:hover {
    background: #fbbf24;
    transform: translateY(-1px);
}
.public-layout__main {
    flex: 1;
    width: 100%;
    max-width: 80rem;
    margin: 0 auto;
    padding: 2.5rem 1.25rem 1rem;
}
</style>

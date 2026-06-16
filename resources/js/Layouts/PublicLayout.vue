<script setup>
/**
 * PublicLayout — minimal layout for unauthenticated public pages
 * (currently /about and /tutorial). No sidebar, no auth check.
 * Top bar has logo + 2 nav links (Sobre / Tutorial) + locale
 * switcher (3 text buttons) + login button.
 * Renders the AppFooter at the bottom.
 *
 * FASE 7 i18n: nav labels, login button, and brand text all
 * come from the `useT` composable. The locale switcher posts
 * to `settings.idioma.update` if the user is authenticated,
 * and falls back to a cookie-only path + `window.location.reload()`
 * for guests (the SetLocale middleware reads the cookie).
 */
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppFooter from '@/Components/AppFooter.vue';
import { useT } from '@/Composables/useT';
import { useLocale } from '@/Composables/useLocale';

const page = usePage();
const { t } = useT();
const { locale: activeLocale, available: availableLocales } = useLocale();

const isAuthed = computed(() => Boolean(page.props.auth?.user));

// Safe accessor for `route('settings.idioma.update')` — the
// route only exists for authed users. Returning null when the
// route is missing keeps the guest flow on the cookie+reload
// path without throwing.
function idiomaUpdateRoute() {
    try {
        return route('settings.idioma.update');
    } catch (e) {
        return null;
    }
}

function switchLocale(code) {
    if (code === activeLocale.value) return;
    const updateRoute = idiomaUpdateRoute();
    if (isAuthed.value && updateRoute) {
        // Authed: persist on the server and let the controller
        // redirect back. The next Inertia visit will see the
        // new locale.
        router.patch(updateRoute, { locale: code }, {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['app'] }),
        });
    } else {
        // Guest: write the cookie directly. The SetLocale
        // middleware reads `app_locale` on the next request,
        // so a hard reload picks it up.
        if (typeof document !== 'undefined') {
            const oneYear = 60 * 60 * 24 * 365;
            document.cookie = `app_locale=${encodeURIComponent(code)}; path=/; max-age=${oneYear}; samesite=lax`;
        }
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    }
}
</script>

<template>
    <div class="public-layout">
        <header class="public-layout__bar">
            <div class="public-layout__bar-inner">
                <Link :href="route('about')" class="public-layout__brand" :aria-label="`${t('app.brand')} - ${t('app.about')}`">
                    <span class="public-layout__brand-mark" aria-hidden="true">&#9728;</span>
                    <span class="public-layout__brand-text">{{ t('app.brand') }}</span>
                </Link>
                <nav class="public-layout__nav" aria-label="Principal">
                    <Link :href="route('about')" class="public-layout__link">{{ t('app.about') }}</Link>
                    <Link :href="route('tutorial')" class="public-layout__link">{{ t('app.tutorial') }}</Link>
                </nav>
                <div class="public-layout__locale" role="group" :aria-label="t('app.language')">
                    <button
                        v-for="loc in availableLocales"
                        :key="loc.code"
                        type="button"
                        :class="['public-layout__locale-btn', { 'public-layout__locale-btn--active': loc.code === activeLocale }]"
                        :aria-pressed="loc.code === activeLocale"
                        :title="loc.name"
                        @click="switchLocale(loc.code)"
                    >
                        {{ loc.code }}
                    </button>
                </div>
                <div class="public-layout__cta">
                    <Link :href="route('login')" class="public-layout__login">{{ t('app.login') }}</Link>
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
    flex-wrap: wrap;
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

/* Locale switcher — 3 text buttons in a tight pill row */
.public-layout__locale {
    display: inline-flex;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.public-layout__locale-btn {
    appearance: none;
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.7);
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    padding: 0.3rem 0.6rem;
    border-radius: 999px;
    cursor: pointer;
    transition: background 120ms ease-out, color 120ms ease-out;
}
.public-layout__locale-btn:hover { color: rgba(255, 255, 255, 0.95); }
.public-layout__locale-btn--active {
    background: rgba(245, 158, 11, 0.18);
    color: #fbbf24;
    font-weight: 600;
}

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

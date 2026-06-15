<script setup>
/**
 * Settings/Idioma — FASE 7 i18n tri-língue.
 *
 * 3 radio cards (pt-BR / es / en) in the same Apple-style
 * glass aesthetic as `Settings/Appearance.vue`. The card that
 * matches the user's current locale gets the `__radio--active`
 * border + tint treatment.
 *
 * Persistence: `useForm` → PATCH to `settings.idioma.update`.
 * On success the controller redirects back with a success
 * flash AND sets a 1-year `app_locale` cookie. The middleware
 * re-evaluates the locale on the next request, so we just
 * trust the redirect and let the new locale flow back through
 * the Inertia shared props.
 */
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';
import { useT } from '@/Composables/useT';

const props = defineProps({
    user: { type: Object, required: true },
    availableLocales: { type: Array, default: () => [] },
    currentLocale: { type: String, default: 'pt-BR' },
});

const { locale: activeLocale } = useLocale();
const { t } = useT();

// Initial form value prefers the controller-provided
// currentLocale (which is the user's stored preference at
// page-load time) and falls back to the live Inertia locale
// if that's missing.
const form = useForm({
    locale: props.currentLocale || activeLocale.value || 'pt-BR',
});

// 3-second toast for the success flash.
const showSuccessToast = ref(false);
let toastTimer = null;

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

watch(flashSuccess, (val) => {
    if (val) {
        showSuccessToast.value = true;
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { showSuccessToast.value = false; }, 3000);
    }
});

onUnmounted(() => { if (toastTimer) clearTimeout(toastTimer); });

// Surface the flash on initial mount too (e.g. when arriving
// from the controller redirect with `success` already in
// props).
onMounted(() => {
    if (flashSuccess.value) {
        showSuccessToast.value = true;
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { showSuccessToast.value = false; }, 3000);
    }
});

/**
 * Locales to render. The controller passes
 * `props.availableLocales` (already in the right order) — we
 * only use it if non-empty, otherwise we build a minimal
 * fallback so the page still renders in dev with stale props.
 */
const locales = computed(() => {
    if (props.availableLocales.length > 0) return props.availableLocales;
    return [
        { code: 'pt-BR', name: 'Português (Brasil)', english_name: 'Portuguese (Brazil)' },
        { code: 'es',    name: 'Español',           english_name: 'Spanish' },
        { code: 'en',    name: 'English',            english_name: 'English' },
    ];
});

const isActive = (code) => code === form.locale;

function selectLocale(code) {
    form.locale = code;
    // Clear any pre-existing validation error so the user
    // gets immediate feedback that their click took effect.
    form.clearErrors();
}

function save() {
    form.patch(route('settings.idioma.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // The controller redirected back with `success`.
            // Trigger a partial reload of the `app` shared
            // prop so all components that read `useLocale()`
            // pick up the new value without a full nav.
            router.reload({ only: ['app'] });
        },
    });
}

function cancel() {
    router.visit(route('settings.index'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${t('app.language')} - Solar Money`" />
    <AuthenticatedLayout :title="t('app.language')">
        <div class="idioma">
            <!-- Header -->
            <header class="idioma__header">
                <h1 class="idioma__h1">{{ t('app.language') }}</h1>
                <p class="idioma__lede">
                    Escolha o idioma da interface. Suas transacoes, contas e metas continuam
                    com os nomes que voce cadastrou.
                </p>
            </header>

            <!-- Success toast (3s) -->
            <transition name="toast">
                <div v-if="showSuccessToast" class="idioma__toast" role="status">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                    <span>{{ flashSuccess || t('app.language_save_success') }}</span>
                </div>
            </transition>

            <!-- Radio cards -->
            <section class="idioma__card">
                <div class="idioma__cards">
                    <label
                        v-for="loc in locales"
                        :key="loc.code"
                        :class="['idioma__radio', { 'idioma__radio--active': isActive(loc.code) }]"
                    >
                        <input
                            type="radio"
                            name="locale"
                            :value="loc.code"
                            :checked="isActive(loc.code)"
                            @change="selectLocale(loc.code)"
                        />
                        <span class="idioma__radio-code">{{ loc.code }}</span>
                        <span class="idioma__radio-name">{{ loc.name }}</span>
                        <span
                            v-if="loc.code.split('-')[0] !== 'en' && loc.english_name"
                            class="idioma__radio-sub"
                        >
                            {{ loc.english_name }}
                        </span>
                        <span v-else-if="loc.code.split('-')[0] === 'en'" class="idioma__radio-sub">
                            Default for international users
                        </span>
                    </label>
                </div>

                <p v-if="form.errors.locale" class="idioma__error">
                    {{ form.errors.locale }}
                </p>
            </section>

            <!-- Actions -->
            <div class="idioma__actions">
                <button
                    type="button"
                    class="idioma__cancel"
                    :disabled="form.processing"
                    @click="cancel"
                >
                    {{ t('app.cancel') }}
                </button>
                <button
                    type="button"
                    class="idioma__save"
                    :disabled="form.processing"
                    @click="save"
                >
                    <span v-if="form.processing">{{ t('app.saving') }}</span>
                    <span v-else>{{ t('app.save') }}</span>
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.idioma { max-width: 48rem; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; position: relative; }

/* Header */
.idioma__header { display: flex; flex-direction: column; gap: 0.375rem; }
.idioma__h1 { font-size: 1.5rem; font-weight: 600; margin: 0; letter-spacing: -0.01em; }
.idioma__lede { color: rgba(255, 255, 255, 0.65); font-size: 0.9rem; line-height: 1.5; margin: 0; }

/* Card surface */
.idioma__card {
    padding: 1.5rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* 3-card grid */
.idioma__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 0.75rem;
}

/* Radio card */
.idioma__radio {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1.25rem 1rem;
    border-radius: 0.875rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    transition: border-color 160ms ease-out, background 160ms ease-out, transform 160ms ease-out;
    position: relative;
}
.idioma__radio:hover { border-color: rgba(255, 255, 255, 0.16); }
.idioma__radio--active {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.08);
    box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.3) inset;
}
.idioma__radio input { position: absolute; opacity: 0; pointer-events: none; }

.idioma__radio-code {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.7rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.55);
    font-weight: 500;
}
.idioma__radio-name {
    font-size: 1.05rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
    letter-spacing: -0.01em;
}
.idioma__radio-sub {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.55);
    line-height: 1.35;
}

/* Validation error */
.idioma__error {
    color: #fca5a5;
    font-size: 0.825rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 0.5rem;
}

/* Actions */
.idioma__actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
.idioma__cancel {
    padding: 0.625rem 1.25rem;
    border-radius: 0.75rem;
    background: transparent;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.12);
    cursor: pointer;
    font-size: 0.95rem;
    transition: background 160ms ease-out, color 160ms ease-out;
}
.idioma__cancel:hover { background: rgba(255, 255, 255, 0.06); color: rgba(255, 255, 255, 0.95); }
.idioma__cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.idioma__save {
    padding: 0.625rem 1.25rem;
    border-radius: 0.75rem;
    background: #f59e0b;
    color: #0b0f1a;
    font-weight: 600;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
    transition: transform 160ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.idioma__save:hover { transform: translateY(-1px); }
.idioma__save:active { transform: translateY(0); }
.idioma__save:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

/* Success toast */
.idioma__toast {
    position: fixed;
    top: 1.25rem;
    right: 1.25rem;
    z-index: 80;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    border-radius: 0.75rem;
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.35);
    color: #6ee7b7;
    font-size: 0.875rem;
    font-weight: 500;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.4);
}
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px); }
.toast-enter-active, .toast-leave-active { transition: opacity 200ms ease-out, transform 200ms ease-out; }

/* Reduced motion */
html[data-motion="reduced"] .idioma__radio,
html[data-motion="reduced"] .idioma__save,
html[data-motion="reduced"] .idioma__toast {
    transition: none !important;
}
</style>

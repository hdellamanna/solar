<script setup>
/*
 * Two-Factor challenge (FASE 4D / Auth Phase 3).
 *
 * Rendered for an already-authenticated + email-verified user whose
 * `two_factor_verified` session flag is still false. The user
 * either pastes a 6-digit TOTP code from their authenticator app
 * OR one of the 10 recovery codes shown at enrollment time. A
 * "Trust this device" checkbox controls whether we mint a 90-day
 * `solar_trusted` cookie so they don't have to repeat the
 * challenge on the same browser.
 *
 * The backend's `TwoFactorChallengeController` is format-tolerant:
 * it tries TOTP first (digits only, length 6) and falls back to
 * a recovery code (anything else, 8–10 chars). The single `code`
 * field covers both paths.
 */
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    hasRecoveryCodes: { type: Boolean, default: false },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error ?? null);
const fieldError = computed(() => form.errors?.code ?? null);

const form = useForm({
    code: '',
    trust_device: true,
});

// Autofocus the 6-digit input on mount.
const codeInput = ref(null);

// "Use a recovery code" toggle — switches the input type from
// `tel` (numeric, autocomplete=one-time-code) to `text` so dashes
// and alphanumerics go through.
const useRecoveryCode = ref(false);

const submit = () => {
    form.post(route('two-factor.verify'), {
        preserveScroll: true,
        onFinish: () => {
            if (form.invalid) {
                form.reset('code');
                nextTickFocus();
            }
        },
    });
};

const nextTickFocus = () => {
    // Vanilla focus is fine here — Vue 3 will re-render the input
    // by `v-if` swap without a remount most of the time, so the
    // ref is stable.
    setTimeout(() => codeInput.value?.focus(), 0);
};

const onModeSwap = (recovery) => {
    useRecoveryCode.value = recovery;
    form.reset('code');
    nextTickFocus();
};
</script>

<template>
    <Head title="Verificacao em duas etapas · Solar Money" />
    <AuthenticatedLayout title="Verificacao em duas etapas">
        <div class="max-w-md mx-auto space-y-6">
            <!-- Shield icon -->
            <div class="flex justify-center">
                <div class="relative w-16 h-16 rounded-2xl bg-gradient-to-br from-solar-500 to-solar-600
                            grid place-items-center shadow-glow-solar
                            transition-transform duration-500 ease-spring">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2l8 4v6c0 5-3.5 9.4-8 10-4.5-.6-8-5-8-10V6l8-4z" />
                        <path d="M9 12l2 2 4-4" />
                    </svg>
                    <span class="absolute inset-0 rounded-2xl bg-solar-400/30 animate-sun-pulse"></span>
                </div>
            </div>

            <div class="text-center">
                <h1 class="font-display text-display-sm tracking-tight">Confirme o codigo 2FA</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                    Para continuar, informe o codigo de 6 digitos do seu app
                    autenticador ou um dos codigos de recuperacao.
                </p>
            </div>

            <!--
                Banners (errors). The middleware bouncer flash lands
                in `page.props.flash.error`; the per-field error
                lands in `form.errors.code`. Both surfaces coexist.
            -->
            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="flashError"
                     class="p-3.5 rounded-2xl card-glass
                            bg-rose-50/70 dark:bg-rose-500/10
                            border border-rose-200/70 dark:border-rose-500/30
                            text-sm text-rose-700 dark:text-rose-300 flex items-start gap-2.5"
                     role="alert">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <span>{{ flashError }}</span>
                </div>
            </Transition>

            <form @submit.prevent="submit" class="card p-6 space-y-5">
                <!-- Tab-style switch between TOTP and recovery code.
                     The input is the same `code` field; only the
                     type / autocomplete / placeholder differ. -->
                <div class="flex items-center p-1 rounded-xl bg-ink-100/70 dark:bg-ink-800/60">
                    <button
                        type="button"
                        @click="onModeSwap(false)"
                        :class="[
                            'flex-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                            ! useRecoveryCode
                                ? 'bg-white dark:bg-ink-900 shadow-sm text-ink-900 dark:text-ink-50'
                                : 'text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200'
                        ]"
                    >
                        Codigo do app
                    </button>
                    <button
                        v-if="hasRecoveryCodes"
                        type="button"
                        @click="onModeSwap(true)"
                        :class="[
                            'flex-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                            useRecoveryCode
                                ? 'bg-white dark:bg-ink-900 shadow-sm text-ink-900 dark:text-ink-50'
                                : 'text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200'
                        ]"
                    >
                        Codigo de recuperacao
                    </button>
                </div>

                <div>
                    <label for="code" class="block text-sm font-semibold mb-1.5">
                        {{ useRecoveryCode ? 'Codigo de recuperacao' : 'Codigo de 6 digitos' }}
                    </label>
                    <input
                        id="code"
                        ref="codeInput"
                        v-model="form.code"
                        :type="useRecoveryCode ? 'text' : 'tel'"
                        :inputmode="useRecoveryCode ? 'text' : 'numeric'"
                        :autocomplete="useRecoveryCode ? 'off' : 'one-time-code'"
                        :pattern="useRecoveryCode ? undefined : '[0-9]{6}'"
                        :maxlength="useRecoveryCode ? 12 : 6"
                        :placeholder="useRecoveryCode ? 'XXXX-XXXX-XX' : '000000'"
                        class="input-glass font-mono text-center text-lg tracking-widest"
                        autofocus
                        required
                    >
                    <p v-if="fieldError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">
                        {{ fieldError }}
                    </p>
                </div>

                <label class="flex items-center gap-2.5 text-sm cursor-pointer select-none pt-1">
                    <input
                        v-model="form.trust_device"
                        type="checkbox"
                        class="w-4 h-4 rounded text-primary-600 border-ink-300
                               focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 cursor-pointer"
                    >
                    <span class="text-ink-600 dark:text-ink-300">
                        Confiar neste dispositivo por 90 dias
                    </span>
                </label>

                <button
                    type="submit"
                    class="btn-primary w-full mt-2"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                        </svg>
                        Verificando...
                    </span>
                    <span v-else class="flex items-center gap-2">
                        Verificar
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </span>
                </button>
            </form>

            <!-- Recovery-code help footer — only when codes exist. -->
            <p v-if="hasRecoveryCodes" class="text-xs text-ink-500 dark:text-ink-400 text-center">
                Perdeu o acesso ao app?
                <button
                    type="button"
                    @click="onModeSwap(true)"
                    class="text-primary-600 hover:text-primary-700 font-semibold ml-0.5"
                >
                    Use um codigo de recuperacao
                </button>
            </p>
        </div>
    </AuthenticatedLayout>
</template>

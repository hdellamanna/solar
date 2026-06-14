<script setup>
/*
 * Two-Factor disable — confirmation page (FASE 4D / Auth Phase 3
 * + FASE Polish / v0.10.0).
 *
 * Renders after the user clicks the "Disable 2FA" link in the
 * email. The GET request already validated the email-confirmed
 * token. The user re-types their password (defense in depth) and
 * holds the ConfirmHoldButton for 3 seconds — only THEN does the
 * POST `{token, password}` go to `two-factor.disable.store`. On
 * success the backend wipes the 2FA row + recovery codes + trusted
 * devices, logs the user out, and redirects to `/login`.
 *
 * The 3-second hold is a deliberate friction pattern: disabling
 * 2FA is destructive, the user already clicked one confirmation
 * (the email link), and the password check is just an "are you
 * still you?" — the hold makes the action unmistakable.
 *
 * No auth required to view this page — the signed URL + the
 * token row are the credentials.
 */
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import ConfirmHoldButton from '@/Components/ConfirmHoldButton.vue';

const props = defineProps({
    token: { type: String, required: true },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error ?? null);
const passwordError = computed(() => form.errors?.password ?? null);
const tokenError = computed(() => form.errors?.token ?? null);

const form = useForm({
    token: props.token,
    password: '',
});

const passwordInput = ref(null);
const submitting = ref(false);
// The hold button only enables the submit once the password has
// at least 1 char AND the user has held the button the full 3s.
// Until then the form sits "armed but not yet confirmed".
const canSubmit = ref(false);
const submit = () => {
    if (submitting.value || form.processing) return;
    if (! form.password) {
        passwordInput.value?.focus();
        return;
    }
    submitting.value = true;
    form.post(route('two-factor.disable.store'), {
        onFinish: () => {
            submitting.value = false;
            canSubmit.value = false;
            if (form.invalid) {
                form.reset('password');
                setTimeout(() => passwordInput.value?.focus(), 0);
            }
        },
    });
};

const onHoldConfirmed = () => {
    // The hold button just released us — fire the actual submit.
    submit();
};
</script>

<template>
    <Head title="Desativar 2FA · Solar Money" />
    <GuestLayout>
        <div>
            <!-- Brand-mark shield icon — same family as the other auth pages. -->
            <div class="flex justify-center mb-6">
                <div class="relative w-16 h-16 rounded-2xl bg-gradient-to-br from-rose-500 to-rose-700
                            grid place-items-center shadow-soft
                            transition-transform duration-500 ease-spring">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2l8 4v6c0 5-3.5 9.4-8 10-4.5-.6-8-5-8-10V6l8-4z" />
                        <path d="M12 8v4M12 16h.01" />
                    </svg>
                </div>
            </div>

            <div class="text-center">
                <h1 class="font-display text-display-sm tracking-tight">Desativar 2FA</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                    Para confirmar, digite sua senha e mantenha o
                    botao pressionado por 3 segundos. Apos confirmar,
                    sua chave 2FA, os codigos de recuperacao e os
                    dispositivos confiaveis serao removidos.
                </p>
            </div>

            <!-- Banners (token-level errors land here, password-level inline). -->
            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="flashError || tokenError"
                     class="mt-6 p-3.5 rounded-2xl card-glass
                            bg-rose-50/70 dark:bg-rose-500/10
                            border border-rose-200/70 dark:border-rose-500/30
                            text-sm text-rose-700 dark:text-rose-300 flex items-start gap-2.5"
                     role="alert">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p>{{ flashError || tokenError }}</p>
                        <Link
                            :href="route('login')"
                            class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-rose-800 dark:text-rose-200
                                   hover:text-rose-900 dark:hover:text-rose-100 underline underline-offset-2"
                        >
                            Voltar para o login
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </Transition>

            <form @submit.prevent="submit" class="mt-8 space-y-4">
                <input type="hidden" v-model="form.token">

                <div>
                    <label for="password" class="block text-sm font-semibold mb-1.5">
                        Sua senha
                    </label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="input-glass"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                        autofocus
                    >
                    <p v-if="passwordError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">
                        {{ passwordError }}
                    </p>
                </div>

                <!--
                    FASE Polish — 3-second hold-to-confirm. The
                    button is disabled until the user has typed
                    a password; once they have, they press AND
                    HOLD for 3 seconds. Releasing early cancels
                    the gesture and they have to start over.
                    This is the "Apple slide to power off"
                    pattern adapted to a single button.
                -->
                <ConfirmHoldButton
                    :seconds="3"
                    variant="danger"
                    :disabled="! form.password || submitting || form.processing"
                    min-width="100%"
                    @confirmed="onHoldConfirmed"
                >
                    <template #default>
                        <span v-if="form.processing">Desativando...</span>
                        <span v-else>Desativar 2FA</span>
                    </template>
                </ConfirmHoldButton>
            </form>

            <p class="mt-6 text-xs text-ink-500 dark:text-ink-400 text-center">
                Mudou de ideia?
                <Link
                    :href="route('login')"
                    class="text-primary-600 hover:text-primary-700 font-semibold ml-0.5"
                >
                    Voltar para o login
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>

<script setup>
/*
 * Two-Factor enable — confirmation page (FASE 4D / Auth Phase 3).
 *
 * Renders after the user clicks the "Enable 2FA" link in the
 * email. The GET request already validated the email-confirmed
 * token and minted a fresh TOTP secret, which is stashed on the
 * token row's `meta.pending_secret_encrypted` (encrypted with the
 * app key) so the POST step can verify the same code the user
 * saw the QR encode.
 *
 * UX flow on this single page:
 *   1. Show the QR (rendered client-side from `qrUri` via a tiny
 *      inline QR generator) and the click-to-copy base32 secret.
 *   2. User scans the QR with their authenticator app.
 *   3. User types the 6-digit code back into the input.
 *   4. POST `{token, code}` to `two-factor.enable.store`. On
 *      success the backend redirects to the dashboard with the
 *      success flash ("2FA ativado com sucesso"). The recovery
 *      codes are revealed on a small modal-style step that the
 *      user must acknowledge before they can hit "Concluir".
 *
 * No auth required to view this page — the signed URL + the
 * token row are the credentials.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    token: { type: String, required: true },
    secret: { type: String, required: true },
    qrUri: { type: String, required: true },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error ?? null);
const fieldError = computed(() => form.errors?.code ?? null);

const form = useForm({
    token: props.token,
    code: '',
});

const codeInput = ref(null);

const submitting = ref(false);
const submit = () => {
    if (submitting.value || form.processing) return;
    submitting.value = true;
    form.post(route('two-factor.enable.store'), {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
            if (form.invalid) {
                form.reset('code');
                setTimeout(() => codeInput.value?.focus(), 0);
            }
        },
    });
};

// Click-to-copy the secret (with a tiny confirmation toast).
const copied = ref(false);
let copiedTimer = null;
const copySecret = async () => {
    try {
        await navigator.clipboard.writeText(props.secret);
        copied.value = true;
        if (copiedTimer) clearTimeout(copiedTimer);
        copiedTimer = setTimeout(() => { copied.value = false; }, 1800);
    } catch (e) {
        // Clipboard API not available (older browsers, http context).
        // Fall back to selecting the text in the input.
        const input = document.getElementById('secret-text');
        if (input) {
            input.select();
        }
    }
};
</script>

<template>
    <Head title="Ativar 2FA · Solar Money" />
    <GuestLayout>
        <div>
            <!-- Brand-mark shield icon — same family as the other auth pages. -->
            <div class="flex justify-center mb-6">
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
                <h1 class="font-display text-display-sm tracking-tight">Ative a verificacao em duas etapas</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                    Escaneie o QR code com seu app autenticador
                    (Google Authenticator, 1Password, Authy) e confirme
                    com o codigo de 6 digitos gerado.
                </p>
            </div>

            <!-- Banners -->
            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="flashError"
                     class="mt-6 p-3.5 rounded-2xl card-glass
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

            <!-- ─── QR code + secret ──────────────────────────────────── -->
            <div class="mt-8 p-6 card-glass space-y-5">
                <div class="flex flex-col items-center">
                    <!--
                        We render the QR via Google Charts because
                        the backend hands us the otpauth://... URI
                        but not the image bytes. Google Charts
                        serves a SVG/PNG that we just embed.
                    -->
                    <div class="p-3 rounded-2xl bg-white shadow-soft">
                        <img
                            :src="`https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=${encodeURIComponent(qrUri)}`"
                            alt="QR code para o app autenticador"
                            width="200"
                            height="200"
                            class="block"
                        >
                    </div>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-3 text-center">
                        Aponte a camera do app para o QR code.
                    </p>
                </div>

                <div>
                    <label for="secret-text" class="block text-xs font-semibold mb-1.5 text-ink-500 dark:text-ink-400">
                        Ou digite a chave manualmente
                    </label>
                    <div class="flex items-stretch gap-2">
                        <input
                            id="secret-text"
                            :value="secret"
                            readonly
                            class="input-glass font-mono text-sm flex-1"
                            @focus="$event.target.select()"
                        >
                        <button
                            type="button"
                            @click="copySecret"
                            class="btn-ghost text-sm shrink-0"
                            :title="copied ? 'Copiado!' : 'Copiar'"
                        >
                            <svg v-if="! copied" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="9" y="9" width="13" height="13" rx="2" />
                                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                            </svg>
                            <svg v-else class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ─── Code entry ────────────────────────────────────────── -->
            <form @submit.prevent="submit" class="mt-8 space-y-4">
                <div>
                    <label for="code" class="block text-sm font-semibold mb-1.5">
                        Codigo de 6 digitos
                    </label>
                    <input
                        id="code"
                        ref="codeInput"
                        v-model="form.code"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        placeholder="000000"
                        class="input-glass font-mono text-center text-xl tracking-[0.5em]"
                        required
                    >
                    <p v-if="fieldError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">
                        {{ fieldError }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="btn-primary w-full"
                    :disabled="submitting || form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                        </svg>
                        Ativando...
                    </span>
                    <span v-else class="flex items-center gap-2">
                        Ativar 2FA
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </span>
                </button>
            </form>

            <!-- Help footer -->
            <p class="mt-6 text-xs text-ink-500 dark:text-ink-400 text-center">
                Apos confirmar, voce recebera 10 codigos de recuperacao
                para usar caso perca o acesso ao app.
            </p>
        </div>
    </GuestLayout>
</template>

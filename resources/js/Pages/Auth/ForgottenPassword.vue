<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

/*
 * Auth Phase 2 (PR2) — forgot-password entry point.
 *
 * Submits the user's email to `password.email`. The backend always
 * redirects back with the SAME success flash, whether or not the email
 * matches a real user (no user enumeration). We surface that flash
 * from `page.props.flash` so the user sees consistent feedback
 * regardless of which side of the lookup they landed on.
 */
const form = useForm({ email: '' });

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const fieldError = computed(() => form.errors?.email ?? null);

const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Esqueci minha senha · Solar Money" />
    <GuestLayout>
        <div>
            <!-- Brand-mark key icon, animated — mirrors the envelope tile
                 on VerifyEmailNotice so the auth pages feel like one family. -->
            <div class="flex justify-center mb-6">
                <div class="relative w-16 h-16 rounded-2xl bg-gradient-to-br from-solar-500 to-solar-600
                            grid place-items-center shadow-glow-solar
                            transition-transform duration-500 ease-spring">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v3M12 19v3M4.93 4.93l2.12 2.12M16.95 16.95l2.12 2.12M2 12h3M19 12h3M4.93 19.07l2.12-2.12M16.95 7.05l2.12-2.12" />
                    </svg>
                    <span class="absolute inset-0 rounded-2xl bg-solar-400/30 animate-sun-pulse"></span>
                </div>
            </div>

            <div class="text-center">
                <h1 class="font-display text-display-sm tracking-tight">Esqueci minha senha</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                    Informe seu email e enviaremos um link para redefinir.
                </p>
            </div>

            <!-- Success flash (security: same copy for known + unknown emails) -->
            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="flashSuccess"
                     class="mt-6 p-3.5 rounded-2xl card-glass
                            bg-emerald-50/70 dark:bg-emerald-500/10
                            border border-emerald-200/70 dark:border-emerald-500/30
                            text-sm text-emerald-700 dark:text-emerald-300 flex items-start gap-2.5"
                     role="status">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ flashSuccess }}</span>
                </div>
            </Transition>

            <!-- Error flash (rate-limit banner from the backend) -->
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <span>{{ flashError }}</span>
                </div>
            </Transition>

            <form @submit.prevent="submit" class="mt-8 space-y-4">
                <div>
                    <label for="email" class="block text-sm font-semibold mb-1.5">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="voce@email.com"
                        class="input-glass"
                        required
                        autofocus
                    >
                    <p v-if="fieldError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">
                        {{ fieldError }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="btn-primary w-full"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                        </svg>
                        Enviando...
                    </span>
                    <span v-else class="flex items-center gap-2">
                        Enviar link de redefinição
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </span>
                </button>
            </form>

            <!-- Hint about the link TTL — mirrors the "expira em 60 minutos"
                 copy on the verification page so users know what to expect. -->
            <p class="mt-4 text-xs text-ink-500 dark:text-ink-400 text-center">
                O link expira em 60 minutos.
            </p>

            <!-- Back to login -->
            <div class="mt-8 flex items-center gap-3 text-[11px] uppercase tracking-[0.18em] text-ink-400">
                <span class="flex-1 h-px bg-ink-200/70 dark:bg-ink-800/70"></span>
                <span>Lembrou?</span>
                <span class="flex-1 h-px bg-ink-200/70 dark:bg-ink-800/70"></span>
            </div>

            <Link
                :href="route('login')"
                class="mt-5 w-full btn-ghost text-sm"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 19l-7-7 7-7" />
                </svg>
                Voltar para entrar
            </Link>
        </div>
    </GuestLayout>
</template>

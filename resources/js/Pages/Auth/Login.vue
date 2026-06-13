<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({ email: '', password: '', remember: false });
const page = usePage();
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });

// Surface a verification-needed banner whenever either:
//   (a) the form was submitted and Laravel returned a validation error
//       on the email field (e.g. via back()->withErrors() on a 302), or
//   (b) a session error was flashed before redirecting to /login and
//       landed in Inertia's shared `errors` prop.
const errorMessage = computed(() => {
    const fromForm = form.errors?.email;
    if (fromForm) return fromForm;
    // Inertia 3: page.props is reactive; in <script setup> we read
    // .errors?.email directly (the auto-unwrapped accessor).
    const props = page.props;
    return props?.errors?.email ?? null;
});
// The backend sets a verification-needed error with "Verifique seu email"
// in it — surface a "resend" link in that case. Match a few stems
// defensively to handle future backend copy variations
// ("verifique", "verifica", "verifique seu email", etc).
const isVerificationError = computed(() => {
    const msg = errorMessage.value ?? '';
    return /verif/i.test(msg);
});
</script>

<template>
    <Head title="Entrar · Solar Money" />
    <GuestLayout>
        <div>
            <h1 class="font-display text-display-sm tracking-tight">Bem-vindo de volta</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-2">
                Entre na sua conta pra ver onde o sol brilha hoje.
            </p>

            <!--
                Error banner (FASE 4D): shown when backend returns a
                form-level error on the email field. If the message is
                a verification-needed error, we also surface a
                "Reenviar email de verificação" link that takes the
                user to the verification notice page where the actual
                Resend button lives.
            -->
            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div
                    v-if="errorMessage"
                    class="mt-6 p-3.5 rounded-2xl card-glass
                           bg-rose-50/70 dark:bg-rose-500/10
                           border border-rose-200/70 dark:border-rose-500/30
                           text-sm text-rose-700 dark:text-rose-300
                           flex items-start gap-2.5"
                    role="alert"
                >
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p>{{ errorMessage }}</p>
                        <Link
                            v-if="isVerificationError"
                            :href="route('verification.notice')"
                            class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-rose-800 dark:text-rose-200
                                   hover:text-rose-900 dark:hover:text-rose-100 underline underline-offset-2"
                        >
                            Reenviar email de verificação
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
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
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-semibold">Senha</label>
                        <Link :href="route('password.request')" class="text-xs text-primary-600 hover:text-primary-700 font-medium">Esqueci</Link>
                    </div>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••"
                        class="input-glass"
                        required
                    >
                </div>
                <label class="flex items-center gap-2.5 text-sm cursor-pointer select-none pt-1">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="w-4 h-4 rounded text-primary-600 border-ink-300
                               focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 cursor-pointer"
                    >
                    <span class="text-ink-600 dark:text-ink-300">Lembrar de mim</span>
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
                        Entrando...
                    </span>
                    <span v-else class="flex items-center gap-2">
                        Entrar
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </span>
                </button>
            </form>

            <p class="mt-8 text-sm text-center text-ink-500 dark:text-ink-400">
                Ainda sem conta?
                <Link :href="route('register')" class="text-primary-600 hover:text-primary-700 font-semibold ml-1">
                    Criar agora
                </Link>
            </p>

            <!-- Demo creds hint -->
            <div class="mt-6 p-3.5 rounded-2xl text-xs text-ink-600 dark:text-ink-300
                        card-glass">
                <span class="font-semibold text-primary-600 dark:text-primary-400">Demo:</span> demo@solar.app · solar123
            </div>
        </div>
    </GuestLayout>
</template>

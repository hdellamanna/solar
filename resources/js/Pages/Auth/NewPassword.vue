<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

/*
 * Auth Phase 2 (PR2) — new-password screen.
 *
 * The user lands here from the link in the reset email, which is a
 * `URL::temporarySignedRoute('password.reset', ...)` — Inertia hands us
 * the raw token as a route param. The backend's `NewPasswordController@create`
 * will redirect to forgot-password with an error flash if the token is
 * bad, expired, or already used, so we only render the form when the
 * URL actually points at a valid token.
 */
const props = defineProps({
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    password: '',
    password_confirmation: '',
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error ?? null);
const passwordError = computed(() => form.errors?.password ?? null);
const tokenError = computed(() => form.errors?.token ?? null);

const submit = () => form.post(route('password.update'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head title="Criar nova senha · Solar Money" />
    <GuestLayout>
        <div>
            <!-- Brand-mark lock icon, animated — same sun-pulse treatment
                 as the other auth pages so the family reads as one set. -->
            <div class="flex justify-center mb-6">
                <div class="relative w-16 h-16 rounded-2xl bg-gradient-to-br from-solar-500 to-solar-600
                            grid place-items-center shadow-glow-solar
                            transition-transform duration-500 ease-spring">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="11" width="16" height="10" rx="2.5" />
                        <path d="M8 11V7a4 4 0 018 0v4" />
                        <circle cx="12" cy="16" r="1.4" fill="currentColor" />
                    </svg>
                    <span class="absolute inset-0 rounded-2xl bg-solar-400/30 animate-sun-pulse"></span>
                </div>
            </div>

            <div class="text-center">
                <h1 class="font-display text-display-sm tracking-tight">Criar nova senha</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                    Defina uma nova senha pra sua conta. Use pelo menos 8 caracteres.
                </p>
            </div>

            <!-- Token-level error (e.g. backend rejected before the request
                 was sent). Field-level password errors render inline below
                 the input — both surfaces can coexist. -->
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p>{{ flashError || tokenError }}</p>
                        <Link
                            :href="route('password.request')"
                            class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-rose-800 dark:text-rose-200
                                   hover:text-rose-900 dark:hover:text-rose-100 underline underline-offset-2"
                        >
                            Solicitar novo link
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </Transition>

            <form @submit.prevent="submit" class="mt-8 space-y-4">
                <!-- Token is bound from the route param on first render; we
                     keep it as a hidden input so the backend can re-validate
                     and so Inertia preserves it on the POST round-trip. -->
                <input type="hidden" v-model="form.token">

                <div>
                    <label for="password" class="block text-sm font-semibold mb-1.5">Nova senha</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        placeholder="Mínimo 8 caracteres"
                        class="input-glass"
                        required
                        autofocus
                        autocomplete="new-password"
                    >
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold mb-1.5">Confirmar nova senha</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        placeholder="Repita a senha"
                        class="input-glass"
                        required
                        autocomplete="new-password"
                    >
                    <p v-if="passwordError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">
                        {{ passwordError }}
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
                        Redefinindo...
                    </span>
                    <span v-else class="flex items-center gap-2">
                        Redefinir senha
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </span>
                </button>
            </form>

            <!-- Help footer — a second path back to the request screen for
                 users who arrived here from an old link or shared link. -->
            <p class="mt-6 text-xs text-ink-500 dark:text-ink-400 text-center">
                Link inválido?
                <Link
                    :href="route('password.request')"
                    class="text-primary-600 hover:text-primary-700 font-semibold ml-0.5"
                >
                    Solicitar um novo
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>

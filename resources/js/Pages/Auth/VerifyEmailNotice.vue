<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    email: { type: String, default: '' },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const resend = useForm({});
const logoutForm = useForm({});

const cooldownActive = ref(false);
let cooldownTimer = null;

const startCooldown = (seconds = 30) => {
    cooldownActive.value = true;
    if (cooldownTimer) clearTimeout(cooldownTimer);
    cooldownTimer = setTimeout(() => {
        cooldownActive.value = false;
        cooldownTimer = null;
    }, seconds * 1000);
};

const submitResend = () => {
    if (cooldownActive.value || resend.processing) return;
    resend.post(route('verification.resend'), {
        preserveScroll: true,
        onSuccess: () => startCooldown(30),
    });
};

const logout = () => logoutForm.post(route('logout'));
</script>

<template>
    <Head title="Confirme seu email · Solar Money" />
    <GuestLayout>
        <div>
            <!-- Brand-mark envelope icon, animated -->
            <div class="flex justify-center mb-6">
                <div class="relative w-16 h-16 rounded-2xl bg-gradient-to-br from-solar-500 to-solar-600
                            grid place-items-center shadow-glow-solar
                            transition-transform duration-500 ease-spring">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="3" />
                        <path d="M3 7l9 6 9-6" />
                    </svg>
                    <!-- Pulse ring -->
                    <span class="absolute inset-0 rounded-2xl bg-solar-400/30 animate-sun-pulse"></span>
                </div>
            </div>

            <div class="text-center">
                <h1 class="font-display text-display-sm tracking-tight">Confirme seu email</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                    Enviamos um link de verificação para:
                </p>
                <p class="text-sm mt-1.5 font-mono font-semibold text-ink-900 dark:text-ink-50 px-3 py-1.5
                          rounded-lg bg-ink-100/70 dark:bg-ink-800/60
                          inline-block max-w-full truncate" :title="email">
                    {{ email }}
                </p>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                    Clique no link para ativar sua conta.
                </p>
            </div>

            <!-- Flash messages -->
            <Transition
                enter-active-class="transition duration-300 ease-spring" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="flashSuccess"
                     class="mt-6 p-3.5 rounded-2xl card-glass
                            bg-emerald-50/70 dark:bg-emerald-500/10
                            border border-emerald-200/70 dark:border-emerald-500/30
                            text-sm text-emerald-700 dark:text-emerald-300 flex items-start gap-2.5">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ flashSuccess }}</span>
                </div>
            </Transition>

            <Transition
                enter-active-class="transition duration-300 ease-spring" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="flashError"
                     class="mt-6 p-3.5 rounded-2xl card-glass
                            bg-rose-50/70 dark:bg-rose-500/10
                            border border-rose-200/70 dark:border-rose-500/30
                            text-sm text-rose-700 dark:text-rose-300 flex items-start gap-2.5">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <span>{{ flashError }}</span>
                </div>
            </Transition>

            <!-- Resend button -->
            <form @submit.prevent="submitResend" class="mt-8">
                <button
                    type="submit"
                    class="btn-primary w-full"
                    :disabled="resend.processing || cooldownActive"
                >
                    <span v-if="resend.processing" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                        </svg>
                        Enviando...
                    </span>
                    <span v-else-if="cooldownActive" class="flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path stroke-linecap="round" d="M12 6v6l4 2" />
                        </svg>
                        Aguarde 30s para reenviar
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Reenviar email
                    </span>
                </button>
                <p class="mt-3 text-xs text-ink-500 dark:text-ink-400 text-center">
                    O link expira em 60 minutos.
                </p>
            </form>

            <!-- Divider -->
            <div class="mt-8 flex items-center gap-3 text-[11px] uppercase tracking-[0.18em] text-ink-400">
                <span class="flex-1 h-px bg-ink-200/70 dark:bg-ink-800/70"></span>
                <span>Não é você?</span>
                <span class="flex-1 h-px bg-ink-200/70 dark:bg-ink-800/70"></span>
            </div>

            <button
                type="button"
                @click="logout"
                class="mt-5 w-full btn-ghost text-sm"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Sair da conta
            </button>
        </div>
    </GuestLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Entrar · Solar Money" />
    <GuestLayout>
        <div>
            <h1 class="font-display text-display-sm tracking-tight">Bem-vindo de volta</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-2">
                Entre na sua conta pra ver onde o sol brilha hoje.
            </p>

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
                        <a href="#" class="text-xs text-primary-600 hover:text-primary-700 font-medium">Esqueci</a>
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
                <div v-if="form.errors.email" class="text-sm text-expense">{{ form.errors.email }}</div>
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

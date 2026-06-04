<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Entrar" />
    <GuestLayout>
        <div class="card p-6 md:p-8 space-y-5">
            <div>
                <h1 class="text-2xl font-bold">Entrar no Solar</h1>
                <p class="text-sm text-slate-500 mt-1">Acesse sua conta para continuar.</p>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input v-model="form.email" type="email" placeholder="voce@email.com" class="input" required autofocus>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Senha</label>
                    <input v-model="form.password" type="password" placeholder="••••••••" class="input" required>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.remember" type="checkbox" class="rounded text-brand-500 focus:ring-brand-500">
                    Lembrar de mim
                </label>
                <div v-if="form.errors.email" class="text-sm text-expense">{{ form.errors.email }}</div>
                <button class="btn-primary w-full" :disabled="form.processing">
                    <span v-if="form.processing">Entrando...</span>
                    <span v-else>Entrar</span>
                </button>
            </form>
            <p class="text-sm text-center text-slate-500">
                Sem conta?
                <Link :href="route('register')" class="text-brand-600 hover:underline font-medium">Criar agora</Link>
            </p>
        </div>
    </GuestLayout>
</template>

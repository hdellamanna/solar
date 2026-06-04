<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Entrar" />
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="card p-8 w-full max-w-sm space-y-5">
            <h1 class="text-2xl font-bold">Entrar no Solar</h1>
            <form @submit.prevent="submit" class="space-y-3">
                <input v-model="form.email" type="email" placeholder="Email" class="input" required>
                <input v-model="form.password" type="password" placeholder="Senha" class="input" required>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.remember" type="checkbox" class="rounded">
                    Lembrar de mim
                </label>
                <button class="btn-primary w-full" :disabled="form.processing">Entrar</button>
            </form>
            <p class="text-sm text-center text-slate-500">
                Sem conta? <Link href="/register" class="text-brand-600 hover:underline">Criar agora</Link>
            </p>
        </div>
    </div>
</template>

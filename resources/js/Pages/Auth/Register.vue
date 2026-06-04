<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
const submit = () => form.post('/register', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Criar conta" />
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="card p-8 w-full max-w-sm space-y-5">
            <h1 class="text-2xl font-bold">Criar conta no Solar</h1>
            <form @submit.prevent="submit" class="space-y-3">
                <input v-model="form.name" type="text" placeholder="Nome" class="input" required>
                <input v-model="form.email" type="email" placeholder="Email" class="input" required>
                <input v-model="form.password" type="password" placeholder="Senha" class="input" required>
                <input v-model="form.password_confirmation" type="password" placeholder="Confirmar senha" class="input" required>
                <button class="btn-primary w-full" :disabled="form.processing">Criar conta</button>
            </form>
            <p class="text-sm text-center text-slate-500">
                Ja tem conta? <Link href="/login" class="text-brand-600 hover:underline">Entrar</Link>
            </p>
        </div>
    </div>
</template>

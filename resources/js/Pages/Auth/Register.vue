<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
const submit = () => form.post(route('register'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Criar conta" />
    <GuestLayout>
        <div class="card p-6 md:p-8 space-y-5">
            <div>
                <h1 class="text-2xl font-bold">Criar conta</h1>
                <p class="text-sm text-slate-500 mt-1">Comece a controlar suas finanças hoje.</p>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Nome</label>
                    <input v-model="form.name" type="text" placeholder="Seu nome" class="input" required autofocus>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input v-model="form.email" type="email" placeholder="voce@email.com" class="input" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Senha</label>
                    <input v-model="form.password" type="password" placeholder="Mínimo 8 caracteres" class="input" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Confirmar senha</label>
                    <input v-model="form.password_confirmation" type="password" placeholder="Repita a senha" class="input" required>
                </div>
                <div v-if="form.errors.email" class="text-sm text-expense">{{ form.errors.email }}</div>
                <div v-if="form.errors.password" class="text-sm text-expense">{{ form.errors.password }}</div>
                <button class="btn-primary w-full" :disabled="form.processing">
                    <span v-if="form.processing">Criando...</span>
                    <span v-else>Criar conta</span>
                </button>
            </form>
            <p class="text-sm text-center text-slate-500">
                Já tem conta?
                <Link :href="route('login')" class="text-brand-600 hover:underline font-medium">Entrar</Link>
            </p>
        </div>
    </GuestLayout>
</template>

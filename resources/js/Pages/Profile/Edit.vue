<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', email: '', theme: 'light' });
const submit = () => form.patch(route('profile.update'));
</script>

<template>
    <Head title="Perfil" />
    <AuthenticatedLayout title="Perfil">
        <form @submit.prevent="submit" class="card p-6 max-w-lg space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Nome</label>
                <input v-model="form.name" type="text" class="input">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input v-model="form.email" type="email" class="input">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Tema preferido</label>
                <select v-model="form.theme" class="input">
                    <option value="light">Claro</option>
                    <option value="dark">Escuro</option>
                    <option value="system">Sistema</option>
                </select>
            </div>
            <button class="btn-primary">Salvar</button>
        </form>
    </AuthenticatedLayout>
</template>

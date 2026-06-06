<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    periods: { type: Object, default: () => ({}) },
    colors: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    category_id: props.categories[0]?.id || '',
    amount: '',
    period: 'monthly',
    starts_at: new Date().toISOString().split('T')[0],
    ends_at: '',
    alert_threshold: 80,
    color: '#10b981',
    icon: '🎯',
});

const icons = ['🎯', '🛒', '🍔', '🚗', '🏠', '🎬', '💊', '📚', '✈️', '💼', '💰', '🎮'];

const submit = () => form.post(route('budgets.store'));
</script>

<template>
    <Head title="Novo orçamento" />
    <AuthenticatedLayout title="Novo orçamento">
        <div class="max-w-xl">
            <form @submit.prevent="submit" class="card p-5 md:p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nome</label>
                    <input v-model="form.name" type="text" placeholder="Ex: Mercado, Uber, Netflix" class="input" required>
                    <div v-if="form.errors.name" class="text-sm text-expense mt-1">{{ form.errors.name }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Categoria</label>
                    <select v-model="form.category_id" class="input" required>
                        <option v-for="c in props.categories" :key="c.id" :value="c.id">
                            {{ c.icon || '📦' }} {{ c.name }}
                        </option>
                    </select>
                    <div v-if="form.errors.category_id" class="text-sm text-expense mt-1">{{ form.errors.category_id }}</div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Valor (R$)</label>
                        <input v-model="form.amount" type="number" step="0.01" min="0.01" placeholder="0,00" class="input" required>
                        <div v-if="form.errors.amount" class="text-sm text-expense mt-1">{{ form.errors.amount }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Período</label>
                        <select v-model="form.period" class="input" required>
                            <option v-for="(label, key) in props.periods" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Início</label>
                        <input v-model="form.starts_at" type="date" class="input" required>
                        <div v-if="form.errors.starts_at" class="text-sm text-expense mt-1">{{ form.errors.starts_at }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Término (opcional)</label>
                        <input v-model="form.ends_at" type="date" class="input">
                        <div v-if="form.errors.ends_at" class="text-sm text-expense mt-1">{{ form.errors.ends_at }}</div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Limite de alerta: {{ form.alert_threshold }}%</label>
                    <input v-model.number="form.alert_threshold" type="range" min="1" max="100" class="w-full">
                    <p class="text-xs text-slate-500 mt-1">Você será alertado quando atingir esse percentual.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Cor</label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="c in props.colors" :key="c" type="button" @click="form.color = c"
                                :class="['w-8 h-8 rounded-full border-2 transition-transform', form.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']"
                                :style="{ backgroundColor: c }"></button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Ícone</label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="i in icons" :key="i" type="button" @click="form.icon = i"
                                :class="['w-9 h-9 rounded-lg border-2 text-lg flex items-center justify-center transition-transform', form.icon === i ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30 scale-110' : 'border-slate-200 dark:border-slate-700']">
                            {{ i }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Criar orçamento</button>
                    <Link :href="route('budgets.index')" class="btn-ghost">Cancelar</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

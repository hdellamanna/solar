<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';

const props = defineProps({
    budget: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    periods: { type: Object, default: () => ({}) },
    colors: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.budget.name,
    category_id: props.budget.category?.id || '',
    amount: props.budget.amount_decimal,
    period: props.budget.period,
    starts_at: props.budget.starts_at,
    ends_at: props.budget.ends_at || '',
    alert_threshold: props.budget.alert_threshold,
    color: props.budget.color || '#10b981',
    icon: props.budget.icon || '🎯',
});

const icons = ['🎯', '🛒', '🍔', '🚗', '🏠', '🎬', '💊', '📚', '✈️', '💼', '💰', '🎮'];

const submit = () => form.put(route('budgets.update', props.budget.id));
</script>

<template>
    <Head title="Editar orçamento" />
    <AuthenticatedLayout title="Editar orçamento">
        <div class="max-w-xl">
            <div v-if="props.budget" class="card p-4 mb-4 flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: props.budget.color || '#f59e0b' }"></span>
                    <span class="text-slate-600 dark:text-slate-300">Gasto atual no período:</span>
                    <span class="font-semibold">{{ formatCents(props.budget.spent_cents) }}</span>
                </div>
                <span class="text-xs text-slate-500">Status: {{ props.budget.status }}</span>
            </div>

            <form @submit.prevent="submit" class="card p-5 md:p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nome</label>
                    <input v-model="form.name" type="text" class="input" required>
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
                        <input v-model="form.amount" type="number" step="0.01" min="0.01" class="input" required>
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
                    <button type="submit" class="btn-primary" :disabled="form.processing">Salvar alterações</button>
                    <Link :href="route('budgets.index')" class="btn-ghost">Cancelar</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

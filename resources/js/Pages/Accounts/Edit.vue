<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    account: { type: Object, default: null },
});

const form = useForm({
    name: props.account?.name || '',
    type: props.account?.type || 'checking',
    currency: props.account?.currency || 'BRL',
    color: props.account?.color || '#f59e0b',
    initial_balance_cents: props.account ? props.account.initial_balance_cents / 100 : 0,
    icon: props.account?.icon || '',
});

const colors = ['#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

const submit = () => {
    if (props.account) {
        form.put(route('accounts.update', props.account.id));
    } else {
        form.post(route('accounts.store'));
    }
};
</script>

<template>
    <Head :title="props.account ? 'Editar conta' : 'Nova conta'" />
    <AuthenticatedLayout :title="props.account ? 'Editar conta' : 'Nova conta'">
        <div class="max-w-lg">
            <form @submit.prevent="submit" class="card p-5 md:p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nome</label>
                    <input v-model="form.name" type="text" placeholder="Ex: Nubank, Itaú, Carteira" class="input" required>
                    <div v-if="form.errors.name" class="text-sm text-expense mt-1">{{ form.errors.name }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tipo</label>
                    <select v-model="form.type" class="input" required>
                        <option value="checking">Conta corrente</option>
                        <option value="savings">Poupança</option>
                        <option value="credit_card">Cartão de crédito</option>
                        <option value="cash">Dinheiro</option>
                        <option value="investment">Investimento</option>
                        <option value="crypto">Cripto</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Cor</label>
                    <div class="flex gap-2">
                        <button v-for="c in colors" :key="c" type="button" @click="form.color = c" :class="['w-8 h-8 rounded-full border-2 transition-transform', form.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']" :style="{ backgroundColor: c }"></button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Saldo inicial (R$)</label>
                    <input v-model.number="form.initial_balance_cents" type="number" step="0.01" min="0" class="input">
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Salvar</button>
                    <Link :href="route('accounts.index')" class="btn-ghost">Cancelar</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

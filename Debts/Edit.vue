<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    debt: { type: Object, required: true },
    strategies: { type: Array, default: () => [] },
});

const form = useForm({
    creditor: props.debt.creditor,
    description: props.debt.description ?? '',
    total_balance: props.debt.total_balance_decimal,
    interest_rate: props.debt.interest_rate_percent,
    monthly_payment: props.debt.monthly_payment_decimal,
    start_date: props.debt.start_date,
    payoff_strategy: props.debt.payoff_strategy,
    currency: props.debt.currency,
    notes: props.debt.notes ?? '',
});

const submit = () => {
    form.put(route('debts.update', props.debt.id));
};

const remove = () => {
    if (confirm(`Remover a dívida com "${props.debt.creditor}"?`)) {
        router.delete(route('debts.destroy', props.debt.id));
    }
};
</script>

<template>
    <Head :title="`Editar: ${props.debt.creditor}`" />
    <AuthenticatedLayout :title="`Editar: ${props.debt.creditor}`">
        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="card-elevated p-6 md:p-8 space-y-6">
                <!-- Strategy picker -->
                <div>
                    <label class="block text-sm font-medium mb-3">Sistema de amortização</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button
                            v-for="s in props.strategies"
                            :key="s.id"
                            type="button"
                            @click="form.payoff_strategy = s.id"
                            :class="[
                                'p-4 rounded-2xl border text-left transition-all duration-200 ease-out',
                                form.payoff_strategy === s.id
                                    ? 'border-slate-900 dark:border-white bg-slate-50 dark:bg-slate-800/50'
                                    : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50',
                            ]"
                        >
                            <p class="font-semibold tracking-tight" :class="s.id === 'price' ? 'text-violet-600 dark:text-violet-300' : 'text-blue-600 dark:text-blue-300'">{{ s.label }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ s.description }}</p>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Credor</label>
                        <input v-model="form.creditor" type="text" maxlength="80" class="input" required />
                        <p v-if="form.errors.creditor" class="text-xs text-expense mt-1">{{ form.errors.creditor }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Descrição (opcional)</label>
                        <input v-model="form.description" type="text" maxlength="120" class="input" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Saldo total</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">R$</span>
                            <input v-model="form.total_balance" type="number" step="0.01" min="0.01" class="input pl-10 tabular-nums text-lg font-semibold" required />
                        </div>
                        <p v-if="form.errors.total_balance" class="text-xs text-expense mt-1">{{ form.errors.total_balance }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Parcela mensal</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">R$</span>
                            <input v-model="form.monthly_payment" type="number" step="0.01" min="0.01" class="input pl-10 tabular-nums text-lg font-semibold" required />
                        </div>
                        <p v-if="form.errors.monthly_payment" class="text-xs text-expense mt-1">{{ form.errors.monthly_payment }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Taxa de juros anual</label>
                        <div class="relative">
                            <input v-model="form.interest_rate" type="number" step="0.0001" min="0" class="input pr-12 tabular-nums" required />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">% a.a.</span>
                        </div>
                        <p v-if="form.errors.interest_rate" class="text-xs text-expense mt-1">{{ form.errors.interest_rate }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Data de início</label>
                        <input v-model="form.start_date" type="date" class="input" required />
                        <p v-if="form.errors.start_date" class="text-xs text-expense mt-1">{{ form.errors.start_date }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Moeda</label>
                    <input v-model="form.currency" type="text" maxlength="3" class="input w-32 uppercase" required />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Notas (opcional)</label>
                    <textarea v-model="form.notes" rows="2" maxlength="2000" class="input resize-none"></textarea>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="remove" class="text-sm text-expense hover:underline">Remover dívida</button>
                    <div class="flex items-center gap-3 ml-auto">
                        <Link :href="route('debts.index')" class="btn-ghost">Voltar</Link>
                        <button type="submit" :disabled="form.processing" class="btn-primary">
                            {{ form.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    strategies: { type: Array, default: () => [] },
});

const form = useForm({
    creditor: '',
    description: '',
    total_balance: '',
    interest_rate: '',
    monthly_payment: '',
    start_date: new Date().toISOString().slice(0, 10),
    payoff_strategy: 'sac',
    currency: 'BRL',
    notes: '',
});

const submit = () => {
    form.post(route('debts.store'));
};
</script>

<template>
    <Head title="Nova dívida" />
    <AuthenticatedLayout title="Nova dívida">
        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="card-elevated p-6 md:p-8 space-y-6">
                <!-- Strategy picker (segmented control) -->
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
                    <p v-if="form.errors.payoff_strategy" class="text-xs text-expense mt-1">{{ form.errors.payoff_strategy }}</p>
                </div>

                <!-- Creditor + description -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Credor</label>
                        <input
                            v-model="form.creditor"
                            type="text"
                            maxlength="80"
                            placeholder="Ex: Banco do Brasil, Itaú, Nubank..."
                            class="input"
                            :class="form.errors.creditor ? 'border-expense' : ''"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.creditor" class="text-xs text-expense mt-1">{{ form.errors.creditor }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Descrição (opcional)</label>
                        <input
                            v-model="form.description"
                            type="text"
                            maxlength="120"
                            placeholder="Financiamento do carro, cartão..."
                            class="input"
                        />
                    </div>
                </div>

                <!-- Balance + monthly payment -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Saldo total</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">R$</span>
                            <input
                                v-model="form.total_balance"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0,00"
                                class="input pl-10 tabular-nums text-lg font-semibold"
                                :class="form.errors.total_balance ? 'border-expense' : ''"
                                required
                            />
                        </div>
                        <p v-if="form.errors.total_balance" class="text-xs text-expense mt-1">{{ form.errors.total_balance }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Parcela mensal</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">R$</span>
                            <input
                                v-model="form.monthly_payment"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0,00"
                                class="input pl-10 tabular-nums text-lg font-semibold"
                                :class="form.errors.monthly_payment ? 'border-expense' : ''"
                                required
                            />
                        </div>
                        <p v-if="form.errors.monthly_payment" class="text-xs text-expense mt-1">{{ form.errors.monthly_payment }}</p>
                    </div>
                </div>

                <!-- Interest rate + start date -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Taxa de juros anual</label>
                        <div class="relative">
                            <input
                                v-model="form.interest_rate"
                                type="number"
                                step="0.0001"
                                min="0"
                                placeholder="0,0000"
                                class="input pr-12 tabular-nums"
                                :class="form.errors.interest_rate ? 'border-expense' : ''"
                                required
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">% a.a.</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Ex: 12,5 para 12.50% ao ano. Use 0 para sem juros.</p>
                        <p v-if="form.errors.interest_rate" class="text-xs text-expense mt-1">{{ form.errors.interest_rate }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Data de início</label>
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="input"
                            :class="form.errors.start_date ? 'border-expense' : ''"
                            required
                        />
                        <p v-if="form.errors.start_date" class="text-xs text-expense mt-1">{{ form.errors.start_date }}</p>
                    </div>
                </div>

                <!-- Currency -->
                <div>
                    <label class="block text-sm font-medium mb-2">Moeda</label>
                    <input
                        v-model="form.currency"
                        type="text"
                        maxlength="3"
                        placeholder="BRL"
                        class="input w-32 uppercase"
                        :class="form.errors.currency ? 'border-expense' : ''"
                        required
                    />
                    <p class="text-xs text-slate-500 mt-1">Código ISO-4217 de 3 letras (BRL, USD, EUR...).</p>
                    <p v-if="form.errors.currency" class="text-xs text-expense mt-1">{{ form.errors.currency }}</p>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium mb-2">Notas (opcional)</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        maxlength="2000"
                        placeholder="Contrato 12345, refinanciado em 2026..."
                        class="input resize-none"
                    ></textarea>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Link :href="route('debts.index')" class="btn-ghost">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Salvando...' : 'Cadastrar' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

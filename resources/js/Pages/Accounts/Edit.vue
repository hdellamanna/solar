<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    account: { type: Object, required: true },
    types: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: props.account.name,
    type: props.account.type,
    currency: props.account.currency || 'BRL',
    color: props.account.color || '#f59e0b',
    initial_balance_cents: props.account.initial_balance_cents / 100,
    icon: props.account.icon || '',
    balances: (props.account.balances || []).map(b => ({
        currency: b.currency,
        balance_cents: b.balance_cents / 100,
    })),
});

const colors = ['#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
const popularCurrencies = ['BRL', 'USD', 'EUR', 'GBP'];

const isMulti = computed(() => form.type === 'multi_currency');

watch(isMulti, (now) => {
    if (now && form.balances.length === 0) {
        form.balances = [{ currency: 'USD', balance_cents: 0 }];
    }
    if (!now) {
        form.balances = [];
    }
});

const addBalance = () => form.balances.push({ currency: 'USD', balance_cents: 0 });
const removeBalance = (i) => form.balances.splice(i, 1);

const submit = () => {
    const payload = { ...form.data() };
    if (isMulti.value) {
        payload.balances = form.balances.map(b => ({
            currency: b.currency,
            balance_cents: Math.round(Number(b.balance_cents) * 100),
        }));
    } else {
        delete payload.balances;
    }
    form.put(route('accounts.update', props.account.id), { data: payload });
};
</script>

<template>
    <Head :title="`Editar: ${props.account.name}`" />
    <AuthenticatedLayout :title="props.account.name">
        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="card-elevated p-6 md:p-8 space-y-5">
                <div>
                    <label class="block text-sm font-medium mb-2">Nome</label>
                    <input v-model="form.name" type="text" class="input" :class="form.errors.name ? 'border-expense' : ''" required>
                    <p v-if="form.errors.name" class="text-xs text-expense mt-1.5">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Tipo</label>
                        <select v-model="form.type" class="input" required>
                            <option v-for="(label, value) in props.types" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Cor</label>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <button v-for="c in colors" :key="c" type="button" @click="form.color = c" :class="['w-7 h-7 rounded-full border-2 transition-all duration-200', form.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']" :style="{ backgroundColor: c }"></button>
                        </div>
                    </div>
                </div>

                <div v-if="!isMulti">
                    <label class="block text-sm font-medium mb-2">Moeda principal</label>
                    <select v-model="form.currency" class="input">
                        <option v-for="c in popularCurrencies" :key="c" :value="c">{{ c }}</option>
                    </select>
                    <label class="block text-sm font-medium mt-4 mb-2">Saldo inicial ({{ form.currency }})</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">{{ form.currency }}</span>
                        <input v-model.number="form.initial_balance_cents" type="number" step="0.01" min="0" class="input pl-14 tabular-nums">
                    </div>
                </div>

                <div v-else>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium">Moeda principal + sub-saldos</label>
                        <button type="button" @click="addBalance" class="text-xs text-brand-600 hover:underline">+ Adicionar moeda</button>
                    </div>
                    <p class="text-xs text-slate-500 mb-3">Multi-moeda (Wise, Nomad, C6 Global, Inter Global): cadastre a moeda principal e adicione os saldos em outras moedas que você mantém nessa conta.</p>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <select v-model="form.currency" class="input flex-1">
                                <option v-for="c in popularCurrencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <div class="relative flex-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">{{ form.currency }}</span>
                                <input v-model.number="form.initial_balance_cents" type="number" step="0.01" min="0" class="input pl-14 tabular-nums" placeholder="Saldo principal">
                            </div>
                        </div>
                        <div v-for="(b, i) in form.balances" :key="i" class="flex items-center gap-2">
                            <select v-model="b.currency" class="input flex-1">
                                <option v-for="c in popularCurrencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <div class="relative flex-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">{{ b.currency }}</span>
                                <input v-model.number="b.balance_cents" type="number" step="0.01" class="input pl-14 tabular-nums" placeholder="Saldo">
                            </div>
                            <button type="button" @click="removeBalance(i)" class="p-2 text-slate-500 hover:text-expense" title="Remover">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <Link :href="route('accounts.index')" class="btn-ghost">Voltar</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Salvando...' : 'Salvar alterações' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

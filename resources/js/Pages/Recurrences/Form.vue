<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    recurrence: { type: Object, default: null },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

// Real-world form uses "amount" in reais; we send amount_cents to the API.
const amountReais = ref(
    props.recurrence ? String((props.recurrence.amount_cents / 100).toFixed(2)).replace('.', ',') : ''
);

const form = useForm({
    description: props.recurrence?.description || '',
    amount_cents: props.recurrence?.amount_cents ?? null,
    type: props.recurrence?.type || 'expense',
    frequency: props.recurrence?.frequency || 'monthly',
    account_id: props.recurrence?.account_id || (props.accounts[0]?.id ?? ''),
    category_id: props.recurrence?.category_id || '',
    starts_at: props.recurrence?.starts_at || new Date().toISOString().split('T')[0],
    ends_at: props.recurrence?.ends_at || '',
    active: props.recurrence?.active ?? true,
});

watch(amountReais, (val) => {
    // Strip everything that isn't digit/comma/dot.
    const cleaned = String(val).replace(/[^\d.,]/g, '').replace(/\./g, '').replace(',', '.');
    const num = parseFloat(cleaned);
    form.amount_cents = Number.isFinite(num) ? Math.round(num * 100) : null;
});

const filteredCategories = computed(() => {
    return props.categories.filter(c => c.type === form.type);
});

watch(() => form.type, () => {
    form.category_id = '';
});

const frequencies = [
    { value: 'daily', label: 'Diária' },
    { value: 'weekly', label: 'Semanal' },
    { value: 'monthly', label: 'Mensal' },
    { value: 'yearly', label: 'Anual' },
];

const types = [
    { value: 'expense', label: 'Despesa', emoji: '⬇️' },
    { value: 'income', label: 'Receita', emoji: '⬆️' },
    { value: 'transfer', label: 'Transf.', emoji: '↔️' },
];

const submit = () => {
    if (props.recurrence) {
        form.put(route('recurrences.update', props.recurrence.id));
    } else {
        form.post(route('recurrences.store'));
    }
};
</script>

<template>
    <Head :title="props.recurrence ? 'Editar recorrência' : 'Nova recorrência'" />
    <AuthenticatedLayout :title="props.recurrence ? 'Editar recorrência' : 'Nova recorrência'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('recurrences.index')" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-200">
                    ← Recorrências
                </Link>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-4">
            <div class="card p-5 md:p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Tipo</label>
                    <div class="grid grid-cols-3 gap-2">
                        <label v-for="t in types" :key="t.value"
                            :class="['cursor-pointer border-2 rounded-xl p-3 text-center transition-colors',
                                form.type === t.value
                                    ? (t.value === 'expense' ? 'border-expense bg-red-50 dark:bg-red-900/20'
                                      : t.value === 'income' ? 'border-income bg-green-50 dark:bg-green-900/20'
                                      : 'border-info bg-blue-50 dark:bg-blue-900/20')
                                    : 'border-slate-200 dark:border-slate-700']">
                            <input v-model="form.type" type="radio" :value="t.value" class="sr-only">
                            <span class="text-2xl">{{ t.emoji }}</span>
                            <p class="text-sm font-medium mt-1">{{ t.label }}</p>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Descrição</label>
                    <input v-model="form.description" type="text" placeholder="Ex: Aluguel, Netflix, Salário"
                        class="input" required>
                    <p v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Valor (R$)</label>
                        <input v-model="amountReais" type="text" inputmode="decimal"
                            placeholder="0,00" class="input text-lg font-semibold" required>
                        <p v-if="form.errors.amount_cents" class="text-xs text-red-500 mt-1">{{ form.errors.amount_cents }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Frequência</label>
                        <select v-model="form.frequency" class="input" required>
                            <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Conta</label>
                        <select v-model="form.account_id" class="input" required>
                            <option value="" disabled>Selecione…</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Categoria</label>
                        <select v-model="form.category_id" class="input">
                            <option value="">Sem categoria</option>
                            <option v-for="c in filteredCategories" :key="c.id" :value="c.id">
                                {{ c.icon }} {{ c.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Início</label>
                        <input v-model="form.starts_at" type="date" class="input" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Término (opcional)</label>
                        <input v-model="form.ends_at" type="date" class="input">
                        <p v-if="form.errors.ends_at" class="text-xs text-red-500 mt-1">{{ form.errors.ends_at }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="form.active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm">Recorrência ativa</span>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2 justify-end">
                <Link :href="route('recurrences.index')" class="btn-secondary">Cancelar</Link>
                <button type="submit" :disabled="form.processing" class="btn-primary">
                    <span v-if="form.processing">Salvando…</span>
                    <span v-else>{{ props.recurrence ? 'Salvar alterações' : 'Criar recorrência' }}</span>
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

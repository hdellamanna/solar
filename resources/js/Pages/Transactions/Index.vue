<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';
import { ref, computed } from 'vue';

const props = defineProps({
    transactions: { type: Object, default: () => ({ data: [], links: [] }) },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const accountId = ref(props.filters.account_id || '');
const categoryId = ref(props.filters.category_id || '');
const type = ref(props.filters.type || '');

const applyFilters = () => {
    router.get(route('transactions.index'), {
        search: search.value || undefined,
        account_id: accountId.value || undefined,
        category_id: categoryId.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true });
};

const destroy = (tx) => {
    if (confirm(`Excluir transação "${tx.description}"?`)) {
        router.delete(route('transactions.destroy', tx.id));
    }
};
</script>

<template>
    <Head title="Transações" />
    <AuthenticatedLayout title="Transações">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
            <p class="text-sm text-slate-500">{{ props.transactions.data?.length || 0 }} transações</p>
            <Link :href="route('transactions.create')" class="btn-primary">+ Nova transação</Link>
        </div>

        <div class="card p-3 md:p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-2">
            <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Buscar descrição..." class="input md:col-span-2">
            <select v-model="accountId" @change="applyFilters" class="input">
                <option value="">Todas as contas</option>
                <option v-for="a in props.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
            <select v-model="categoryId" @change="applyFilters" class="input">
                <option value="">Todas as categorias</option>
                <option v-for="c in props.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="type" @change="applyFilters" class="input">
                <option value="">Todos os tipos</option>
                <option value="income">Receitas</option>
                <option value="expense">Despesas</option>
                <option value="transfer">Transferências</option>
            </select>
        </div>

        <div v-if="props.transactions.data?.length === 0" class="card p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            <h3 class="font-semibold mb-1">Nenhuma transação encontrada</h3>
            <p class="text-sm text-slate-500 mb-4">Crie uma transação ou ajuste os filtros.</p>
            <Link :href="route('transactions.create')" class="btn-primary inline-flex">+ Nova transação</Link>
        </div>

        <div v-else class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Descrição</th>
                            <th class="px-4 py-3 hidden md:table-cell">Categoria</th>
                            <th class="px-4 py-3 hidden md:table-cell">Conta</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="tx in props.transactions.data" :key="tx.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ formatDate(tx.date) }}</td>
                            <td class="px-4 py-3 font-medium">
                                {{ tx.description }}
                                <span v-if="tx.is_pix" class="ml-1 text-xs px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">PIX</span>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-slate-500">
                                <span v-if="tx.category">{{ tx.category.icon }} {{ tx.category.name }}</span>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-slate-500">{{ tx.account?.name }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums whitespace-nowrap" :class="tx.type === 'income' ? 'text-income' : 'text-expense'">
                                {{ tx.type === 'income' ? '+' : '-' }}{{ formatCents(Math.abs(tx.amount_cents)) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link :href="route('transactions.edit', tx.id)" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </Link>
                                <button @click="destroy(tx)" class="ml-2 text-slate-400 hover:text-expense">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';
import { useTransactionFilters } from '@/Composables/useTransactionFilters';
import { ref, computed } from 'vue';

const props = defineProps({
    transactions: { type: Object, default: () => ({ data: [], links: [] }) },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    periodPresets: { type: Object, default: () => ({}) },
});

const {
    state,
    hasActiveFilters,
    apply,
    clear,
    toggleAccount,
    toggleCategory,
} = useTransactionFilters(props.filters);

const accountMenuOpen = ref(false);
const categoryMenuOpen = ref(false);

const periodOptions = computed(() => ([
    { value: '', label: 'Todos os períodos' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Mês passado' },
    { value: 'last_3_months', label: 'Últimos 3 meses' },
    { value: 'last_6_months', label: 'Últimos 6 meses' },
    { value: 'this_year', label: 'Este ano' },
    { value: 'custom', label: 'Personalizado' },
]));

const accountsLabel = computed(() => {
    if (!state.account_ids.length) return 'Todas as contas';
    if (state.account_ids.length === 1) {
        const a = props.accounts.find(x => x.id === state.account_ids[0]);
        return a?.name ?? '1 conta';
    }
    return `${state.account_ids.length} contas`;
});

const categoriesLabel = computed(() => {
    if (!state.category_ids.length) return 'Todas as categorias';
    if (state.category_ids.length === 1) {
        const c = props.categories.find(x => x.id === state.category_ids[0]);
        return c?.name ?? '1 categoria';
    }
    return `${state.category_ids.length} categorias`;
});

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
            <p class="text-sm text-slate-500">
                {{ props.transactions.total ?? props.transactions.data?.length ?? 0 }} transações
                <span v-if="hasActiveFilters" class="ml-2 text-xs px-2 py-0.5 rounded bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                    Filtros ativos
                </span>
            </p>
            <Link :href="route('transactions.create')" class="btn-primary">+ Nova transação</Link>
        </div>

        <!-- Filters card -->
        <div class="card p-3 md:p-4 mb-4 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                <input v-model="state.search" @keyup.enter="apply" type="text" placeholder="Buscar descrição ou notas..." class="input md:col-span-2">

                <select v-model="state.period" @change="apply" class="input">
                    <option v-for="o in periodOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>

                <select v-model="state.type" @change="apply" class="input">
                    <option value="">Todos os tipos</option>
                    <option value="income">Receitas</option>
                    <option value="expense">Despesas</option>
                    <option value="transfer">Transferências</option>
                </select>

                <select v-model="state.status" @change="apply" class="input">
                    <option value="">Todos os status</option>
                    <option value="paid">Pago</option>
                    <option value="pending">Pendente</option>
                </select>
            </div>

            <!-- Custom period dates -->
            <div v-if="state.period === 'custom'" class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <input v-model="state.from" @change="apply" type="date" class="input" placeholder="De">
                <input v-model="state.to" @change="apply" type="date" class="input" placeholder="Até">
                <div></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                <!-- Multi-select account -->
                <div class="relative">
                    <button type="button" @click="accountMenuOpen = !accountMenuOpen; categoryMenuOpen = false" class="input w-full text-left flex items-center justify-between">
                        <span class="truncate">{{ accountsLabel }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-if="accountMenuOpen" class="absolute z-20 mt-1 w-full card p-2 max-h-60 overflow-y-auto">
                        <button type="button" v-for="a in props.accounts" :key="a.id" @click="toggleAccount(a.id)" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-sm">
                            <input type="checkbox" :checked="state.account_ids.includes(a.id)" class="rounded">
                            <span class="truncate">{{ a.name }}</span>
                        </button>
                        <div v-if="!props.accounts.length" class="text-xs text-slate-500 px-2 py-1">Nenhuma conta.</div>
                        <div class="border-t border-slate-100 dark:border-slate-800 mt-1 pt-1 flex gap-2">
                            <button type="button" @click="state.account_ids = []; accountMenuOpen = false" class="text-xs text-slate-500 hover:underline">Limpar</button>
                            <button type="button" @click="accountMenuOpen = false; apply" class="text-xs text-brand-600 hover:underline ml-auto">Aplicar</button>
                        </div>
                    </div>
                </div>

                <!-- Multi-select category -->
                <div class="relative">
                    <button type="button" @click="categoryMenuOpen = !categoryMenuOpen; accountMenuOpen = false" class="input w-full text-left flex items-center justify-between">
                        <span class="truncate">{{ categoriesLabel }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-if="categoryMenuOpen" class="absolute z-20 mt-1 w-full card p-2 max-h-60 overflow-y-auto">
                        <button type="button" v-for="c in props.categories" :key="c.id" @click="toggleCategory(c.id)" class="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-sm">
                            <input type="checkbox" :checked="state.category_ids.includes(c.id)" class="rounded">
                            <span class="truncate">{{ c.icon }} {{ c.name }}</span>
                        </button>
                        <div v-if="!props.categories.length" class="text-xs text-slate-500 px-2 py-1">Nenhuma categoria.</div>
                        <div class="border-t border-slate-100 dark:border-slate-800 mt-1 pt-1 flex gap-2">
                            <button type="button" @click="state.category_ids = []; categoryMenuOpen = false" class="text-xs text-slate-500 hover:underline">Limpar</button>
                            <button type="button" @click="categoryMenuOpen = false; apply" class="text-xs text-brand-600 hover:underline ml-auto">Aplicar</button>
                        </div>
                    </div>
                </div>

                <input v-model="state.amount_min" @change="apply" type="number" step="0.01" min="0" placeholder="Valor mín. (R$)" class="input">
                <input v-model="state.amount_max" @change="apply" type="number" step="0.01" min="0" placeholder="Valor máx. (R$)" class="input">
            </div>

            <div class="flex items-center gap-2">
                <button @click="apply" class="btn-primary text-sm">Aplicar filtros</button>
                <button v-if="hasActiveFilters" @click="clear" class="btn-secondary text-sm">Limpar filtros</button>
                <span v-if="hasActiveFilters" class="text-xs text-slate-500">Os filtros são salvos na URL — você pode compartilhar!</span>
            </div>
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
                                <Link :href="route('transactions.show', tx.id)" class="hover:underline">{{ tx.description }}</Link>
                                <span v-if="tx.is_pix" class="ml-1 text-xs px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">PIX</span>
                                <span v-if="tx.splits_count" class="ml-1 text-xs px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Dividida</span>
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

            <!-- Pagination -->
            <div v-if="props.transactions.links?.length > 3" class="flex flex-wrap items-center justify-center gap-1 p-3 border-t border-slate-200 dark:border-slate-800">
                <template v-for="(link, i) in props.transactions.links" :key="i">
                    <Link v-if="link.url" :href="link.url" :class="['px-3 py-1.5 text-sm rounded', link.active ? 'bg-brand-500 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800']" v-html="link.label" />
                    <span v-else class="px-3 py-1.5 text-sm text-slate-300" v-html="link.label" />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

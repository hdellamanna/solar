<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';
import { useLineConfig, useDarkMode } from '@/Composables/useChart';
import { computed } from 'vue';

const props = defineProps({
    totalBalanceCents: Number,
    monthInflowCents: Number,
    monthOutflowCents: Number,
    monthSavingsCents: Number,
    recentTransactions: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    monthlyFlow: { type: Array, default: () => [] },
    goals: { type: Array, default: () => [] },
});

const isDark = useDarkMode();

// Cash-flow chart datasets (Receitas / Despesas / Saldo).
const flowCategories = computed(() => props.monthlyFlow.map((m) => m.month));
const flowSeries = computed(() => [
    { name: 'Receitas', data: props.monthlyFlow.map((m) => m.income) },
    { name: 'Despesas', data: props.monthlyFlow.map((m) => Math.abs(m.expense)) },
    { name: 'Saldo',    data: props.monthlyFlow.map((m) => m.net) },
]);
const flowOptions = computed(() => useLineConfig({
    categories: flowCategories.value,
    series: flowSeries.value,
    isDark,
    height: 256,
    colors: ['#10b981', '#ef4444', '#3b82f6'],
}));
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout title="Dashboard">
        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6">
            <div class="card p-4 md:p-5">
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Saldo total</p>
                <p class="text-xl md:text-2xl font-bold mt-1" :class="props.totalBalanceCents >= 0 ? 'text-slate-900 dark:text-slate-100' : 'text-expense'">
                    {{ formatCents(props.totalBalanceCents) }}
                </p>
                <p class="text-xs text-slate-400 mt-1">{{ props.accounts.length }} {{ props.accounts.length === 1 ? 'conta' : 'contas' }}</p>
            </div>
            <div class="card p-4 md:p-5">
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Receitas do mês</p>
                <p class="text-xl md:text-2xl font-bold mt-1 text-income">{{ formatCents(props.monthInflowCents) }}</p>
            </div>
            <div class="card p-4 md:p-5">
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Despesas do mês</p>
                <p class="text-xl md:text-2xl font-bold mt-1 text-expense">{{ formatCents(props.monthOutflowCents) }}</p>
            </div>
            <div class="card p-4 md:p-5">
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400">Economia</p>
                <p class="text-xl md:text-2xl font-bold mt-1" :class="props.monthSavingsCents >= 0 ? 'text-income' : 'text-expense'">
                    {{ formatCents(props.monthSavingsCents) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
            <!-- Cash-flow chart -->
            <div class="card p-5 lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold">Fluxo de caixa</h2>
                    <span class="text-xs text-slate-400">Últimos 6 meses</span>
                </div>
                <Apexchart
                    v-if="props.monthlyFlow.length"
                    type="line"
                    height="256"
                    :options="flowOptions"
                    :series="flowSeries"
                />
                <div v-else class="h-64 flex items-center justify-center text-sm text-slate-400">
                    Sem transações no período
                </div>
            </div>

            <!-- Goals widget (FASE 4A) -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Metas em andamento</h2>
                    <Link :href="route('goals.index')" class="text-xs text-brand-600 hover:underline">Ver todas</Link>
                </div>
                <div v-if="props.goals.length === 0" class="text-center py-4 text-sm text-slate-400">
                    Nenhuma meta ativa.
                    <Link :href="route('goals.create')" class="block mt-1 text-brand-600 hover:underline">Criar meta</Link>
                </div>
                <div v-else class="space-y-3">
                    <Link
                        v-for="g in props.goals"
                        :key="g.id"
                        :href="route('goals.edit', g.id)"
                        class="block group"
                    >
                        <div class="flex items-center gap-2 mb-1">
                            <span
                                class="w-6 h-6 rounded-md flex items-center justify-center text-sm shrink-0"
                                :style="{ backgroundColor: (g.color || '#f59e0b') + '20', color: g.color || '#f59e0b' }"
                            >{{ g.icon || '🎯' }}</span>
                            <span class="text-sm font-medium flex-1 truncate">{{ g.name }}</span>
                            <span class="text-xs text-slate-500 tabular-nums">{{ g.progress_percent }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden ml-8">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{ width: g.progress_percent + '%', backgroundColor: g.color || '#f59e0b' }"
                            ></div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mt-4 md:mt-6">
            <!-- Accounts list -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Minhas contas</h2>
                    <Link :href="route('accounts.create')" class="text-xs text-brand-600 hover:underline">+ Nova</Link>
                </div>
                <div v-if="props.accounts.length === 0" class="text-center py-6 text-sm text-slate-400">
                    Nenhuma conta ainda.
                    <Link :href="route('accounts.create')" class="block mt-2 text-brand-600 hover:underline">Criar primeira conta</Link>
                </div>
                <ul v-else class="space-y-2">
                    <li v-for="acc in props.accounts.slice(0, 5)" :key="acc.id" class="flex items-center justify-between">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: acc.color || '#f59e0b' }"></span>
                            <span class="text-sm truncate">{{ acc.name }}</span>
                        </div>
                        <span class="text-sm font-medium tabular-nums" :class="acc.balance_cents >= 0 ? '' : 'text-expense'">{{ formatCents(acc.balance_cents) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Recent transactions -->
        <div class="card p-5 mt-4 md:mt-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold">Transações recentes</h2>
                <Link :href="route('transactions.index')" class="text-xs text-brand-600 hover:underline">Ver todas</Link>
            </div>
            <div v-if="props.recentTransactions.length === 0" class="text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <p class="text-sm text-slate-500 mb-1">Nenhuma transação ainda</p>
                <p class="text-xs text-slate-400 mb-3">Lance sua primeira transação para começar.</p>
                <Link :href="route('transactions.create')" class="btn-primary">+ Nova transação</Link>
            </div>
            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="tx in props.recentTransactions" :key="tx.id" class="py-2.5 flex items-center gap-3">
                    <span class="text-lg">{{ tx.category?.icon || (tx.type === 'income' ? '⬆️' : '⬇️') }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ tx.description }}</p>
                        <p class="text-xs text-slate-500">{{ tx.account?.name }} • {{ formatDate(tx.date) }}</p>
                    </div>
                    <span class="text-sm font-semibold tabular-nums" :class="tx.type === 'income' ? 'text-income' : 'text-expense'">
                        {{ tx.type === 'income' ? '+' : '-' }}{{ formatCents(Math.abs(tx.amount_cents)) }}
                    </span>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>

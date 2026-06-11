<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';
import { useLineConfig, useDarkMode } from '@/Composables/useChart';
import PwaInstallBanner from '@/Components/PwaInstallBanner.vue';
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
    subscriptions: { type: Object, default: () => ({}) },
    investmentsSummary: { type: Object, default: () => null },
    debts: { type: Object, default: () => ({}) },
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

        <!-- Subscriptions widget (FASE 4B) -->
        <div v-if="(props.subscriptions?.active_count ?? 0) > 0" class="card-elevated p-5 md:p-6 mt-4 md:mt-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold tracking-tight">Assinaturas</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ formatCents(props.subscriptions.total_monthly_cents) }} / mês em {{ props.subscriptions.active_count }} assinatura{{ props.subscriptions.active_count === 1 ? '' : 's' }}
                    </p>
                </div>
                <Link :href="route('subscriptions.index')" class="text-xs text-brand-600 hover:underline">Ver todas</Link>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Link
                    v-for="s in props.subscriptions.upcoming"
                    :key="s.id"
                    :href="route('subscriptions.edit', s.id)"
                    class="flex items-center gap-3 p-3 rounded-xl border border-slate-200/60 dark:border-slate-800/60 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group"
                >
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 transition-transform duration-200 ease-out group-hover:scale-105"
                        :style="{ backgroundColor: (s.color || '#ef4444') + '1a', color: s.color || '#ef4444' }"
                    >{{ s.icon || '📺' }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ s.name }}</p>
                        <p class="text-xs text-slate-500">
                            <span v-if="s.days_until_billing === 0" class="text-expense font-semibold">Cobra hoje</span>
                            <span v-else-if="s.days_until_billing === 1" class="text-amber-600 font-medium">Cobra amanhã</span>
                            <span v-else>Em {{ s.days_until_billing }} dias</span>
                        </p>
                    </div>
                    <p class="text-sm font-semibold tabular-nums">{{ s.amount_formatted }}</p>
                </Link>
            </div>
        </div>

        <!-- Investments widget (FASE 5) -->
        <div v-if="props.investmentsSummary" class="card-elevated p-5 md:p-6 mt-4 md:mt-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold tracking-tight">Investimentos</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ props.investmentsSummary.count }} posição{{ props.investmentsSummary.count === 1 ? '' : 'es' }} · valor atual {{ formatCents(props.investmentsSummary.current_value_cents) }}
                    </p>
                </div>
                <Link :href="route('investments.index')" class="text-xs text-brand-600 hover:underline">Ver todos</Link>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                    <p class="text-xs text-slate-500">Total investido</p>
                    <p class="text-base font-semibold tabular-nums mt-0.5">{{ formatCents(props.investmentsSummary.total_invested_cents) }}</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                    <p class="text-xs text-slate-500">Valor atual</p>
                    <p class="text-base font-semibold tabular-nums mt-0.5">{{ formatCents(props.investmentsSummary.current_value_cents) }}</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                    <p class="text-xs text-slate-500">P&amp;L</p>
                    <p
                        class="text-base font-semibold tabular-nums mt-0.5"
                        :class="props.investmentsSummary.profit_loss_cents >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                    >
                        {{ props.investmentsSummary.profit_loss_cents >= 0 ? '+' : '' }}{{ formatCents(props.investmentsSummary.profit_loss_cents) }}
                        <span v-if="props.investmentsSummary.profit_loss_percent !== null" class="text-xs ml-1">
                            ({{ props.investmentsSummary.profit_loss_percent >= 0 ? '+' : '' }}{{ props.investmentsSummary.profit_loss_percent.toFixed(2) }}%)
                        </span>
                    </p>
                </div>
            </div>
            <div v-if="props.investmentsSummary.by_type.length > 0" class="flex flex-wrap gap-2">
                <span
                    v-for="t in props.investmentsSummary.by_type"
                    :key="t.type"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
                    :style="{ backgroundColor: (t.color || '#64748b') + '1a', color: t.color || '#64748b' }"
                >
                    {{ t.label }} · {{ formatCents(t.total_cents) }}
                </span>
            </div>
        </div>

        <!-- Debts widget (FASE 5) -->
        <div v-if="(props.debts?.count_active ?? 0) > 0" class="card-elevated p-5 md:p-6 mt-4 md:mt-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold tracking-tight">Dívidas</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ formatCents(props.debts.total_balance_cents) }} em {{ props.debts.count_active }} dívida{{ props.debts.count_active === 1 ? '' : 's' }} ativa{{ props.debts.count_active === 1 ? '' : 's' }}
                        · {{ formatCents(props.debts.monthly_commitment_cents) }} / mês
                    </p>
                </div>
                <Link :href="route('debts.index')" class="text-xs text-brand-600 hover:underline">Ver todas</Link>
            </div>
            <div v-if="props.debts.top && props.debts.top.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Link
                    v-for="d in props.debts.top"
                    :key="d.id"
                    :href="route('debts.show', d.id)"
                    class="flex items-center gap-3 p-3 rounded-xl border border-slate-200/60 dark:border-slate-800/60 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group"
                >
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 transition-transform duration-200 ease-out group-hover:scale-105"
                        :style="{ backgroundColor: '#ef44441a', color: '#ef4444' }"
                    >💳</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ d.creditor }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ d.description || 'Sem descrição' }}</p>
                    </div>
                    <p class="text-sm font-semibold tabular-nums">{{ formatCents(d.total_balance_cents) }}</p>
                </Link>
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

        <!-- PWA install prompt — non-blocking, dismissible for 30 days -->
        <PwaInstallBanner />
    </AuthenticatedLayout>
</template>

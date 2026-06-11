<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';
import { useLineConfig, useDarkMode } from '@/Composables/useChart';
import PwaInstallBanner from '@/Components/PwaInstallBanner.vue';
import { computed } from 'vue';

const props = defineProps({
    totalBalanceCents:    { type: Number, default: 0 },
    monthInflowCents:    { type: Number, default: 0 },
    monthOutflowCents:   { type: Number, default: 0 },
    monthSavingsCents:   { type: Number, default: 0 },
    recentTransactions:  { type: Array,  default: () => [] },
    accounts:            { type: Array,  default: () => [] },
    monthlyFlow:         { type: Array,  default: () => [] },
    goals:               { type: Array,  default: () => [] },
    subscriptions:       { type: Object, default: () => ({}) },
    investmentsSummary:  { type: Object, default: () => null },
    debts:               { type: Object, default: () => ({}) },
    homeCurrency:        { type: String, default: 'BRL' },
});

const isDark = useDarkMode();

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
    height: 280,
    colors: ['#10b981', '#ef4444', '#7c3aed'],
}));

// Build an inline SVG sparkline from a 6-point monthly series
const sparkPath = (values, width = 100, height = 30) => {
    if (!values || values.length < 2) return '';
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = (max - min) || 1;
    const step = width / (values.length - 1);
    return values
        .map((v, i) => {
            const x = i * step;
            const y = height - ((v - min) / range) * height;
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
};
const sparkNet = computed(() => sparkPath(props.monthlyFlow.map((m) => m.net), 100, 30));
const sparkInflow = computed(() => sparkPath(props.monthlyFlow.map((m) => m.income), 100, 30));
const sparkOutflow = computed(() => sparkPath(props.monthlyFlow.map((m) => Math.abs(m.expense)), 100, 30));
</script>

<template>
    <Head title="Dashboard · Solar Money" />
    <AuthenticatedLayout title="Dashboard">

        <!-- ─── HERO — Saldo total com gradiente + sparkline ─── -->
        <section class="surface-ink p-6 md:p-8 mb-6">
            <div class="absolute top-4 right-4 chip-ink text-white/60 border-white/10">
                {{ homeCurrency }}
            </div>

            <div class="relative z-10 max-w-2xl">
                <p class="text-xs uppercase tracking-[0.2em] text-white/60 font-semibold">Saldo total</p>
                <h1 class="font-display text-display-lg md:text-display-xl font-bold mt-2 num leading-none">
                    {{ formatCents(totalBalanceCents) }}
                </h1>
                <p class="text-white/60 text-sm mt-3">
                    <span :class="monthSavingsCents >= 0 ? 'text-emerald-300' : 'text-rose-300'" class="font-semibold">
                        {{ monthSavingsCents >= 0 ? '+' : '' }}{{ formatCents(monthSavingsCents) }}
                    </span>
                    este mês · em {{ accounts.length }} {{ accounts.length === 1 ? 'conta' : 'contas' }}
                </p>

                <!-- In-hero cashflow sparkline -->
                <div class="mt-6 pt-5 border-t border-white/10">
                    <div class="flex items-center gap-2 text-[11px] uppercase tracking-[0.15em] text-white/50 mb-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>Fluxo · últimos 6 meses</span>
                    </div>
                    <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full h-8">
                        <defs>
                            <linearGradient id="heroSpark" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#FF8A3D" stop-opacity="0.5" />
                                <stop offset="100%" stop-color="#FF8A3D" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path :d="`${sparkNet} L100,30 L0,30 Z`" fill="url(#heroSpark)" />
                        <path :d="sparkNet" fill="none" stroke="#FFC93C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
        </section>

        <!-- ─── Quick stats — 3 mini cards com sparklines ─── -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4 mb-6">
            <div class="stat-tile">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs text-ink-500 dark:text-ink-400">Receitas do mês</p>
                        <p class="font-mono text-lg font-bold mt-1 text-emerald-600 dark:text-emerald-400 num">
                            {{ formatCents(monthInflowCents) }}
                        </p>
                    </div>
                    <div class="w-8 h-8 rounded-lg grid place-items-center bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                        </svg>
                    </div>
                </div>
                <svg viewBox="0 0 100 20" preserveAspectRatio="none" class="w-full h-4 mt-2">
                    <path :d="sparkInflow" fill="none" stroke="#10b981" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <div class="stat-tile">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs text-ink-500 dark:text-ink-400">Despesas do mês</p>
                        <p class="font-mono text-lg font-bold mt-1 text-rose-600 dark:text-rose-400 num">
                            {{ formatCents(monthOutflowCents) }}
                        </p>
                    </div>
                    <div class="w-8 h-8 rounded-lg grid place-items-center bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-300 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <svg viewBox="0 0 100 20" preserveAspectRatio="none" class="w-full h-4 mt-2">
                    <path :d="sparkOutflow" fill="none" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <div class="stat-tile">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs text-ink-500 dark:text-ink-400">Economia do mês</p>
                        <p class="font-mono text-lg font-bold mt-1 num"
                           :class="monthSavingsCents >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-rose-600 dark:text-rose-400'">
                            {{ formatCents(monthSavingsCents) }}
                        </p>
                    </div>
                    <div class="w-8 h-8 rounded-lg grid place-items-center bg-primary-100 dark:bg-primary-500/15 text-primary-600 dark:text-primary-300 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                </div>
                <svg viewBox="0 0 100 20" preserveAspectRatio="none" class="w-full h-4 mt-2">
                    <path :d="sparkNet" fill="none" :stroke="monthSavingsCents >= 0 ? '#7c3aed' : '#ef4444'" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
        </div>

        <!-- ─── Two-column: cash flow chart + goals ─── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
            <div class="card p-5 md:p-6 lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="font-display font-bold text-lg">Fluxo de caixa</h2>
                        <p class="text-xs text-ink-500 mt-0.5">Últimos 6 meses · em {{ homeCurrency }}</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-ink-500">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Receitas</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span>Despesas</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-primary-500"></span>Saldo</span>
                    </div>
                </div>
                <Apexchart
                    v-if="props.monthlyFlow.length"
                    type="line" height="280"
                    :options="flowOptions" :series="flowSeries" />
                <div v-else class="h-64 flex items-center justify-center text-sm text-ink-400">
                    Sem transações no período
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-display font-bold text-base">Metas em andamento</h2>
                    <Link :href="route('goals.index')" class="text-xs text-primary-600 hover:underline">Ver todas</Link>
                </div>
                <div v-if="goals.length === 0" class="text-center py-6 text-sm text-ink-400">
                    Nenhuma meta ativa.
                    <Link :href="route('goals.create')" class="block mt-1 text-primary-600 hover:underline">Criar meta</Link>
                </div>
                <div v-else class="space-y-4">
                    <Link v-for="g in goals" :key="g.id" :href="route('goals.edit', g.id)" class="block group">
                        <div class="flex items-center gap-2.5 mb-1.5">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm shrink-0"
                                  :style="{ backgroundColor: (g.color || '#f59e0b') + '20', color: g.color || '#f59e0b' }">
                                {{ g.icon || '🎯' }}
                            </span>
                            <span class="text-sm font-semibold flex-1 truncate group-hover:text-primary-600 transition-colors">{{ g.name }}</span>
                            <span class="text-xs font-bold text-ink-700 dark:text-ink-200 tabular-nums num">{{ g.progress_percent }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-ink-100 dark:bg-ink-800 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 :style="{ width: g.progress_percent + '%', backgroundColor: g.color || '#f59e0b' }"></div>
                        </div>
                        <p class="text-[11px] text-ink-500 mt-1.5 ml-9 num">
                            {{ formatCents(g.current_amount_cents) }} de {{ formatCents(g.target_amount_cents) }}
                        </p>
                    </Link>
                </div>
            </div>
        </div>

        <!-- ─── Subscriptions (FASE 4B) ─── -->
        <div v-if="(subscriptions?.active_count ?? 0) > 0" class="card-elevated p-5 md:p-6 mt-4 md:mt-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-display font-bold text-base">Assinaturas</h2>
                    <p class="text-xs text-ink-500 mt-0.5">
                        <span class="num">{{ formatCents(subscriptions.total_monthly_cents) }}</span> / mês · {{ subscriptions.active_count }} ativa{{ subscriptions.active_count === 1 ? '' : 's' }}
                    </p>
                </div>
                <Link :href="route('subscriptions.index')" class="text-xs text-primary-600 hover:underline">Ver todas</Link>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Link v-for="s in subscriptions.upcoming" :key="s.id" :href="route('subscriptions.edit', s.id)"
                      class="group flex items-center gap-3 p-3.5 rounded-2xl border border-ink-200/60 dark:border-ink-800/60 hover:border-primary-300 dark:hover:border-primary-700 hover:bg-ink-50/40 dark:hover:bg-ink-800/40 transition-all">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-lg shrink-0 transition-transform duration-300 group-hover:scale-110"
                         :style="{ backgroundColor: (s.color || '#ef4444') + '1a', color: s.color || '#ef4444' }">
                        {{ s.icon || '📺' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ s.name }}</p>
                        <p class="text-[11px] text-ink-500 mt-0.5">
                            <span v-if="s.days_until_billing === 0" class="text-expense font-bold">Cobra hoje</span>
                            <span v-else-if="s.days_until_billing === 1" class="text-amber-600 font-semibold">Cobra amanhã</span>
                            <span v-else>Em <span class="font-semibold num">{{ s.days_until_billing }}</span> dias</span>
                        </p>
                    </div>
                    <p class="text-sm font-bold tabular-nums num">{{ s.amount_formatted }}</p>
                </Link>
            </div>
        </div>

        <!-- ─── Investments (FASE 5) — solar hero widget ─── -->
        <div v-if="investmentsSummary" class="mt-4 md:mt-6 relative overflow-hidden rounded-3xl
                    bg-gradient-to-br from-solar-500 via-solar-600 to-solar-700 text-white shadow-glow-solar">
            <div class="sun-glow -top-20 -right-20 w-64 h-64 opacity-30"></div>
            <div class="relative z-10 p-6 md:p-7">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/70 font-bold">Investimentos</p>
                        <p class="font-display text-2xl font-bold mt-1 num">
                            {{ formatCents(investmentsSummary.current_value_cents) }}
                            <span class="text-sm font-normal text-white/70">/ {{ formatCents(investmentsSummary.total_invested_cents) }}</span>
                        </p>
                    </div>
                    <Link :href="route('investments.index')" class="text-xs text-white/80 hover:text-white">Ver todos →</Link>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-white/12 backdrop-blur-md border border-white/10 p-3.5">
                        <p class="text-[10px] text-white/70 uppercase tracking-wider">P&amp;L</p>
                        <p class="font-mono text-lg font-bold mt-1 num"
                           :class="investmentsSummary.profit_loss_cents >= 0 ? 'text-emerald-200' : 'text-rose-200'">
                            {{ investmentsSummary.profit_loss_cents >= 0 ? '+' : '' }}{{ formatCents(investmentsSummary.profit_loss_cents) }}
                            <span v-if="investmentsSummary.profit_loss_percent !== null" class="text-xs ml-1">
                                ({{ investmentsSummary.profit_loss_percent >= 0 ? '+' : '' }}{{ investmentsSummary.profit_loss_percent.toFixed(2) }}%)
                            </span>
                        </p>
                    </div>
                    <div class="rounded-2xl bg-white/12 backdrop-blur-md border border-white/10 p-3.5">
                        <p class="text-[10px] text-white/70 uppercase tracking-wider">Posições</p>
                        <p class="font-mono text-lg font-bold mt-1 num">{{ investmentsSummary.count }}</p>
                    </div>
                    <div v-if="investmentsSummary.by_type.length" class="rounded-2xl bg-white/12 backdrop-blur-md border border-white/10 p-3.5 col-span-2 md:col-span-1">
                        <p class="text-[10px] text-white/70 uppercase tracking-wider mb-1.5">Alocação</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="t in investmentsSummary.by_type" :key="t.type"
                                  class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white/15 backdrop-blur">
                                {{ t.label }} · {{ formatCents(t.total_cents) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Debts (FASE 5) ─── -->
        <div v-if="(debts?.count_active ?? 0) > 0" class="card-elevated p-5 md:p-6 mt-4 md:mt-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-display font-bold text-base">Dívidas</h2>
                    <p class="text-xs text-ink-500 mt-0.5">
                        <span class="num font-semibold">{{ formatCents(debts.total_balance_cents) }}</span> em
                        {{ debts.count_active }} ativa{{ debts.count_active === 1 ? '' : 's' }} ·
                        <span class="num">{{ formatCents(debts.monthly_commitment_cents) }}</span> / mês
                    </p>
                </div>
                <Link :href="route('debts.index')" class="text-xs text-primary-600 hover:underline">Ver todas</Link>
            </div>
            <div v-if="debts.top && debts.top.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Link v-for="d in debts.top" :key="d.id" :href="route('debts.show', d.id)"
                      class="group flex items-center gap-3 p-3.5 rounded-2xl border border-ink-200/60 dark:border-ink-800/60 hover:border-rose-300 dark:hover:border-rose-700 hover:bg-rose-50/30 dark:hover:bg-rose-500/5 transition-all">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-lg shrink-0 bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-300 transition-transform duration-300 group-hover:scale-110">
                        💳
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ d.creditor }}</p>
                        <p class="text-[11px] text-ink-500 truncate">{{ d.description || 'Sem descrição' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold tabular-nums num">{{ formatCents(d.total_balance_cents) }}</p>
                        <p class="text-[10px] text-ink-500 uppercase tracking-wider">{{ d.payoff_strategy.toUpperCase() }}</p>
                    </div>
                </Link>
            </div>
        </div>

        <!-- ─── Two-column: accounts + recent transactions ─── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mt-4 md:mt-6">
            <div class="card p-5 lg:col-span-1">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-display font-bold text-base">Minhas contas</h2>
                    <Link :href="route('accounts.create')" class="text-xs text-primary-600 hover:underline">+ Nova</Link>
                </div>
                <div v-if="accounts.length === 0" class="text-center py-6 text-sm text-ink-400">
                    Nenhuma conta.
                    <Link :href="route('accounts.create')" class="block mt-1 text-primary-600 hover:underline">Criar primeira</Link>
                </div>
                <ul v-else class="space-y-2.5">
                    <li v-for="acc in accounts.slice(0, 6)" :key="acc.id" class="flex items-center gap-3">
                        <span class="w-2.5 h-2.5 rounded-full ring-2 ring-white dark:ring-ink-900"
                              :style="{ backgroundColor: acc.color || '#94a3b8' }"></span>
                        <span class="text-sm flex-1 truncate font-medium">{{ acc.name }}</span>
                        <span class="text-sm font-bold tabular-nums num"
                              :class="acc.balance_cents >= 0 ? 'text-ink-900 dark:text-ink-50' : 'text-expense'">
                            {{ formatCents(acc.balance_cents) }}
                        </span>
                    </li>
                </ul>
            </div>

            <div class="card p-5 lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-display font-bold text-base">Transações recentes</h2>
                    <Link :href="route('transactions.index')" class="text-xs text-primary-600 hover:underline">Ver todas</Link>
                </div>
                <div v-if="recentTransactions.length === 0" class="text-center py-12">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-primary-100 to-solar-100 dark:from-primary-500/15 dark:to-solar-500/15 grid place-items-center">
                        <svg class="w-7 h-7 text-primary-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium mb-1">Nenhuma transação ainda</p>
                    <p class="text-xs text-ink-500 mb-3">Lance sua primeira para começar.</p>
                    <Link :href="route('transactions.create')" class="btn-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Nova transação
                    </Link>
                </div>
                <ul v-else class="divide-y divide-ink-100 dark:divide-ink-800">
                    <li v-for="tx in recentTransactions" :key="tx.id" class="py-3 flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0"
                             :class="tx.type === 'income' ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600' : (tx.type === 'transfer' ? 'bg-blue-100 dark:bg-blue-500/15 text-blue-600' : 'bg-rose-100 dark:bg-rose-500/15 text-rose-600')">
                            {{ tx.category?.icon || (tx.type === 'income' ? '⬆' : '⬇') }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ tx.description }}</p>
                            <p class="text-[11px] text-ink-500 truncate mt-0.5">
                                {{ tx.account?.name }} · {{ tx.category?.name }} · {{ formatDate(tx.date) }}
                            </p>
                        </div>
                        <span class="text-sm font-bold tabular-nums num"
                              :class="tx.type === 'income' ? 'text-emerald-600' : (tx.type === 'transfer' ? 'text-blue-600' : 'text-rose-600')">
                            {{ tx.type === 'income' ? '+' : '-' }}{{ formatCents(Math.abs(tx.amount_cents)) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <PwaInstallBanner />
    </AuthenticatedLayout>
</template>

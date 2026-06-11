<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';

const props = defineProps({
    debts: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const toggleFilter = (key) => {
    router.get(route('debts.index'), { ...props.filters, [key]: !props.filters[key] }, { preserveState: true });
};

const destroy = (d) => {
    if (confirm(`Remover a dívida com "${d.creditor}"? Ela ficará no histórico.`)) {
        router.delete(route('debts.destroy', d.id));
    }
};

const strategyLabel = (s) => (s === 'price' ? 'Price' : 'SAC');
const strategyColor = (s) => (s === 'price' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300');
</script>

<template>
    <Head title="Dívidas" />
    <AuthenticatedLayout title="Dívidas">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6 md:mb-8">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold tracking-tight">Suas dívidas</h2>
                <p class="text-sm text-slate-500 mt-1">
                    {{ props.totals.count_active ?? 0 }}
                    {{ (props.totals.count_active ?? 0) === 1 ? 'dívida ativa' : 'dívidas ativas' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="toggleFilter('paid_off')"
                    :class="[
                        'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 ease-out',
                        props.filters.paid_off
                            ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 dark:border-white'
                            : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
                    ]"
                >Mostrar quitadas</button>
                <Link :href="route('debts.create')" class="btn-primary">
                    <span class="text-base leading-none">+</span> Nova dívida
                </Link>
            </div>
        </div>

        <!-- Totals card (Apple-style: large, airy) -->
        <div v-if="(props.totals.count_active ?? 0) > 0" class="card-elevated p-6 md:p-8 mb-6 md:mb-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Saldo total</p>
                    <p class="text-3xl md:text-4xl font-bold tabular-nums tracking-tight mt-2 text-expense">
                        {{ formatCents(props.totals.total_balance_cents ?? 0) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Comprometido / mês</p>
                    <p class="text-2xl md:text-3xl font-semibold tabular-nums tracking-tight mt-2 text-slate-600 dark:text-slate-400">
                        {{ formatCents(props.totals.monthly_commitment_cents ?? 0) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Taxa média ponderada</p>
                    <p class="text-2xl md:text-3xl font-semibold tabular-nums tracking-tight mt-2 text-slate-600 dark:text-slate-400">
                        {{ ((props.totals.weighted_avg_rate ?? 0) * 100).toFixed(2) }}% a.a.
                    </p>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="props.debts.length === 0" class="card-elevated p-12 md:p-16 text-center">
            <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-amber-100 to-rose-100 dark:from-amber-900/30 dark:to-rose-900/30 flex items-center justify-center mx-auto mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h12" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold mb-1">Sem dívidas por aqui</h3>
            <p class="text-sm text-slate-500 mb-6 max-w-sm mx-auto">Cadastre financiamentos, cartão de crédito, empréstimos — e simule SAC vs Price.</p>
            <Link :href="route('debts.create')" class="btn-primary inline-flex">+ Adicionar dívida</Link>
        </div>

        <!-- Debts grid (Apple-style cards) -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
            <div
                v-for="d in props.debts"
                :key="d.id"
                class="card-elevated p-5 md:p-6 group transition-all duration-200 ease-out"
                :class="d.is_paid_off ? 'opacity-60' : ''"
            >
                <!-- Header row -->
                <div class="flex items-start justify-between mb-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold truncate tracking-tight">{{ d.creditor }}</p>
                        <p v-if="d.description" class="text-xs text-slate-500 truncate">{{ d.description }}</p>
                    </div>
                    <span
                        :class="['px-2.5 py-1 rounded-full text-[11px] font-semibold tracking-wide', strategyColor(d.payoff_strategy)]"
                    >{{ strategyLabel(d.payoff_strategy) }}</span>
                </div>

                <!-- Balance (hero) -->
                <p class="text-3xl font-bold tabular-nums tracking-tight" :class="d.is_paid_off ? 'line-through text-slate-400' : 'text-expense'">
                    {{ d.total_balance_formatted }}
                </p>
                <p class="text-xs text-slate-500 mt-1">
                    {{ d.monthly_payment_formatted }} / mês · {{ d.interest_rate_percent.toFixed(2) }}% a.a.
                </p>

                <!-- Status + actions -->
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <span
                        :class="[
                            'px-2 py-0.5 rounded-full text-[11px] font-semibold',
                            d.is_paid_off
                                ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                        ]"
                    >{{ d.is_paid_off ? 'Quitada' : 'Ativa' }}</span>
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <Link :href="route('debts.show', d.id)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Ver detalhes">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </Link>
                        <Link :href="route('debts.edit', d.id)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Editar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </Link>
                        <button v-if="!d.is_paid_off" @click="destroy(d)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-expense" title="Remover">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

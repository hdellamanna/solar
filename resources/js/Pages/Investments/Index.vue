<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';

const props = defineProps({
    investments: { type: Array, default: () => [] },
    pagination:  { type: Object, default: () => ({}) },
    totals:      { type: Object, default: () => ({}) },
    types:       { type: Object, default: () => ({}) },
    typeColors:  { type: Object, default: () => ({}) },
});

const destroy = (i) => {
    if (confirm(`Remover o investimento "${i.name}"?`)) {
        router.delete(route('investments.destroy', i.id));
    }
};

const plLabel = (i) => {
    if (!i.has_current_price) return '—';
    const sign = i.profit_loss_cents > 0 ? '+' : i.profit_loss_cents < 0 ? '-' : '';
    return `${sign}${formatCents(Math.abs(i.profit_loss_cents))}`;
};
const plPercent = (i) => {
    if (i.profit_loss_percent === null || i.profit_loss_percent === undefined) return '';
    const sign = i.profit_loss_percent > 0 ? '+' : '';
    return ` (${sign}${i.profit_loss_percent.toFixed(2).replace('.', ',')}%)`;
};
const plClass = (i) => {
    if (!i.has_current_price) return 'text-slate-400';
    if (i.profit_loss_cents > 0) return 'text-income';
    if (i.profit_loss_cents < 0) return 'text-expense';
    return 'text-slate-500';
};
</script>

<template>
    <Head title="Investimentos" />
    <AuthenticatedLayout title="Investimentos">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6 md:mb-8">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold tracking-tight">Seus investimentos</h2>
                <p class="text-sm text-slate-500 mt-1">
                    {{ props.totals.count ?? 0 }}
                    {{ (props.totals.count ?? 0) === 1 ? 'ativo rastreado' : 'ativos rastreados' }}
                </p>
            </div>
            <Link :href="route('investments.create')" class="btn-primary">
                <span class="text-base leading-none">+</span> Novo investimento
            </Link>
        </div>

        <div v-if="(props.totals.count ?? 0) > 0" class="card-elevated p-6 md:p-8 mb-6 md:mb-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <p class="text-[11px] font-medium text-slate-500/90">Total investido</p>
                    <p class="text-2xl md:text-3xl font-semibold tabular-nums tracking-tight mt-2 text-slate-700 dark:text-slate-300">
                        {{ formatCents(props.totals.total_invested_cents ?? 0) }}
                    </p>
                </div>
                <div>
                    <p class="text-[11px] font-medium text-slate-500/90">Valor atual</p>
                    <p class="text-3xl md:text-4xl font-bold tabular-nums tracking-tight mt-2">
                        {{ formatCents(props.totals.current_value_cents ?? 0) }}
                    </p>
                </div>
                <div>
                    <p class="text-[11px] font-medium text-slate-500/90">Lucro / prejuízo</p>
                    <p
                        class="text-2xl md:text-3xl font-semibold tabular-nums tracking-tight mt-2"
                        :class="(props.totals.profit_loss_cents ?? 0) > 0 ? 'text-income' : (props.totals.profit_loss_cents ?? 0) < 0 ? 'text-expense' : 'text-slate-500'"
                    >
                        {{ (props.totals.profit_loss_cents ?? 0) >= 0 ? '+' : '-' }}{{ formatCents(Math.abs(props.totals.profit_loss_cents ?? 0)) }}
                        <span v-if="props.totals.profit_loss_percent !== null && props.totals.profit_loss_percent !== undefined" class="text-base font-normal text-slate-500 ml-1">
                            ({{ (props.totals.profit_loss_percent ?? 0) > 0 ? '+' : '' }}{{ (props.totals.profit_loss_percent ?? 0).toFixed(2).replace('.', ',') }}%)
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <div v-if="props.investments.length === 0" class="card-elevated p-12 md:p-16 text-center">
            <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-amber-100 to-orange-100 dark:from-amber-900/30 dark:to-orange-900/30 flex items-center justify-center mx-auto mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold mb-1">Comece a investir</h3>
            <p class="text-sm text-slate-500 mb-6 max-w-sm mx-auto">Cadastre ações, fundos, criptomoedas, renda fixa e títulos do Tesouro para acompanhar a rentabilidade.</p>
            <Link :href="route('investments.create')" class="btn-primary inline-flex">+ Adicione seu primeiro investimento</Link>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
            <div
                v-for="i in props.investments"
                :key="i.id"
                class="card-elevated p-5 md:p-6 group transition-all duration-200 ease-out"
            >
                <div class="flex items-start justify-between mb-4">
                    <div class="min-w-0">
                        <span
                            class="inline-block text-[10px] uppercase tracking-wider font-semibold px-2 py-0.5 rounded-full"
                            :style="{ backgroundColor: (i.type_color || '#64748b') + '1a', color: i.type_color || '#64748b' }"
                        >{{ i.type_label }}</span>
                        <p v-if="i.ticker" class="text-[11px] text-slate-500 mt-1.5 font-mono tracking-wide">{{ i.ticker }}</p>
                    </div>
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                        <Link :href="route('investments.edit', i.id)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Editar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </Link>
                        <button @click="destroy(i)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-expense" title="Excluir">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>

                <p class="font-semibold tracking-tight truncate">{{ i.name }}</p>
                <p class="text-xs text-slate-500 mt-1 tabular-nums">
                    {{ i.formatted_quantity }} × {{ i.currency_symbol }} {{ i.average_price_decimal.toFixed(2).replace('.', ',') }}
                </p>
                <p class="text-sm font-medium tabular-nums text-slate-600 dark:text-slate-400 mt-1">
                    {{ formatCents(i.total_invested_cents) }}
                </p>

                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-baseline justify-between">
                        <p class="text-xs text-slate-500">Valor atual</p>
                        <p class="text-sm font-semibold tabular-nums">
                            {{ i.has_current_price ? formatCents(i.current_value_cents) : '—' }}
                        </p>
                    </div>
                    <div class="flex items-baseline justify-between mt-1">
                        <p class="text-xs text-slate-500">P&amp;L</p>
                        <p class="text-sm font-semibold tabular-nums" :class="plClass(i)">
                            <span v-if="i.profit_loss_cents > 0">▲</span>
                            <span v-else-if="i.profit_loss_cents < 0">▼</span>
                            {{ plLabel(i) }}<span class="font-normal">{{ plPercent(i) }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="props.pagination?.last_page > 1" class="mt-6 flex items-center justify-center gap-2">
            <template v-for="(link, idx) in (props.pagination.last_page ? Array.from({length: props.pagination.last_page}, (_, i) => i + 1) : [])" :key="idx">
                <button
                    v-if="link === props.pagination.current_page"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium bg-slate-900 text-white dark:bg-white dark:text-slate-900"
                >{{ link }}</button>
                <Link
                    v-else
                    :href="route('investments.index', { page: link })"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                >{{ link }}</Link>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

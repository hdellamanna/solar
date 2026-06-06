<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    formatBRL,
    useLineConfig,
    useDonutConfig,
    useBarConfig,
    useDarkMode,
} from '@/Composables/useChart';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    preset: { type: String, default: 'this_month' },
    kpis: { type: Object, required: true },
    monthly: { type: Array, required: true },
    categories: { type: Array, required: true },
    accounts: { type: Array, required: true },
    daily: { type: Array, required: true },
    merchants: { type: Array, required: true },
});

const isDark = useDarkMode();

// --- Period selector -------------------------------------------------------

const customFrom = ref(props.from);
const customTo = ref(props.to);

const presets = [
    { key: 'this_month', label: 'Este mês' },
    { key: 'last_3',     label: 'Últimos 3 meses' },
    { key: 'last_6',     label: 'Últimos 6 meses' },
    { key: 'ytd',        label: 'Este ano' },
];

function rangeFor(preset) {
    const fmt = (d) => d.toISOString().slice(0, 10);
    const today = new Date();
    const y = today.getFullYear();
    const m = today.getMonth();
    switch (preset) {
        case 'this_month':
            return { from: fmt(new Date(y, m, 1)), to: fmt(new Date(y, m + 1, 0)) };
        case 'last_3':
            return { from: fmt(new Date(y, m - 2, 1)), to: fmt(new Date(y, m + 1, 0)) };
        case 'last_6':
            return { from: fmt(new Date(y, m - 5, 1)), to: fmt(new Date(y, m + 1, 0)) };
        case 'ytd':
            return { from: fmt(new Date(y, 0, 1)), to: fmt(today) };
        default:
            return { from: customFrom.value, to: customTo.value };
    }
}

function applyPreset(preset) {
    const { from, to } = rangeFor(preset);
    router.get(route('reports.index'), { preset, from, to }, { preserveState: true });
}

function applyCustom() {
    if (!customFrom.value || !customTo.value) return;
    router.get(route('reports.index'), {
        preset: 'custom',
        from: customFrom.value,
        to: customTo.value,
    }, { preserveState: true });
}

// --- Derived datasets ------------------------------------------------------

const hasData = computed(() => props.kpis.count > 0);
const isEmpty = computed(() => !hasData.value);

// Period label (e.g. "Junho/2026" or "01/06/2026 — 30/06/2026")
const periodLabel = computed(() => {
    if (!props.from || !props.to) return '';
    const f = new Date(props.from);
    const t = new Date(props.to);
    const fmtShort = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    if (props.from === props.to) return fmtShort.format(f);
    return `${fmtShort.format(f)} — ${fmtShort.format(t)}`;
});

// --- Chart datasets --------------------------------------------------------

// Line: 12-month flow
const monthlyCategories = computed(() => props.monthly.map((m) => m.month));
const monthlySeries = computed(() => [
    { name: 'Receitas', data: props.monthly.map((m) => m.income) },
    { name: 'Despesas', data: props.monthly.map((m) => Math.abs(m.expense)) },
    { name: 'Saldo',    data: props.monthly.map((m) => m.net) },
]);
const lineOptions = computed(() => useLineConfig({
    categories: monthlyCategories.value,
    series: monthlySeries.value,
    isDark,
    height: 340,
    colors: ['#10b981', '#ef4444', '#3b82f6'],
}));

// Donut: category breakdown
const categoryLabels = computed(() => props.categories.map((c) => c.name));
const categoryValues = computed(() => props.categories.map((c) => Math.abs(c.value_cents)));
const donutCategoryOptions = computed(() => useDonutConfig({
    labels: categoryLabels.value,
    isDark,
    height: 340,
    totalLabel: 'Total',
}));

// Bar: daily spending (last 30 days slice to keep the chart readable)
const dailySlice = computed(() => {
    if (!props.daily.length) return { cats: [], vals: [] };
    // If more than 60 days, show last 30 to avoid overcrowding
    if (props.daily.length > 60) {
        const last = props.daily.slice(-30);
        return {
            cats: last.map((d) => d.date),
            vals: last.map((d) => d.value_cents),
        };
    }
    return {
        cats: props.daily.map((d) => d.date),
        vals: props.daily.map((d) => d.value_cents),
    };
});
const dailyOptions = computed(() => useBarConfig({
    categories: dailySlice.value.cats,
    series: [{ name: 'Despesas', data: dailySlice.value.vals }],
    isDark,
    height: 280,
    colors: ['#ef4444'],
}));

// Horizontal bar: top merchants
const merchantCats = computed(() => props.merchants.map((m) => m.description));
const merchantVals = computed(() => props.merchants.map((m) => Math.abs(m.total_cents)));
const merchantOptions = computed(() => useBarConfig({
    categories: merchantCats.value,
    series: [{ name: 'Total', data: merchantVals.value }],
    isDark,
    height: 320,
    horizontal: true,
    colors: ['#f59e0b'],
}));

// Donut: account distribution
const accountLabels = computed(() => props.accounts.map((a) => a.account_name));
const accountValues = computed(() => props.accounts.map((a) => Math.abs(a.balance_cents)));
const accountColors = computed(() => props.accounts.map((a) => a.color));
const accountTypeLabel = (t) => ({
    checking: 'Conta corrente',
    savings: 'Poupança',
    credit_card: 'Cartão de crédito',
    cash: 'Dinheiro',
    investment: 'Investimento',
    crypto: 'Cripto',
}[t] || t);
const donutAccountOptions = computed(() => {
    const cfg = useDonutConfig({
        labels: accountLabels.value,
        isDark,
        height: 320,
        totalLabel: 'Patrimônio',
    });
    // Override colors with per-account colors
    if (accountColors.value.length) cfg.colors = accountColors.value;
    return cfg;
});
const accountTotal = computed(() =>
    props.accounts.reduce((sum, a) => sum + a.balance_cents, 0)
);

// --- Export modal ----------------------------------------------------------

const showExportModal = ref(false);
const exportType = ref('');
function openExport(type) {
    exportType.value = type;
    showExportModal.value = true;
}
function closeExport() {
    showExportModal.value = false;
}
</script>

<template>
    <Head title="Relatórios" />
    <AuthenticatedLayout title="Relatórios">
        <!-- Header: title + period selector -->
        <div class="mb-4 md:mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Período selecionado</p>
                <p class="text-base font-semibold">{{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="p in presets"
                    :key="p.key"
                    @click="applyPreset(p.key)"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                        props.preset === p.key
                            ? 'bg-brand-500 text-white border-brand-500'
                            : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
                    ]"
                >
                    {{ p.label }}
                </button>
                <div class="flex items-center gap-1 ml-1 pl-2 border-l border-slate-200 dark:border-slate-800">
                    <input
                        v-model="customFrom"
                        type="date"
                        class="input !py-1.5 !text-xs w-[140px]"
                        aria-label="Data inicial"
                    />
                    <span class="text-slate-400 text-xs">→</span>
                    <input
                        v-model="customTo"
                        type="date"
                        class="input !py-1.5 !text-xs w-[140px]"
                        aria-label="Data final"
                    />
                    <button
                        @click="applyCustom"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200"
                    >
                        Aplicar
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="isEmpty" class="card p-10 md:p-16 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h2 class="text-lg font-semibold mb-1">Nada por aqui ainda</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                Nenhuma transação paga no período selecionado. Que tal registrar uma nova?
            </p>
            <a :href="route('transactions.create')" class="btn-primary">+ Nova transação</a>
        </div>

        <template v-else>
            <!-- KPI cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4 mb-4 md:mb-6">
                <div class="card p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Receitas</p>
                    <p class="text-lg md:text-xl font-bold mt-1 text-income">{{ formatBRL(props.kpis.income_cents) }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Despesas</p>
                    <p class="text-lg md:text-xl font-bold mt-1 text-expense">{{ formatBRL(props.kpis.expense_cents) }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Saldo</p>
                    <p
                        class="text-lg md:text-xl font-bold mt-1"
                        :class="props.kpis.net_cents >= 0 ? 'text-income' : 'text-expense'"
                    >{{ formatBRL(props.kpis.net_cents) }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Maior categoria</p>
                    <p class="text-sm font-semibold mt-1 truncate" :title="props.kpis.top_category_name || '—'">
                        {{ props.kpis.top_category_name || '—' }}
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ formatBRL(props.kpis.top_category_cents) }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Média diária</p>
                    <p class="text-lg md:text-xl font-bold mt-1 text-expense">{{ formatBRL(props.kpis.avg_daily_expense_cents) }}</p>
                </div>
            </div>

            <!-- Chart 1 + Chart 2: monthly flow + category donut -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-6 mb-4 md:mb-6">
                <div class="card p-5 lg:col-span-8">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold">Fluxo mensal</h2>
                        <span class="text-xs text-slate-400">Últimos 12 meses</span>
                    </div>
                    <Apexchart
                        type="line"
                        height="340"
                        :options="lineOptions"
                        :series="monthlySeries"
                    />
                </div>
                <div class="card p-5 lg:col-span-4">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold">Despesas por categoria</h2>
                        <span class="text-xs text-slate-400">Top 10</span>
                    </div>
                    <Apexchart
                        v-if="categories.length"
                        type="donut"
                        height="340"
                        :options="donutCategoryOptions"
                        :series="categoryValues"
                    />
                    <div v-else class="h-[340px] flex items-center justify-center text-sm text-slate-400">
                        Sem despesas no período
                    </div>
                </div>
            </div>

            <!-- Chart 3: daily spending -->
            <div class="card p-5 mb-4 md:mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Gastos diários</h2>
                    <span class="text-xs text-slate-400">{{ dailySlice.cats.length }} dias</span>
                </div>
                <Apexchart
                    type="bar"
                    height="280"
                    :options="dailyOptions"
                    :series="[{ name: 'Despesas', data: dailySlice.vals }]"
                />
            </div>

            <!-- Chart 4 + Chart 5: merchants + account distribution -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-4 md:mb-6">
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold">Top 10 comércios</h2>
                        <span class="text-xs text-slate-400">Por gasto</span>
                    </div>
                    <Apexchart
                        v-if="merchants.length"
                        type="bar"
                        height="320"
                        :options="merchantOptions"
                        :series="[{ name: 'Total', data: merchantVals }]"
                    />
                    <div v-else class="h-[320px] flex items-center justify-center text-sm text-slate-400">
                        Sem despesas categorizadas no período
                    </div>
                </div>
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold">Saldo por conta</h2>
                        <span
                            class="text-xs font-semibold"
                            :class="accountTotal >= 0 ? 'text-income' : 'text-expense'"
                        >{{ formatBRL(accountTotal) }}</span>
                    </div>
                    <Apexchart
                        v-if="accounts.length"
                        type="donut"
                        height="320"
                        :options="donutAccountOptions"
                        :series="accountValues"
                    />
                    <div v-else class="h-[320px] flex items-center justify-center text-sm text-slate-400">
                        Nenhuma conta ativa
                    </div>
                </div>
            </div>

            <!-- Account list (legend with balances and types) -->
            <div v-if="accounts.length" class="card p-5 mb-4 md:mb-6">
                <h2 class="font-semibold mb-3">Detalhamento por conta</h2>
                <ul class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <li
                        v-for="acc in accounts"
                        :key="acc.account_name"
                        class="flex items-center justify-between p-3 rounded-xl border border-slate-200 dark:border-slate-800"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: acc.color }"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium truncate">{{ acc.account_name }}</p>
                                <p class="text-[11px] text-slate-500">{{ accountTypeLabel(acc.type) }}</p>
                            </div>
                        </div>
                        <span
                            class="text-sm font-semibold tabular-nums"
                            :class="acc.balance_cents >= 0 ? 'text-income' : 'text-expense'"
                        >{{ formatBRL(acc.balance_cents) }}</span>
                    </li>
                </ul>
            </div>

            <!-- Export buttons -->
            <div class="flex flex-wrap items-center justify-end gap-2 pb-2">
                <button
                    @click="openExport('pdf')"
                    class="btn-ghost"
                    aria-label="Exportar relatório em PDF"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" /></svg>
                    Exportar PDF
                </button>
                <button
                    @click="openExport('excel')"
                    class="btn-ghost"
                    aria-label="Exportar relatório em Excel"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Exportar Excel
                </button>
            </div>
        </template>

        <!-- Export modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showExportModal"
                    class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
                    @click.self="closeExport"
                >
                    <div class="card p-6 max-w-sm w-full">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <h3 class="text-lg font-semibold">Em breve</h3>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-5">
                            A exportação de relatórios em
                            <span class="font-semibold uppercase">{{ exportType }}</span>
                            está chegando nas próximas fases. Os gráficos e dados desta tela já estão prontos para serem enviados.
                        </p>
                        <div class="flex justify-end">
                            <button @click="closeExport" class="btn-primary">Entendi</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

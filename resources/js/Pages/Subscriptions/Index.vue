<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';

const props = defineProps({
    subscriptions: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const toggleFilter = (key) => {
    router.get(route('subscriptions.index'), { ...props.filters, [key]: !props.filters[key] }, { preserveState: true });
};

const destroy = (s) => {
    if (confirm(`Cancelar a assinatura "${s.name}"? Ela ficará no histórico.`)) {
        router.delete(route('subscriptions.destroy', s.id));
    }
};

const reactivate = (s) => {
    router.post(route('subscriptions.reactivate', s.id), {}, { preserveScroll: true });
};

const toggleActive = (s) => {
    router.post(route('subscriptions.toggle-active', s.id), {}, { preserveScroll: true });
};

const billingDateLabel = (s) => {
    if (s.is_cancelled) return 'Cancelada';
    if (s.days_until_billing === 0) return 'Cobra hoje';
    if (s.days_until_billing === 1) return 'Cobra amanhã';
    if (s.days_until_billing > 0) return `Em ${s.days_until_billing} dias`;
    return `${Math.abs(s.days_until_billing)}d atrás`;
};

const billingDateClass = (s) => {
    if (s.is_cancelled) return 'text-slate-400';
    if (s.days_until_billing <= 1) return 'text-expense font-semibold';
    if (s.days_until_billing <= 3) return 'text-amber-600 font-medium';
    return 'text-slate-500';
};
</script>

<template>
    <Head title="Assinaturas" />
    <AuthenticatedLayout title="Assinaturas">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6 md:mb-8">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold tracking-tight">Suas assinaturas</h2>
                <p class="text-sm text-slate-500 mt-1">
                    {{ props.totals.active_count ?? 0 }}
                    {{ (props.totals.active_count ?? 0) === 1 ? 'assinatura ativa' : 'assinaturas ativas' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="toggleFilter('cancelled')"
                    :class="[
                        'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 ease-out',
                        props.filters.cancelled
                            ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 dark:border-white'
                            : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
                    ]"
                >Mostrar canceladas</button>
                <Link :href="route('subscriptions.create')" class="btn-primary">
                    <span class="text-base leading-none">+</span> Nova
                </Link>
            </div>
        </div>

        <!-- Totals card (Apple-style: large, airy) -->
        <div v-if="(props.totals.active_count ?? 0) > 0" class="card-elevated p-6 md:p-8 mb-6 md:mb-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <p class="text-[11px] font-medium text-slate-500/90">Total mensal</p>
                    <p class="text-3xl md:text-4xl font-bold tabular-nums tracking-tight mt-2">
                        {{ formatCents(props.totals.monthly_cents ?? 0) }}
                    </p>
                </div>
                <div>
                    <p class="text-[11px] font-medium text-slate-500/90">Projeção anual</p>
                    <p class="text-2xl md:text-3xl font-semibold tabular-nums tracking-tight mt-2 text-slate-600 dark:text-slate-400">
                        {{ formatCents(props.totals.yearly_cents ?? 0) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="props.subscriptions.length === 0" class="card-elevated p-12 md:p-16 text-center">
            <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-rose-100 to-orange-100 dark:from-rose-900/30 dark:to-orange-900/30 flex items-center justify-center mx-auto mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold mb-1">Comece a rastrear</h3>
            <p class="text-sm text-slate-500 mb-6 max-w-sm mx-auto">Cadastre Netflix, Spotify, iCloud, academia — tudo que você paga todo mês.</p>
            <Link :href="route('subscriptions.create')" class="btn-primary inline-flex">+ Adicionar assinatura</Link>
        </div>

        <!-- Subscriptions grid (Apple-style cards) -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
            <div
                v-for="s in props.subscriptions"
                :key="s.id"
                class="card-elevated p-5 md:p-6 group transition-all duration-200 ease-out"
                :class="[
                    s.is_cancelled ? 'opacity-60' : '',
                    !s.active ? 'opacity-75' : '',
                ]"
            >
                <!-- Icon + name + menu -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl shrink-0 transition-transform duration-200 ease-out group-hover:scale-105"
                            :style="{ backgroundColor: (s.color || '#ef4444') + '1a', color: s.color || '#ef4444' }"
                        >{{ s.icon || '📺' }}</div>
                        <div class="min-w-0">
                            <p class="font-semibold truncate tracking-tight">{{ s.name }}</p>
                            <p v-if="s.account" class="text-xs text-slate-500 truncate">{{ s.account.name }}</p>
                        </div>
                    </div>
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                        <Link :href="route('subscriptions.edit', s.id)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Editar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </Link>
                        <button v-if="!s.is_cancelled" @click="destroy(s)" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-expense" title="Cancelar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Amount (hero) -->
                <p class="text-3xl font-bold tabular-nums tracking-tight" :class="s.is_cancelled ? 'line-through text-slate-400' : ''">
                    {{ s.amount_formatted }}
                </p>
                <p class="text-xs text-slate-500 mt-1">por mês</p>

                <!-- Billing info -->
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <p class="text-xs" :class="billingDateClass(s)">{{ billingDateLabel(s) }}</p>
                    <p v-if="!s.is_cancelled" class="text-xs text-slate-400 tabular-nums">dia {{ s.billing_day }}</p>
                </div>

                <!-- Action footer for cancelled/paused -->
                <div v-if="s.is_cancelled || !s.active" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex gap-2">
                    <button v-if="s.is_cancelled" @click="reactivate(s)" class="flex-1 text-xs py-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 font-medium transition-colors">
                        Reativar
                    </button>
                    <button v-else-if="!s.active" @click="toggleActive(s)" class="flex-1 text-xs py-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 font-medium transition-colors">
                        Reativar
                    </button>
                    <button v-if="!s.is_cancelled && s.active" @click="toggleActive(s)" class="text-xs py-2 px-3 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Pausar
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

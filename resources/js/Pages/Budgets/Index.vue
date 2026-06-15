<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';
import { statusLabel, statusBadgeClass, colorBarClass } from '@/Composables/useBudget';
import LocalizedName from '@/Components/LocalizedName.vue';

const props = defineProps({
    budgets: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
});

const reset = (b) => {
    if (confirm(`Reiniciar o orçamento "${b.name}"? Uma nova vigência será criada a partir de hoje.`)) {
        router.post(route('budgets.reset', b.id));
    }
};

const destroy = (b) => {
    if (confirm(`Excluir o orçamento "${b.name}"?`)) {
        router.delete(route('budgets.destroy', b.id));
    }
};
</script>

<template>
    <Head title="Orçamentos" />
    <AuthenticatedLayout title="Orçamentos">
        <!-- Header / summary -->
        <div class="card p-5 md:p-6 mb-5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="grid grid-cols-3 gap-4 flex-1">
                    <div>
                        <p class="text-xs text-slate-500">Total orçado</p>
                        <p class="text-lg md:text-xl font-bold tabular-nums">{{ formatCents(props.totals.budgeted_cents) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Total gasto</p>
                        <p class="text-lg md:text-xl font-bold tabular-nums" :class="props.totals.spent_cents > props.totals.budgeted_cents ? 'text-expense' : ''">
                            {{ formatCents(props.totals.spent_cents) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">% geral</p>
                        <p class="text-lg md:text-xl font-bold tabular-nums">{{ Math.round(props.totals.progress_percent || 0) }}%</p>
                    </div>
                </div>
                <Link :href="route('budgets.create')" class="btn-primary whitespace-nowrap">+ Novo orçamento</Link>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                <div class="h-full transition-all" :class="colorBarClass(props.budgets[0]?.color)"
                     :style="{ width: (props.totals.progress_percent || 0) + '%' }"></div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="props.budgets.length === 0" class="card p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-20 h-20 mx-auto mb-4 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 0V4m0 16v-2m8-6h-2M6 12H4m12.95-5.05l-1.41 1.41M7.46 16.54l-1.41 1.41m12.72 0l-1.41-1.41M7.46 7.46L6.05 6.05" />
            </svg>
            <h3 class="text-lg font-semibold mb-1">Nenhum orçamento ainda</h3>
            <p class="text-sm text-slate-500 mb-4">Crie orçamentos por categoria para acompanhar seus gastos.</p>
            <Link :href="route('budgets.create')" class="btn-primary inline-flex">+ Criar primeiro orçamento</Link>
        </div>

        <!-- Grid de cards -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
            <div v-for="b in props.budgets" :key="b.id" class="card p-4 md:p-5 group">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0"
                              :style="{ backgroundColor: (b.color || '#f59e0b') + '20', color: b.color || '#f59e0b' }">
                            {{ b.icon || b.category?.icon || '🎯' }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ b.name }}</p>
                            <p class="text-xs text-slate-500 truncate">
                                <span v-if="b.category"><LocalizedName :entity="b.category" /></span>
                                <span v-else>—</span>
                                • {{ b.period_label }}
                            </p>
                        </div>
                    </div>
                    <span :class="['text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full', statusBadgeClass(b.status)]">
                        {{ statusLabel(b.status) }}
                    </span>
                </div>

                <!-- Progress bar -->
                <div class="mb-3">
                    <div class="flex items-baseline justify-between mb-1.5 text-sm">
                        <span class="font-semibold tabular-nums">{{ formatCents(b.spent_cents) }}</span>
                        <span class="text-xs text-slate-500 tabular-nums">de {{ formatCents(b.amount_cents) }}</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                        <div class="h-full rounded-full transition-all"
                             :class="colorBarClass(b.color)"
                             :style="{ width: b.progress_percent + '%' }"></div>
                    </div>
                    <div class="flex items-center justify-between mt-1.5 text-xs text-slate-500">
                        <span>{{ Math.round(b.progress_percent) }}% usado</span>
                        <span v-if="b.days_remaining > 0">{{ b.days_remaining }} {{ b.days_remaining === 1 ? 'dia restante' : 'dias restantes' }}</span>
                        <span v-else class="text-amber-600">Período encerrado</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Link :href="route('budgets.edit', b.id)" class="btn-ghost flex-1 justify-center text-xs">Editar</Link>
                    <button @click="reset(b)" class="btn-ghost text-xs" title="Reiniciar vigência">↻ Resetar</button>
                    <button @click="destroy(b)" class="p-2 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-expense" title="Excluir">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

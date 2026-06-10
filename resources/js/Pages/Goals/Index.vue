<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';

const props = defineProps({
    goals: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const toggleFilter = (key) => {
    router.get(route('goals.index'), { ...props.filters, [key]: !props.filters[key] }, { preserveState: true });
};

const archive = (g) => {
    if (confirm(`Arquivar a meta "${g.name}"?`)) {
        router.delete(route('goals.destroy', g.id));
    }
};
</script>

<template>
    <Head title="Metas" />
    <AuthenticatedLayout title="Metas de economia">
        <!-- Header: counts + filters + new button -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 md:mb-6">
            <div>
                <p class="text-sm text-slate-500">
                    {{ props.totals.goals_count ?? 0 }}
                    {{ (props.totals.goals_count ?? 0) === 1 ? 'meta cadastrada' : 'metas cadastradas' }}
                </p>
                <p v-if="props.totals.target_cents > 0" class="text-xs text-slate-400 mt-0.5">
                    {{ formatCents(props.totals.current_cents) }} de {{ formatCents(props.totals.target_cents) }}
                    <span class="ml-1 text-brand-600 font-semibold">({{ props.totals.overall_progress_percent }}%)</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="toggleFilter('achieved')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                        props.filters.achieved
                            ? 'bg-brand-500 text-white border-brand-500'
                            : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
                    ]"
                >Mostrar concluídas</button>
                <button
                    @click="toggleFilter('archived')"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                        props.filters.archived
                            ? 'bg-brand-500 text-white border-brand-500'
                            : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
                    ]"
                >Mostrar arquivadas</button>
                <Link :href="route('goals.create')" class="btn-primary">+ Nova meta</Link>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="props.goals.length === 0" class="card p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            <h3 class="font-semibold mb-1">Crie sua primeira meta</h3>
            <p class="text-sm text-slate-500 mb-4">Defina um objetivo financeiro e acompanhe seu progresso.</p>
            <Link :href="route('goals.create')" class="btn-primary inline-flex">+ Nova meta</Link>
        </div>

        <!-- Goals grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
            <div
                v-for="g in props.goals"
                :key="g.id"
                class="card p-4 md:p-5 group relative"
                :class="g.is_achieved ? 'ring-2 ring-emerald-400/50' : ''"
            >
                <!-- Header: icon + name + menu -->
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0"
                            :style="{ backgroundColor: (g.color || '#f59e0b') + '20', color: g.color || '#f59e0b' }"
                        >{{ g.icon || '🎯' }}</span>
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ g.name }}</p>
                            <p v-if="g.is_achieved" class="text-xs text-emerald-600 font-medium">✓ Concluída</p>
                            <p v-else-if="g.days_remaining !== null" class="text-xs text-slate-500">
                                <span v-if="g.days_remaining >= 0">{{ g.days_remaining }}d restantes</span>
                                <span v-else class="text-expense">{{ Math.abs(g.days_remaining) }}d em atraso</span>
                            </p>
                            <p v-else class="text-xs text-slate-500">Sem prazo</p>
                        </div>
                    </div>
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                        <Link :href="route('goals.edit', g.id)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Editar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </Link>
                        <button @click="archive(g)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-expense" title="Arquivar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden mb-2">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="g.is_achieved ? 'bg-emerald-500' : ''"
                        :style="!g.is_achieved ? { width: g.progress_percent + '%', backgroundColor: g.color || '#f59e0b' } : { width: '100%' }"
                    ></div>
                </div>

                <!-- Values -->
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-2xl font-bold tabular-nums">
                            {{ formatCents(g.current_amount_cents) }}
                        </p>
                        <p class="text-xs text-slate-500">de {{ formatCents(g.target_amount_cents) }}</p>
                    </div>
                    <p class="text-sm font-semibold tabular-nums" :class="g.is_achieved ? 'text-emerald-600' : 'text-brand-600'">
                        {{ g.progress_percent }}%
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

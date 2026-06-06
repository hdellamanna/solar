<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    recurrences: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    flash: { type: Object, default: () => ({}) },
});

const formatMoney = (cents) => {
    const n = Math.abs(cents) / 100;
    return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (iso) => {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
};

const today = new Date();
const isOverdue = (iso) => iso && new Date(iso + 'T00:00:00') < new Date(today.toDateString() + 'T00:00:00');

const summary = computed(() => {
    const active = props.recurrences.filter(r => r.active).length;
    const monthly = props.recurrences
        .filter(r => r.active)
        .reduce((acc, r) => {
            const cents = Math.abs(r.amount_cents);
            const factor = { daily: 30, weekly: 4, monthly: 1, yearly: 1 / 12 }[r.frequency] || 1;
            return acc + cents * factor;
        }, 0);
    return { active, monthly };
});

const generateNow = (recurrence) => {
    if (!confirm(`Gerar transações pendentes para "${recurrence.description}"?`)) return;
    router.post(route('recurrences.generate-now', recurrence.id), {}, {
        preserveScroll: true,
    });
};

const toggleActive = (recurrence) => {
    router.put(route('recurrences.update', recurrence.id), {
        description: recurrence.description,
        amount_cents: recurrence.amount_cents,
        type: recurrence.type,
        frequency: recurrence.frequency,
        account_id: recurrence.account_id,
        category_id: recurrence.category_id,
        starts_at: recurrence.starts_at,
        ends_at: recurrence.ends_at || null,
        active: !recurrence.active,
    }, {
        preserveScroll: true,
    });
};

const remove = (recurrence) => {
    if (!confirm(`Excluir a recorrência "${recurrence.description}"?`)) return;
    router.delete(route('recurrences.destroy', recurrence.id), { preserveScroll: true });
};

const typeBadge = (type) => {
    const map = {
        income: { label: 'Receita', cls: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' },
        expense: { label: 'Despesa', cls: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' },
        transfer: { label: 'Transf.', cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' },
    };
    return map[type] || map.expense;
};
</script>

<template>
    <Head title="Recorrências" />
    <AuthenticatedLayout title="Recorrências">
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Recorrências</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Regras que geram transações automaticamente.
                    </p>
                </div>
                <Link :href="route('recurrences.create')" class="btn-primary">
                    <span>+ Nova recorrência</span>
                </Link>
            </div>
        </template>

        <div v-if="flash?.success" class="card p-3 mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-sm text-green-700 dark:text-green-300">
            {{ flash.success }}
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <div class="card p-4">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">Regras ativas</p>
                <p class="text-2xl font-bold mt-1">{{ summary.active }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">Equivalente mensal</p>
                <p class="text-2xl font-bold mt-1">R$ {{ formatMoney(summary.monthly) }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">Total de regras</p>
                <p class="text-2xl font-bold mt-1">{{ recurrences.length }}</p>
            </div>
        </div>

        <div v-if="recurrences.length === 0" class="card p-10 text-center">
            <p class="text-5xl mb-3">🔁</p>
            <h2 class="text-lg font-semibold mb-1">Nenhuma recorrência ainda</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                Crie regras para aluguel, salário, assinaturas etc. e o sistema gera as transações por você.
            </p>
            <Link :href="route('recurrences.create')" class="btn-primary inline-block">Criar primeira recorrência</Link>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="r in recurrences" :key="r.id" class="card p-5">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase', typeBadge(r.type).cls]">
                                {{ typeBadge(r.type).label }}
                            </span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 uppercase tracking-wide">
                                {{ r.human_frequency }}
                            </span>
                            <span v-if="!r.active" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 uppercase tracking-wide">
                                Pausada
                            </span>
                        </div>
                        <h3 class="font-semibold mt-1.5 truncate">{{ r.description }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ r.account?.name || '—' }}<span v-if="r.category"> · {{ r.category.icon }} {{ r.category.name }}</span>
                        </p>
                    </div>
                    <p :class="['text-lg font-bold whitespace-nowrap', r.type === 'income' ? 'text-income' : 'text-expense']">
                        {{ r.type === 'income' ? '+' : '-' }} R$ {{ formatMoney(r.amount_cents) }}
                    </p>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800 my-3"></div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 uppercase tracking-wide">Início</p>
                        <p class="font-medium">{{ formatDate(r.starts_at) }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 uppercase tracking-wide">Término</p>
                        <p class="font-medium">{{ r.ends_at ? formatDate(r.ends_at) : 'Sem fim' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 uppercase tracking-wide">Última geração</p>
                        <p class="font-medium">{{ r.last_generated_at ? formatDate(r.last_generated_at) : 'Nunca' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 dark:text-slate-400 uppercase tracking-wide">Próxima execução</p>
                        <p :class="['font-medium', isOverdue(r.next_run_at) ? 'text-amber-600 dark:text-amber-400' : '']">
                            {{ formatDate(r.next_run_at) }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mt-4">
                    <button @click="generateNow(r)" :disabled="!r.active" class="btn-secondary text-xs flex-1 sm:flex-none">
                        ⚡ Gerar agora
                    </button>
                    <button @click="toggleActive(r)" class="btn-secondary text-xs flex-1 sm:flex-none">
                        {{ r.active ? 'Pausar' : 'Ativar' }}
                    </button>
                    <Link :href="route('recurrences.edit', r.id)" class="btn-secondary text-xs flex-1 sm:flex-none">
                        Editar
                    </Link>
                    <button @click="remove(r)" class="btn-secondary text-xs text-red-600 dark:text-red-400 flex-1 sm:flex-none">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

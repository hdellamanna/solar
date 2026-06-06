<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import { formatCents, formatDate } from '@/Composables/useFormat';

const props = defineProps({
    transaction: { type: Object, required: true },
    users: { type: Array, default: () => [] },
});

const tx = computed(() => props.transaction);

const isExpense = computed(() => tx.value.type === 'expense');
const absAmount = computed(() => Math.abs(tx.value.amount_cents));

const splits = computed(() => tx.value.splits || []);
const hasSplits = computed(() => splits.value.length > 0);
const paidSplits = computed(() => splits.value.filter(s => s.is_paid));
const pendingSplits = computed(() => splits.value.filter(s => !s.is_paid));
const totalSplitCents = computed(() => splits.value.reduce((s, x) => s + Number(x.amount_cents), 0));
const totalPaidCents = computed(() => paidSplits.value.reduce((s, x) => s + Number(x.amount_cents), 0));

const userMap = computed(() => {
    const m = {};
    props.users.forEach(u => { m[u.id] = u; });
    return m;
});

function initials(name) {
    if (!name) return '?';
    const parts = name.split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')).toUpperCase();
}

const toggling = ref(null);
async function togglePaid(split) {
    toggling.value = split.id;
    try {
        await router.patch(
            route('transactions.splits.toggle', [tx.value.id, split.id]),
            {},
            { preserveScroll: true, preserveState: false }
        );
    } finally {
        toggling.value = null;
    }
}

function destroy() {
    if (confirm(`Excluir transacao "${tx.value.description}"?`)) {
        router.delete(route('transactions.destroy', tx.value.id));
    }
}
</script>

<template>
    <Head :title="`Transacao: ${tx.description}`" />
    <AuthenticatedLayout :title="`Transacao: ${tx.description}`">
        <div class="max-w-3xl space-y-4">
            <!-- Header card -->
            <div class="card p-5 md:p-6">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                            <span>{{ formatDate(tx.date) }}</span>
                            <span class="px-1.5 py-0.5 rounded text-xs uppercase font-semibold"
                                :class="isExpense ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'">
                                {{ tx.type === 'income' ? 'Receita' : tx.type === 'expense' ? 'Despesa' : 'Transferencia' }}
                            </span>
                            <span v-if="tx.is_pix" class="px-1.5 py-0.5 rounded text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">PIX</span>
                            <span v-if="hasSplits" class="px-1.5 py-0.5 rounded text-xs bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Dividida</span>
                        </div>
                        <h1 class="text-2xl font-bold">{{ tx.description }}</h1>
                        <p v-if="tx.notes" class="text-sm text-slate-500 mt-1">{{ tx.notes }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold tabular-nums" :class="isExpense ? 'text-expense' : 'text-income'">
                            {{ isExpense ? '-' : '+' }}{{ formatCents(absAmount) }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ tx.account?.name }}<span v-if="tx.destination_account"> → {{ tx.destination_account.name }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Link :href="route('transactions.edit', tx.id)" class="btn-ghost">Editar</Link>
                    <button @click="destroy" class="btn-ghost text-expense">Excluir</button>
                    <Link :href="route('transactions.index')" class="btn-ghost">Voltar</Link>
                </div>
            </div>

            <!-- Splits card -->
            <div v-if="hasSplits" class="card p-5 md:p-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Divisao da transacao
                    </h2>
                    <div class="text-xs text-slate-500">
                        {{ paidSplits.length }}/{{ splits.length }} pagos
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 mb-4 overflow-hidden">
                    <div class="bg-income h-full transition-all" :style="{ width: `${splits.length ? (paidSplits.length / splits.length) * 100 : 0}%` }"></div>
                </div>

                <div class="space-y-2">
                    <div v-for="split in splits" :key="split.id"
                        class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
                        :class="split.is_paid ? 'bg-green-50/40 dark:bg-green-900/10' : ''">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-white flex items-center justify-center text-sm font-semibold flex-shrink-0">
                            {{ initials(split.user?.name) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ split.user?.name || 'Usuario #' + split.user_id }}</p>
                            <p class="text-xs text-slate-500">
                                <span v-if="split.category">{{ split.category.icon }} {{ split.category.name }}</span>
                                <span v-else-if="tx.category">{{ tx.category.icon }} {{ tx.category.name }}</span>
                                <span v-else>Sem categoria</span>
                                <span v-if="split.description"> · {{ split.description }}</span>
                            </p>
                            <p v-if="split.paid_by_user_id && split.paid_by_user_id !== split.user_id" class="text-xs text-slate-500 mt-0.5">
                                Pago por: <strong>{{ userMap[split.paid_by_user_id]?.name || 'usuario #' + split.paid_by_user_id }}</strong>
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="font-semibold tabular-nums" :class="isExpense ? 'text-expense' : 'text-income'">
                                {{ isExpense ? '-' : '+' }}{{ formatCents(Math.abs(split.amount_cents)) }}
                            </p>
                            <button
                                @click="togglePaid(split)"
                                :disabled="toggling === split.id"
                                class="text-xs mt-1 px-2 py-1 rounded font-medium transition-colors"
                                :class="split.is_paid ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300' : 'bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300'">
                                <span v-if="toggling === split.id">...</span>
                                <span v-else-if="split.is_paid">✓ Pago</span>
                                <span v-else>Marcar como pago</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 grid grid-cols-3 gap-2 text-center text-sm">
                    <div>
                        <p class="text-xs text-slate-500">Total</p>
                        <p class="font-semibold tabular-nums">{{ formatCents(absAmount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pago</p>
                        <p class="font-semibold text-income tabular-nums">{{ formatCents(Math.abs(totalPaidCents)) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pendente</p>
                        <p class="font-semibold text-expense tabular-nums">{{ formatCents(Math.abs(totalSplitCents - totalPaidCents)) }}</p>
                    </div>
                </div>
            </div>

            <!-- No splits: just show category/meta -->
            <div v-else class="card p-5 md:p-6">
                <p class="text-sm text-slate-500">
                    Esta transacao nao foi dividida.
                    <Link :href="route('transactions.edit', tx.id)" class="text-brand-500 hover:underline">Editar para dividir</Link>
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

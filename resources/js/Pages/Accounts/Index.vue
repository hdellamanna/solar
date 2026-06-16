<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';
import { useFormatType } from '@/Composables/useFormatType';
import { computed } from 'vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    types: { type: Object, default: () => ({}) },
});

const formatAccountType = useFormatType('account');

const destroy = (acc) => {
    if (confirm(`Excluir conta "${acc.name}"?`)) {
        router.delete(route('accounts.destroy', acc.id));
    }
};

// Prefer the localized lookup from `useFormatType` (which reads
// the active locale), but fall back to the `props.types` map
// the controller already sends for backward compat — and finally
// to the raw code so the page never breaks.
const typeLabel = (t) => formatAccountType(t) || props.types[t] || t;
</script>

<template>
    <Head title="Contas" />
    <AuthenticatedLayout title="Minhas contas">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-slate-500">{{ props.accounts.length }} {{ props.accounts.length === 1 ? 'conta' : 'contas' }} cadastradas</p>
            <Link :href="route('accounts.create')" class="btn-primary">+ Nova conta</Link>
        </div>

        <div v-if="props.accounts.length === 0" class="card-elevated p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
            <h3 class="font-semibold mb-1">Crie sua primeira conta</h3>
            <p class="text-sm text-slate-500 mb-4">Adicione contas correntes, cartões, carteiras e também contas multi-moeda (Wise, Nomad, C6 Global, Inter Global).</p>
            <Link :href="route('accounts.create')" class="btn-primary inline-flex">+ Nova conta</Link>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
            <div v-for="acc in props.accounts" :key="acc.id" class="card-elevated p-4 md:p-5 group">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: acc.color || '#f59e0b' }"></span>
                        <div class="min-w-0">
                            <p class="font-semibold truncate tracking-tight">{{ acc.name }}</p>
                            <p class="text-xs text-slate-500">{{ typeLabel(acc.type) }}</p>
                        </div>
                    </div>
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                        <Link :href="route('accounts.edit', acc.id)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Editar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </Link>
                        <button @click="destroy(acc)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-expense" title="Excluir">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 0H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Main balance (home currency of the account) -->
                <p class="text-2xl font-bold tabular-nums tracking-tight" :class="acc.balance_cents >= 0 ? '' : 'text-expense'">
                    {{ formatCents(acc.balance_cents) }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">Saldo em {{ acc.currency || 'BRL' }}</p>

                <!-- Multi-currency sub-balances (FASE 6A) -->
                <div v-if="acc.is_multi_currency && acc.balances?.length" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-1">
                    <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold mb-1">Sub-saldos</p>
                    <div v-for="b in acc.balances" :key="b.currency" class="flex items-center justify-between text-xs">
                        <span class="text-slate-600 dark:text-slate-400">{{ b.currency }}</span>
                        <span class="tabular-nums font-medium" :class="b.balance_cents >= 0 ? '' : 'text-expense'">
                            {{ formatCents(b.balance_cents) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

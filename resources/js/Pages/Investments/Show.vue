<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';

const props = defineProps({
    investment: { type: Object, required: true },
});

const destroy = () => {
    if (confirm(`Remover o investimento "${props.investment.name}"?`)) {
        router.delete(route('investments.destroy', props.investment.id));
    }
};

const plClass = (i) => {
    if (!i.has_current_price) return 'text-slate-400';
    if (i.profit_loss_cents > 0) return 'text-income';
    if (i.profit_loss_cents < 0) return 'text-expense';
    return 'text-slate-500';
};
const plSign = (i) => {
    if (i.profit_loss_cents > 0) return '+';
    if (i.profit_loss_cents < 0) return '-';
    return '';
};
</script>

<template>
    <Head :title="investment.name" />
    <AuthenticatedLayout :title="investment.name">
        <div class="card-elevated p-6 md:p-8 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        <span
                            class="inline-block text-[10px] uppercase tracking-wider font-semibold px-2 py-0.5 rounded-full"
                            :style="{ backgroundColor: (investment.type_color || '#64748b') + '1a', color: investment.type_color || '#64748b' }"
                        >{{ investment.type_label }}</span>
                        <span v-if="investment.ticker" class="text-[11px] text-slate-500 font-mono tracking-wide">{{ investment.ticker }}</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-bold tracking-tight">{{ investment.name }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('investments.edit', investment.id)" class="btn-ghost">Editar</Link>
                    <button @click="destroy" class="btn-ghost text-expense">Excluir</button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Total investido</p>
                    <p class="text-2xl md:text-3xl font-semibold tabular-nums tracking-tight mt-2 text-slate-700 dark:text-slate-300">
                        {{ formatCents(investment.total_invested_cents) }}
                    </p>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ investment.formatted_quantity }} × {{ investment.currency_symbol }} {{ investment.average_price_decimal.toFixed(2).replace('.', ',') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Valor atual</p>
                    <p class="text-3xl md:text-4xl font-bold tabular-nums tracking-tight mt-2">
                        {{ investment.has_current_price ? formatCents(investment.current_value_cents) : '—' }}
                    </p>
                    <p v-if="investment.has_current_price" class="text-xs text-slate-500 mt-1">
                        {{ investment.formatted_quantity }} × {{ investment.currency_symbol }} {{ (investment.current_price_decimal ?? 0).toFixed(2).replace('.', ',') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Lucro / prejuízo</p>
                    <p
                        class="text-3xl md:text-4xl font-bold tabular-nums tracking-tight mt-2"
                        :class="plClass(investment)"
                    >
                        <span v-if="investment.profit_loss_cents > 0">▲</span>
                        <span v-else-if="investment.profit_loss_cents < 0">▼</span>
                        {{ plSign(investment) }}{{ formatCents(Math.abs(investment.profit_loss_cents)) }}
                    </p>
                    <p v-if="investment.profit_loss_percent !== null && investment.profit_loss_percent !== undefined" class="text-xs text-slate-500 mt-1">
                        {{ (investment.profit_loss_percent ?? 0) > 0 ? '+' : '' }}{{ (investment.profit_loss_percent ?? 0).toFixed(2).replace('.', ',') }}% sobre o custo
                    </p>
                    <p v-else class="text-xs text-slate-400 mt-1">Defina o preço atual para calcular.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
            <div class="card-elevated p-5 md:p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Detalhes</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Quantidade</dt>
                        <dd class="font-medium tabular-nums">{{ investment.formatted_quantity }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Preço médio</dt>
                        <dd class="font-medium tabular-nums">{{ investment.currency_symbol }} {{ investment.average_price_decimal.toFixed(2).replace('.', ',') }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Preço atual</dt>
                        <dd class="font-medium tabular-nums">
                            {{ investment.has_current_price ? `${investment.currency_symbol} ${(investment.current_price_decimal ?? 0).toFixed(2).replace('.', ',')}` : '—' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Moeda</dt>
                        <dd class="font-medium">{{ investment.currency }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Data de compra</dt>
                        <dd class="font-medium">{{ investment.acquired_at ? formatDate(investment.acquired_at) : '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card-elevated p-5 md:p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Notas</h3>
                <p v-if="investment.notes" class="text-sm whitespace-pre-line">{{ investment.notes }}</p>
                <p v-else class="text-sm text-slate-400">Nenhuma nota.</p>
            </div>
        </div>

        <div class="mt-6">
            <Link :href="route('investments.index')" class="text-sm text-brand-600 hover:underline">← Voltar para investimentos</Link>
        </div>
    </AuthenticatedLayout>
</template>

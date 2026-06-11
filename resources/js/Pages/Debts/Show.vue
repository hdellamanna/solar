<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents, formatDate } from '@/Composables/useFormat';

const props = defineProps({
    debt: { type: Object, required: true },
});

const simulateOpen = ref(false);
const simulateLoading = ref(false);
const simulation = ref(null);
const simulateError = ref(null);
const chosenStrategy = ref(props.debt.payoff_strategy);

const monthsLabel = (n) => {
    if (!n) return '—';
    if (n < 12) return `${n} ${n === 1 ? 'mês' : 'meses'}`;
    const years = Math.floor(n / 12);
    const rest = n % 12;
    if (rest === 0) return `${years} ${years === 1 ? 'ano' : 'anos'}`;
    return `${years} ${years === 1 ? 'ano' : 'anos'} e ${rest} ${rest === 1 ? 'mês' : 'meses'}`;
};

const openSimulate = async () => {
    simulateOpen.value = true;
    simulateLoading.value = true;
    simulateError.value = null;
    simulation.value = null;
    try {
        const res = await fetch(route('debts.simulate', props.debt.id) + '?strategy=' + chosenStrategy.value, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || `Falha ao simular (HTTP ${res.status}).`);
        }
        simulation.value = await res.json();
    } catch (e) {
        simulateError.value = e.message || 'Erro inesperado.';
    } finally {
        simulateLoading.value = false;
    }
};

const closeSimulate = () => {
    simulateOpen.value = false;
    simulation.value = null;
    simulateError.value = null;
};

const switchStrategy = (s) => {
    chosenStrategy.value = s;
    openSimulate();
};

const confirmMarkPaidOpen = ref(false);
const markingPaid = ref(false);

const markAsPaidOff = async () => {
    markingPaid.value = true;
    try {
        await fetch(route('debts.mark-paid', props.debt.id), {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });
        confirmMarkPaidOpen.value = false;
        router.reload();
    } finally {
        markingPaid.value = false;
    }
};

const paidOffDisabled = computed(() => props.debt.total_balance_cents > 0);
</script>

<template>
    <Head :title="debt.creditor" />
    <AuthenticatedLayout :title="debt.creditor">
        <div class="max-w-4xl">
            <!-- Hero card -->
            <div class="card-elevated p-6 md:p-8 mb-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">{{ debt.description || 'Dívida' }}</p>
                        <h2 class="text-2xl md:text-3xl font-bold tracking-tight mt-1">{{ debt.creditor }}</h2>
                    </div>
                    <span
                        :class="[
                            'px-2.5 py-1 rounded-full text-[11px] font-semibold tracking-wide self-start',
                            debt.payoff_strategy === 'price'
                                ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'
                                : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                        ]"
                    >{{ debt.payoff_strategy === 'price' ? 'Price' : 'SAC' }}</span>
                </div>

                <p class="text-5xl md:text-6xl font-bold tabular-nums tracking-tight text-expense">
                    {{ debt.total_balance_formatted }}
                </p>
                <p class="text-sm text-slate-500 mt-1">saldo atual</p>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Parcela</p>
                        <p class="text-lg font-bold tabular-nums mt-1">{{ debt.monthly_payment_formatted }}</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Taxa a.a.</p>
                        <p class="text-lg font-bold tabular-nums mt-1">{{ debt.interest_rate_percent.toFixed(2) }}%</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Início</p>
                        <p class="text-lg font-bold tabular-nums mt-1">{{ formatDate(debt.start_date) }}</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <button @click="openSimulate" class="btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        Ver simulação de amortização
                    </button>
                    <button
                        v-if="!debt.is_paid_off"
                        @click="confirmMarkPaidOpen = true"
                        :disabled="paidOffDisabled"
                        :title="paidOffDisabled ? 'Zere o saldo antes de quitar' : 'Marcar como quitada'"
                        :class="['px-4 py-2 rounded-xl border text-sm font-medium transition-colors',
                            paidOffDisabled
                                ? 'border-slate-200 text-slate-400 cursor-not-allowed dark:border-slate-700'
                                : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
                        ]"
                    >Marcar como quitada</button>
                    <Link :href="route('debts.edit', debt.id)" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm font-medium transition-colors">
                        Editar
                    </Link>
                </div>

                <p v-if="debt.is_paid_off" class="mt-4 text-sm text-emerald-600 dark:text-emerald-300 font-medium">
                    ✓ Quitada{{ debt.paid_off_at ? ' em ' + formatDate(debt.paid_off_at.slice(0, 10)) : '' }}.
                </p>
            </div>

            <!-- Notes -->
            <div v-if="debt.notes" class="card-elevated p-5">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-medium mb-2">Notas</p>
                <p class="text-sm whitespace-pre-line text-slate-700 dark:text-slate-300">{{ debt.notes }}</p>
            </div>
        </div>

        <!-- Simulate modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="simulateOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="closeSimulate">
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                        <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h3 class="text-lg font-semibold">Simulação de amortização</h3>
                                <p class="text-xs text-slate-500 mt-0.5">{{ debt.creditor }} · {{ debt.total_balance_formatted }} · {{ debt.interest_rate_percent.toFixed(2) }}% a.a.</p>
                            </div>
                            <button @click="closeSimulate" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-5 overflow-y-auto flex-1">
                            <!-- Strategy switch -->
                            <div class="flex items-center gap-2 mb-4">
                                <span class="text-xs uppercase tracking-wider text-slate-500 font-medium">Estratégia:</span>
                                <div class="inline-flex rounded-xl border border-slate-200 dark:border-slate-700 p-1">
                                    <button
                                        v-for="s in ['sac', 'price']"
                                        :key="s"
                                        @click="switchStrategy(s)"
                                        :class="[
                                            'px-3 py-1 rounded-lg text-xs font-semibold transition-colors',
                                            chosenStrategy === s
                                                ? (s === 'price' ? 'bg-violet-500 text-white' : 'bg-blue-500 text-white')
                                                : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-100',
                                        ]"
                                    >{{ s === 'price' ? 'Price' : 'SAC' }}</button>
                                </div>
                            </div>

                            <div v-if="simulateLoading" class="py-12 text-center text-sm text-slate-500">Calculando…</div>
                            <div v-else-if="simulateError" class="py-12 text-center text-sm text-expense">{{ simulateError }}</div>
                            <div v-else-if="simulation">
                                <!-- Summary row -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                        <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Duração</p>
                                        <p class="text-lg font-bold tabular-nums mt-1">{{ monthsLabel(simulation.months) }}</p>
                                    </div>
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                        <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Total de juros</p>
                                        <p class="text-lg font-bold tabular-nums mt-1 text-expense">{{ formatCents(simulation.total_interest_cents) }}</p>
                                    </div>
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                        <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Total pago</p>
                                        <p class="text-lg font-bold tabular-nums mt-1">{{ formatCents(simulation.total_paid_cents) }}</p>
                                    </div>
                                </div>

                                <div v-if="simulation.failed" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 text-sm text-expense">
                                    ⚠️ A parcela mensal não cobre os juros desta dívida. O saldo cresce mês a mês.
                                </div>
                                <div v-else-if="simulation.cap_reached" class="mb-4 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-sm text-amber-700 dark:text-amber-300">
                                    ⚠️ Simulação limitada a 600 meses (50 anos). Considere renegociar.
                                </div>

                                <!-- Schedule table -->
                                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                                    <table class="w-full text-sm tabular-nums">
                                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 font-medium">
                                            <tr>
                                                <th class="text-left px-3 py-2">Mês</th>
                                                <th class="text-left px-3 py-2">Vencimento</th>
                                                <th class="text-right px-3 py-2">Juros</th>
                                                <th class="text-right px-3 py-2">Amortização</th>
                                                <th class="text-right px-3 py-2">Parcela</th>
                                                <th class="text-right px-3 py-2">Saldo</th>
                                                <th class="text-right px-3 py-2">Total pago</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <tr
                                                v-for="row in simulation.schedule"
                                                :key="row.month"
                                                :class="row.remaining_balance_cents === 0 ? 'bg-emerald-50 dark:bg-emerald-900/20 font-semibold' : ''"
                                            >
                                                <td class="px-3 py-2">{{ row.month }}</td>
                                                <td class="px-3 py-2">{{ formatDate(row.due_date) }}</td>
                                                <td class="px-3 py-2 text-right text-expense">{{ formatCents(row.interest_cents) }}</td>
                                                <td class="px-3 py-2 text-right">{{ formatCents(row.principal_cents) }}</td>
                                                <td class="px-3 py-2 text-right font-medium">{{ formatCents(row.payment_cents) }}</td>
                                                <td class="px-3 py-2 text-right">{{ formatCents(row.remaining_balance_cents) }}</td>
                                                <td class="px-3 py-2 text-right text-slate-500">{{ formatCents(row.cumulative_paid_cents) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                            <button @click="closeSimulate" class="btn-ghost">Fechar</button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Confirm mark as paid off -->
            <Transition
                enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="confirmMarkPaidOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="confirmMarkPaidOpen = false">
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md p-6">
                        <h3 class="text-lg font-semibold">Marcar como quitada?</h3>
                        <p class="text-sm text-slate-500 mt-2">Esta ação vai registrar a data de quitação de <strong>{{ debt.creditor }}</strong>. Você ainda poderá consultar no histórico.</p>
                        <div class="mt-6 flex justify-end gap-2">
                            <button @click="confirmMarkPaidOpen = false" class="btn-ghost">Cancelar</button>
                            <button @click="markAsPaidOff" :disabled="markingPaid" class="btn-primary">
                                {{ markingPaid ? 'Salvando…' : 'Sim, quitar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

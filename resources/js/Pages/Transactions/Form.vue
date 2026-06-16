<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SplitEditor from '@/Components/SplitEditor.vue';
import { useAiCategorize } from '@/Composables/useAiCategorize';
import { useFormatType } from '@/Composables/useFormatType';
import { localizedNameOf } from '@/Composables/useLocalizedName';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    transaction: { type: Object, default: null },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const ai = useAiCategorize();
const formatType = useFormatType('transaction');
const aiToast = ref(null);
let aiToastTimer = null;

const form = useForm({
    type: props.transaction?.type || 'expense',
    account_id: props.transaction?.account_id || (props.accounts[0]?.id ?? ''),
    destination_account_id: props.transaction?.destination_account_id || '',
    category_id: props.transaction?.category_id || '',
    amount: props.transaction ? Math.abs(props.transaction.amount_cents) / 100 : '',
    currency: props.transaction?.currency || (props.accounts[0]?.currency || 'BRL'),
    date: props.transaction?.date?.split('T')[0] || new Date().toISOString().split('T')[0],
    description: props.transaction?.description || '',
    notes: props.transaction?.notes || '',
    status: props.transaction?.status || 'paid',
    is_pix: props.transaction?.is_pix || false,
    pix_key: props.transaction?.pix_key || '',
    splits: props.transaction?.splits?.length
        ? props.transaction.splits.map(s => ({
            user_id: s.user_id,
            amount_cents: s.amount_cents,
            description: s.description,
            category_id: s.category_id,
        }))
        : [],
});

const filteredCategories = computed(() => {
    return props.categories.filter(c => c.type === form.type);
});

const selectedAccount = computed(() => {
    return props.accounts.find(a => a.id === form.account_id) || null;
});

const isAccountMultiCurrency = computed(() => {
    return selectedAccount.value?.type === 'multi_currency';
});

const availableCurrencies = computed(() => {
    if (!isAccountMultiCurrency.value) return [selectedAccount.value?.currency || 'BRL'];
    const set = new Set([selectedAccount.value?.currency || 'BRL']);
    (selectedAccount.value?.balances || []).forEach(b => set.add(b.currency));
    return Array.from(set);
});

watch(() => form.account_id, () => {
    const acc = props.accounts.find(a => a.id === form.account_id);
    if (acc) {
        form.currency = acc.currency || 'BRL';
    }
});

watch(() => form.type, () => {
    form.category_id = '';
});

const totalCents = computed(() => {
    const amt = parseFloat(form.amount || 0);
    if (!amt || isNaN(amt)) return 0;
    const cents = Math.round(amt * 100);
    return (form.type === 'expense' || form.type === 'transfer') ? -cents : cents;
});

const splitError = ref(null);
const splitsValid = computed(() => {
    if (!form.splits || form.splits.length === 0) return true; // not enabled
    const sum = form.splits.reduce((s, p) => s + Number(p.amount_cents || 0), 0);
    return sum === Math.abs(totalCents.value);
});

function onSplitsUpdate(v) {
    form.splits = v;
}
function onSplitsError(e) {
    splitError.value = e;
}

const submit = () => {
    if (!splitsValid.value) {
        splitError.value = 'A soma das partes nao confere com o total da transacao.';
        return;
    }
    if (props.transaction) {
        form.put(route('transactions.update', props.transaction.id));
    } else {
        form.post(route('transactions.store'));
    }
};

const showAiSuggest = computed(() => {
    const user = $page.props?.auth?.user;
    return Boolean(user?.use_ai_categorize) && (form.description || '').trim().length >= 3;
});

const aiConfidencePct = (confidence) => {
    if (confidence === null || confidence === undefined) return '?';
    return Math.round(Number(confidence) * 100);
};

async function suggestCategory() {
    aiToast.value = null;
    const { ok, payload, message } = await ai.suggest(form.description);
    if (!ok) {
        aiToast.value = { kind: 'error', text: message || 'Não foi possível sugerir uma categoria.' };
        scheduleToastClear();
        return;
    }
    form.category_id = payload.category_id;
    aiToast.value = {
        kind: 'success',
        text: `Categoria sugerida: ${payload.category_name} (${aiConfidencePct(payload.confidence)}% confiança)`,
    };
    scheduleToastClear();
}

function scheduleToastClear() {
    if (aiToastTimer) clearTimeout(aiToastTimer);
    aiToastTimer = setTimeout(() => { aiToast.value = null; }, 5000);
}
</script>

<template>
    <Head :title="props.transaction ? 'Editar transação' : 'Nova transação'" />
    <AuthenticatedLayout :title="props.transaction ? 'Editar transação' : 'Nova transação'">
        <form @submit.prevent="submit" class="max-w-2xl space-y-4">
            <div class="card p-5 md:p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Tipo</label>
                    <div class="grid grid-cols-3 gap-2">
                        <label :class="['cursor-pointer border-2 rounded-xl p-3 text-center transition-colors', form.type === 'expense' ? 'border-expense bg-red-50 dark:bg-red-900/20' : 'border-slate-200 dark:border-slate-700']">
                            <input v-model="form.type" type="radio" value="expense" class="sr-only">
                            <span class="text-2xl">⬇️</span>
                            <p class="text-sm font-medium mt-1">{{ formatType('expense') }}</p>
                        </label>
                        <label :class="['cursor-pointer border-2 rounded-xl p-3 text-center transition-colors', form.type === 'income' ? 'border-income bg-green-50 dark:bg-green-900/20' : 'border-slate-200 dark:border-slate-700']">
                            <input v-model="form.type" type="radio" value="income" class="sr-only">
                            <span class="text-2xl">⬆️</span>
                            <p class="text-sm font-medium mt-1">{{ formatType('income') }}</p>
                        </label>
                        <label :class="['cursor-pointer border-2 rounded-xl p-3 text-center transition-colors', form.type === 'transfer' ? 'border-info bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-700']">
                            <input v-model="form.type" type="radio" value="transfer" class="sr-only">
                            <span class="text-2xl">↔️</span>
                            <p class="text-sm font-medium mt-1">{{ formatType('transfer') }}</p>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Descrição</label>
                    <input v-model="form.description" type="text" placeholder="Ex: Almoço, Salário, Conta de luz" class="input" required>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-1">Valor</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">{{ form.currency }}</span>
                            <input v-model="form.amount" type="number" step="0.01" min="0.01" placeholder="0,00" class="input pl-14 text-lg font-semibold" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Moeda</label>
                        <select v-model="form.currency" class="input" :disabled="!isAccountMultiCurrency">
                            <option v-for="c in availableCurrencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Data</label>
                        <input v-model="form.date" type="date" class="input" required>
                    </div>
                </div>
                <p v-if="isAccountMultiCurrency" class="text-xs text-slate-500 -mt-2">
                    Conta multi-moeda: a cotação do dia é capturada automaticamente e gravada na transação.
                </p>

                <div v-if="form.type !== 'transfer'">
                    <label class="block text-sm font-medium mb-1">Conta</label>
                    <select v-model="form.account_id" class="input" required>
                        <option value="">Selecione...</option>
                        <option v-for="a in props.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>

                <div v-if="form.type !== 'transfer'">
                    <label class="block text-sm font-medium mb-1">Categoria</label>
                    <div class="flex items-center gap-2">
                        <select v-model="form.category_id" class="input flex-1">
                            <option value="">Sem categoria</option>
                            <option v-for="c in filteredCategories" :key="c.id" :value="c.id">{{ c.icon }} {{ localizedNameOf(c, $page.props.app?.locale || 'pt-BR') }}</option>
                        </select>
                        <button
                            v-if="showAiSuggest"
                            type="button"
                            class="btn-secondary shrink-0"
                            :disabled="ai.loading"
                            @click="suggestCategory"
                            data-testid="ai-suggest-button"
                        >
                            <span v-if="ai.loading" class="inline-flex items-center gap-1">
                                <svg class="w-3 h-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40 60" stroke-linecap="round" /></svg>
                                ...
                            </span>
                            <span v-else>✨ Sugerir categoria</span>
                        </button>
                    </div>
                    <p
                        v-if="aiToast"
                        :class="[
                            'mt-2 text-xs',
                            aiToast.kind === 'success' ? 'text-brand-600 dark:text-brand-400' : 'text-expense',
                        ]"
                        data-testid="ai-suggest-toast"
                    >
                        {{ aiToast.text }}
                    </p>
                </div>

                <div v-if="form.type === 'transfer'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">De (origem)</label>
                        <select v-model="form.account_id" class="input" required>
                            <option value="">Selecione...</option>
                            <option v-for="a in props.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Para (destino)</label>
                        <select v-model="form.destination_account_id" class="input" required>
                            <option value="">Selecione...</option>
                            <option v-for="a in props.accounts" :key="a.id" :value="a.id" :disabled="a.id == form.account_id">{{ a.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select v-model="form.status" class="input">
                        <option value="paid">Pago</option>
                        <option value="pending">Pendente</option>
                    </select>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800 pt-4">
                    <label class="flex items-center gap-2 mb-2">
                        <input v-model="form.is_pix" type="checkbox" class="rounded text-brand-500 focus:ring-brand-500">
                        <span class="text-sm font-medium">É PIX?</span>
                    </label>
                    <input v-if="form.is_pix" v-model="form.pix_key" type="text" placeholder="Chave PIX (opcional)" class="input">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Observações</label>
                    <textarea v-model="form.notes" rows="2" placeholder="Notas opcionais..." class="input"></textarea>
                </div>
            </div>

            <div v-if="form.type !== 'transfer'" class="card p-5 md:p-6 space-y-3">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <h2 class="font-semibold">Dividir transacao</h2>
                </div>
                <SplitEditor
                    :model-value="form.splits"
                    :total-cents="totalCents"
                    :users="props.users"
                    :categories="filteredCategories"
                    :current-user-id="$page.props.auth.user.id"
                    @update:model-value="onSplitsUpdate"
                    @error="onSplitsError"
                />
            </div>

            <div v-if="Object.keys(form.errors).length > 0 || splitError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-sm">
                <p v-for="(err, field) in form.errors" :key="field" class="text-expense">{{ err }}</p>
                <p v-if="splitError" class="text-expense">{{ splitError }}</p>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="btn-primary" :disabled="form.processing || !splitsValid">
                    <span v-if="form.processing">Salvando...</span>
                    <span v-else>{{ props.transaction ? 'Atualizar' : 'Criar transação' }}</span>
                </button>
                <Link :href="route('transactions.index')" class="btn-ghost">Cancelar</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

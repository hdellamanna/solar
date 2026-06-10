<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';
import {
    classifyPixKey,
    pixKeyTypeInfo,
    maskPixKey,
    buildMockBrCode,
} from '@/Composables/usePix';

const props = defineProps({
    recent: { type: Array, default: () => [] },
    top_keys: { type: Array, default: () => [] },
    saved_keys: { type: Array, default: () => [] },
    month_totals: { type: Object, default: () => ({}) },
});

// --- BR Code generator modal ------------------------------------------------
const generatorOpen = ref(false);
const generatorKey = ref('');
const generatorAmount = ref('');
const generatorTxid = ref('');
const generatedCode = ref('');
const copied = ref(false);

const generatorKeyType = computed(() => classifyPixKey(generatorKey.value));
const generatorKeyValid = computed(() => generatorKeyType.value !== null);
const generatorAmountCents = computed(() => {
    const v = parseFloat(generatorAmount.value);
    return isNaN(v) || v <= 0 ? 0 : Math.round(v * 100);
});

function openGenerator(prefillKey = '') {
    generatorKey.value = prefillKey;
    generatorAmount.value = '';
    generatorTxid.value = '';
    generatedCode.value = '';
    copied.value = false;
    generatorOpen.value = true;
}

function closeGenerator() {
    generatorOpen.value = false;
}

function generate() {
    if (!generatorKeyValid.value) return;
    generatedCode.value = buildMockBrCode({
        key: generatorKey.value.trim(),
        type: generatorKeyType.value,
        amountCents: generatorAmountCents.value,
        txid: generatorTxid.value.trim() || '***',
    });
    copied.value = false;
}

async function copyCode() {
    if (!generatedCode.value) return;
    try {
        await navigator.clipboard.writeText(generatedCode.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1800);
    } catch (e) {
        // Fallback: select the text
        const ta = document.createElement('textarea');
        ta.value = generatedCode.value;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1800);
    }
}
</script>

<template>
    <Head title="PIX" />
    <AuthenticatedLayout title="PIX">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6 md:mb-8">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold tracking-tight">PIX</h2>
                <p class="text-sm text-slate-500 mt-1">Receba, envie e copie chaves em um só lugar.</p>
            </div>
            <button @click="openGenerator('')" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                Gerar BR Code
            </button>
        </div>

        <!-- Month totals (3 cards Apple-style) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 md:mb-8">
            <div class="card-elevated p-5">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Recebido no mês</p>
                <p class="text-2xl md:text-3xl font-bold tabular-nums tracking-tight text-emerald-600 mt-2">
                    {{ formatCents(props.month_totals.received_cents) }}
                </p>
            </div>
            <div class="card-elevated p-5">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Enviado no mês</p>
                <p class="text-2xl md:text-3xl font-bold tabular-nums tracking-tight text-rose-600 mt-2">
                    {{ formatCents(Math.abs(props.month_totals.sent_cents)) }}
                </p>
            </div>
            <div class="card-elevated p-5">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Transações no mês</p>
                <p class="text-2xl md:text-3xl font-bold tabular-nums tracking-tight mt-2">
                    {{ props.month_totals.count }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 md:gap-6">
            <!-- Left: Recent PIX (2/3) -->
            <div class="lg:col-span-2 card-elevated p-5 md:p-6">
                <h2 class="font-semibold tracking-tight mb-4">Movimentações recentes</h2>
                <div v-if="props.recent.length === 0" class="text-center py-10 text-sm text-slate-500">
                    Nenhum PIX registrado ainda. Marque transações como PIX ao criá-las.
                </div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="t in props.recent" :key="t.id" class="py-3 flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-base shrink-0"
                            :class="t.type === 'income' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600' : 'bg-rose-100 dark:bg-rose-900/30 text-rose-600'"
                        >
                            {{ t.type === 'income' ? '⬇️' : '⬆️' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ t.description || 'PIX' }}</p>
                            <p class="text-xs text-slate-500 truncate">
                                {{ t.account?.name }}<span v-if="t.pix_key"> · {{ maskPixKey(t.pix_key) }}</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold tabular-nums" :class="t.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
                                {{ t.type === 'income' ? '+' : '-' }}{{ formatCents(Math.abs(t.amount_cents)) }}
                            </p>
                            <p class="text-xs text-slate-400 tabular-nums">{{ new Date(t.date).toLocaleDateString('pt-BR') }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Right: keys (1/3) -->
            <div class="space-y-4">
                <!-- Saved keys -->
                <div class="card-elevated p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold tracking-tight">Minhas chaves</h2>
                    </div>
                    <div v-if="props.saved_keys.length === 0" class="text-sm text-slate-500 text-center py-4">
                        Nenhuma chave salva.
                    </div>
                    <ul v-else class="space-y-2">
                        <li v-for="k in props.saved_keys" :key="k.id" class="flex items-center gap-3 group">
                            <span class="text-lg">{{ pixKeyTypeInfo(k.type).icon }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">
                                    {{ k.label }}
                                    <span v-if="k.is_primary" class="ml-1 text-[10px] uppercase tracking-wider bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 px-1.5 py-0.5 rounded">Principal</span>
                                </p>
                                <p class="text-xs text-slate-500 truncate tabular-nums">{{ k.key }}</p>
                            </div>
                            <button
                                @click="openGenerator(k.key)"
                                class="opacity-0 group-hover:opacity-100 transition-opacity text-xs text-brand-600 hover:underline"
                            >Gerar</button>
                        </li>
                    </ul>
                </div>

                <!-- Top used keys -->
                <div v-if="props.top_keys.length" class="card-elevated p-5">
                    <h2 class="font-semibold tracking-tight mb-3">Mais usadas</h2>
                    <ul class="space-y-2.5">
                        <li v-for="k in props.top_keys" :key="k.key" class="flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate tabular-nums">{{ maskPixKey(k.key) }}</p>
                                <p class="text-xs text-slate-500">{{ k.count }}x · última {{ new Date(k.last_used_at).toLocaleDateString('pt-BR') }}</p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums">{{ formatCents(Math.abs(k.total_cents)) }}</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- BR Code generator modal (Apple-style sheet) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="generatorOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="closeGenerator">
                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
                        enter-to-class="translate-y-0 sm:scale-100 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0 sm:scale-100 opacity-100"
                        leave-to-class="translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
                    >
                        <div class="bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-3xl w-full sm:max-w-md shadow-2xl">
                            <!-- Drag handle (mobile) -->
                            <div class="sm:hidden flex justify-center pt-3 pb-1">
                                <div class="w-10 h-1 rounded-full bg-slate-300 dark:bg-slate-700"></div>
                            </div>

                            <div class="p-6 sm:p-8">
                                <div class="flex items-center justify-between mb-5">
                                    <h3 class="text-lg font-semibold tracking-tight">Gerar BR Code</h3>
                                    <button @click="closeGenerator" class="p-1 -m-1 text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-2">Chave PIX</label>
                                        <input
                                            v-model="generatorKey"
                                            @input="generate"
                                            type="text"
                                            placeholder="CPF, e-mail, telefone ou chave EVP"
                                            class="input"
                                            :class="generatorKey && !generatorKeyValid ? 'border-expense' : ''"
                                        />
                                        <p v-if="generatorKey && !generatorKeyValid" class="text-xs text-expense mt-1.5">Chave inválida. Verifica o formato.</p>
                                        <p v-else-if="generatorKeyValid" class="text-xs text-slate-500 mt-1.5 flex items-center gap-1.5">
                                            <span>{{ pixKeyTypeInfo(generatorKeyType).icon }}</span>
                                            <span>{{ pixKeyTypeInfo(generatorKeyType).label }} detectado</span>
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-2">Valor (opcional)</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">R$</span>
                                            <input
                                                v-model="generatorAmount"
                                                @input="generate"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                placeholder="0,00"
                                                class="input pl-10 tabular-nums"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-2">Identificador (opcional)</label>
                                        <input
                                            v-model="generatorTxid"
                                            @input="generate"
                                            type="text"
                                            maxlength="25"
                                            placeholder="Ex: aluguel-junho"
                                            class="input"
                                        />
                                    </div>

                                    <!-- Output -->
                                    <div v-if="generatedCode" class="rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4">
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-medium mb-2">BR Code (mock)</p>
                                        <p class="text-xs font-mono break-all text-slate-700 dark:text-slate-300 leading-relaxed">{{ generatedCode }}</p>
                                        <button @click="copyCode" class="mt-3 w-full btn-primary text-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                            {{ copied ? 'Copiado!' : 'Copiar BR Code' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

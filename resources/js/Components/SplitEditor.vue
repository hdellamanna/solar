<script setup>
import { ref, computed, watch } from 'vue';
import { useSplit, formatSplitCents, validateSplit } from '@/Composables/useSplit';

const props = defineProps({
    modelValue: { type: Array, default: () => [] }, // payload for backend: [{ user_id, amount_cents, ... }]
    totalCents: { type: Number, required: true }, // transaction.amount_cents (signed)
    users: { type: Array, default: () => [] }, // all users available to split with
    categories: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue', 'error']);

const enabled = ref(props.modelValue && props.modelValue.length > 0);
const totalAbsCents = computed(() => Math.abs(Number(props.totalCents) || 0));

// Local state seeded from props.modelValue (only when enabled flips on)
const seeded = ref(JSON.parse(JSON.stringify(props.modelValue || [])));
const totalCentsRef = computed(() => totalAbsCents.value);

const {
    splitMode,
    participants,
    addParticipant,
    removeParticipant,
    recompute,
    totalAllocated,
    isBalanced,
    difference,
    percentageSum,
    payload,
} = useSplit({
    totalCents: totalCentsRef,
    participants: enabled.value ? seeded.value : [],
    mode: 'equal',
});

// Available users to add (everyone not already in the split)
const availableUsers = computed(() => {
    const ids = new Set(participants.value.map(p => p.user_id));
    return props.users.filter(u => !ids.has(u.id));
});

const selectedNewUserId = ref('');

function enableSplit() {
    enabled.value = true;
    if (participants.value.length === 0) {
        // seed with current user
        const me = props.users.find(u => u.id === props.currentUserId);
        if (me) {
            addParticipant(me);
        }
    }
    recompute();
    sync();
}

function disableSplit() {
    enabled.value = false;
    participants.value = [];
    emit('update:modelValue', []);
}

function addFromSelect() {
    const u = props.users.find(x => x.id === Number(selectedNewUserId.value));
    if (u) {
        addParticipant(u);
        selectedNewUserId.value = '';
        sync();
    }
}

function removeAt(idx) {
    removeParticipant(idx);
    sync();
}

function sync() {
    const out = payload();
    const err = validateSplit(totalAbsCents.value, out);
    emit('update:modelValue', out);
    emit('error', err);
}

watch(() => participants.value.map(p => p.amount_cents).join(','), () => sync());
watch(splitMode, () => {
    // when switching mode, reset percentages if needed
    if (splitMode.value === 'percentage') {
        participants.value.forEach(p => {
            if (p.percentage == null) p.percentage = +(100 / participants.value.length).toFixed(2);
        });
    }
    recompute();
    sync();
});
watch(enabled, (v) => {
    if (!v) {
        emit('update:modelValue', []);
        emit('error', null);
    }
});

// Seed from modelValue if it already has parts (edit mode)
watch(() => props.modelValue, (v) => {
    if (v && v.length && !enabled.value) {
        enabled.value = true;
    }
});

const totalAllocatedAbs = computed(() => Math.abs(totalAllocated.value));
const diffAbs = computed(() => Math.abs(difference.value));
</script>

<template>
    <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 space-y-3">
        <label class="flex items-center gap-2 cursor-pointer">
            <input
                type="checkbox"
                :checked="enabled"
                @change="enabled ? disableSplit() : enableSplit()"
                class="rounded text-brand-500 focus:ring-brand-500"
            >
            <span class="text-sm font-medium">Dividir com outras pessoas</span>
        </label>

        <div v-if="enabled" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1 text-slate-500">Modo de divisao</label>
                    <select v-model="splitMode" class="input">
                        <option value="equal">Igual</option>
                        <option value="percentage">Por percentual</option>
                        <option value="amount">Por valor</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1 text-slate-500">Adicionar participante</label>
                    <div class="flex gap-2">
                        <select v-model="selectedNewUserId" class="input flex-1">
                            <option value="">Selecione uma pessoa...</option>
                            <option v-for="u in availableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <button type="button" @click="addFromSelect" :disabled="!selectedNewUserId" class="btn-primary whitespace-nowrap">+ Adicionar</button>
                    </div>
                </div>
            </div>

            <div v-if="participants.length" class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Pessoa</th>
                            <th v-if="splitMode === 'percentage'" class="px-3 py-2 text-right">%</th>
                            <th class="px-3 py-2 text-right">Valor (R$)</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="(p, idx) in participants" :key="p.user_id">
                            <td class="px-3 py-2 font-medium">{{ p.name }}</td>
                            <td v-if="splitMode === 'percentage'" class="px-3 py-2 text-right">
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    v-model.number="p.percentage"
                                    @input="recompute"
                                    class="input w-24 text-right tabular-nums py-1"
                                >
                            </td>
                            <td class="px-3 py-2 text-right">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :value="(Math.abs(Number(p.amount_cents) || 0) / 100).toFixed(2)"
                                    @input="(e) => { p.amount_cents = Math.round(parseFloat(e.target.value || 0) * 100) * Math.sign(props.totalCents || -1); sync(); }"
                                    :disabled="splitMode !== 'amount'"
                                    class="input w-32 text-right tabular-nums py-1"
                                >
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="removeAt(idx)" class="text-slate-400 hover:text-expense" title="Remover">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-800/30 text-sm">
                        <tr>
                            <td :colspan="splitMode === 'percentage' ? 2 : 1" class="px-3 py-2 text-right font-medium">Total</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums" :class="isBalanced ? 'text-income' : 'text-expense'">
                                {{ formatSplitCents(totalAllocatedAbs) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-xs">
                <span class="text-slate-500">Total da transacao: <strong class="text-slate-700 dark:text-slate-200">{{ formatSplitCents(totalAbsCents) }}</strong></span>
                <span v-if="splitMode === 'percentage'" class="text-slate-500">Soma %: <strong :class="Math.abs(percentageSum - 100) < 0.01 ? 'text-income' : 'text-expense'">{{ percentageSum.toFixed(2) }}%</strong></span>
                <span v-if="!isBalanced" class="text-expense font-medium">Diferenca: {{ formatSplitCents(diffAbs) }}</span>
                <span v-else class="text-income font-medium">OK, soma confere</span>
            </div>
        </div>
    </div>
</template>

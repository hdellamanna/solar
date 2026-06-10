<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';

const props = defineProps({
    goal: { type: Object, required: true },
    colors: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.goal.name,
    target_amount: props.goal.target_decimal,
    deadline: props.goal.deadline || '',
    icon: props.goal.icon,
    color: props.goal.color,
});

const contributeForm = useForm({
    amount_cents: 0,
});

const withdrawForm = useForm({
    amount_cents: 0,
});

const icons = ['🎯', '✈️', '🚗', '🏠', '💻', '📚', '💍', '🛟', '🔨', '💰', '🎓'];

const submit = () => {
    form.put(route('goals.update', props.goal.id));
};

const contribute = () => {
    contributeForm.post(route('goals.contribute', props.goal.id), { preserveScroll: true });
    contributeForm.amount_cents = 0;
};

const withdraw = () => {
    withdrawForm.post(route('goals.withdraw', props.goal.id), { preserveScroll: true });
    withdrawForm.amount_cents = 0;
};

const destroy = () => {
    if (confirm(`Arquivar a meta "${props.goal.name}"?`)) {
        router.delete(route('goals.destroy', props.goal.id));
    }
};
</script>

<template>
    <Head :title="`Editar: ${props.goal.name}`" />
    <AuthenticatedLayout :title="props.goal.name">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
            <!-- Edit form -->
            <div class="lg:col-span-2">
                <form @submit.prevent="submit" class="card p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Nome da meta</label>
                        <input
                            v-model="form.name"
                            type="text"
                            maxlength="80"
                            class="input"
                            :class="form.errors.name ? 'border-expense' : ''"
                            required
                        />
                        <p v-if="form.errors.name" class="text-xs text-expense mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">Valor alvo (R$)</label>
                        <input
                            v-model="form.target_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="input text-lg font-semibold"
                            :class="form.errors.target_amount ? 'border-expense' : ''"
                            required
                        />
                        <p v-if="form.errors.target_amount" class="text-xs text-expense mt-1">{{ form.errors.target_amount }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">Prazo (opcional)</label>
                        <input
                            v-model="form.deadline"
                            type="date"
                            :min="new Date().toISOString().slice(0, 10)"
                            class="input"
                            :class="form.errors.deadline ? 'border-expense' : ''"
                        />
                        <p v-if="form.errors.deadline" class="text-xs text-expense mt-1">{{ form.errors.deadline }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Ícone</label>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="i in icons"
                                    :key="i"
                                    type="button"
                                    @click="form.icon = i"
                                    :class="[
                                        'w-9 h-9 rounded-lg text-lg flex items-center justify-center border transition-colors',
                                        form.icon === i
                                            ? 'bg-brand-100 dark:bg-brand-900/40 border-brand-500'
                                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800',
                                    ]"
                                >{{ i }}</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Cor</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="c in props.colors"
                                    :key="c"
                                    type="button"
                                    @click="form.color = c"
                                    :class="[
                                        'w-7 h-7 rounded-full border-2 transition-all',
                                        form.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent hover:scale-110',
                                    ]"
                                    :style="{ backgroundColor: c }"
                                    :aria-label="`Cor ${c}`"
                                ></button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="destroy" class="text-sm text-expense hover:underline">Arquivar meta</button>
                        <div class="flex items-center gap-2">
                            <Link :href="route('goals.index')" class="btn-ghost">Voltar</Link>
                            <button type="submit" :disabled="form.processing" class="btn-primary">
                                {{ form.processing ? 'Salvando...' : 'Salvar alterações' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Side: progress + actions -->
            <div class="space-y-4">
                <!-- Progress card -->
                <div class="card p-5" :class="props.goal.is_achieved ? 'ring-2 ring-emerald-400/50' : ''">
                    <div class="flex items-center gap-2 mb-3">
                        <span
                            class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                            :style="{ backgroundColor: (props.goal.color || '#f59e0b') + '20', color: props.goal.color || '#f59e0b' }"
                        >{{ props.goal.icon || '🎯' }}</span>
                        <div>
                            <p class="font-semibold">{{ props.goal.name }}</p>
                            <p v-if="props.goal.is_achieved" class="text-xs text-emerald-600 font-medium">✓ Concluída</p>
                            <p v-else-if="props.goal.days_remaining !== null" class="text-xs text-slate-500">
                                <span v-if="props.goal.days_remaining >= 0">{{ props.goal.days_remaining }} dias restantes</span>
                                <span v-else class="text-expense">{{ Math.abs(props.goal.days_remaining) }} dias em atraso</span>
                            </p>
                        </div>
                    </div>

                    <div class="h-3 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden mb-3">
                        <div
                            class="h-full rounded-full transition-all"
                            :style="props.goal.is_achieved
                                ? { width: '100%', backgroundColor: '#10b981' }
                                : { width: props.goal.progress_percent + '%', backgroundColor: props.goal.color || '#f59e0b' }"
                        ></div>
                    </div>

                    <div class="flex items-end justify-between mb-1">
                        <div>
                            <p class="text-2xl font-bold tabular-nums">{{ formatCents(props.goal.current_amount_cents) }}</p>
                            <p class="text-xs text-slate-500">de {{ formatCents(props.goal.target_amount_cents) }}</p>
                        </div>
                        <p class="text-lg font-bold tabular-nums" :class="props.goal.is_achieved ? 'text-emerald-600' : 'text-brand-600'">
                            {{ props.goal.progress_percent }}%
                        </p>
                    </div>
                    <p v-if="!props.goal.is_achieved" class="text-xs text-slate-500">
                        Faltam <strong>{{ formatCents(props.goal.remaining_cents) }}</strong>
                    </p>
                </div>

                <!-- Contribute -->
                <div class="card p-5">
                    <h3 class="font-semibold mb-3">Adicionar à meta</h3>
                    <form @submit.prevent="contribute" class="space-y-2">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Valor (R$)</label>
                            <input
                                v-model.number="contributeForm.amount_cents"
                                @input="contributeForm.amount_cents = Math.round(contributeForm.amount_cents * 100)"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0,00"
                                class="input"
                                required
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Dica: digite em reais (ex: 50 = R$ 50,00).</p>
                        </div>
                        <button type="submit" :disabled="contributeForm.processing" class="btn-primary w-full">
                            {{ contributeForm.processing ? 'Adicionando...' : 'Adicionar' }}
                        </button>
                    </form>
                </div>

                <!-- Withdraw -->
                <div class="card p-5">
                    <h3 class="font-semibold mb-3">Retirar da meta</h3>
                    <form @submit.prevent="withdraw" class="space-y-2">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Valor (R$)</label>
                            <input
                                v-model.number="withdrawForm.amount_cents"
                                @input="withdrawForm.amount_cents = Math.round(withdrawForm.amount_cents * 100)"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0,00"
                                class="input"
                                required
                            />
                        </div>
                        <button type="submit" :disabled="withdrawForm.processing" class="btn-ghost w-full text-expense">
                            {{ withdrawForm.processing ? 'Retirando...' : 'Retirar' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

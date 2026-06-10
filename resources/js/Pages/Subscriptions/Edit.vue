<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatCents } from '@/Composables/useFormat';

const props = defineProps({
    subscription: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    icons: { type: Array, default: () => [] },
    colors: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.subscription.name,
    amount: props.subscription.amount_decimal,
    billing_day: props.subscription.billing_day,
    account_id: props.subscription.account?.id ?? '',
    category_id: props.subscription.category?.id ?? '',
    icon: props.subscription.icon,
    color: props.subscription.color,
    notes: props.subscription.notes ?? '',
});

const submit = () => {
    form.put(route('subscriptions.update', props.subscription.id));
};

const cancel = () => {
    if (confirm(`Cancelar a assinatura "${props.subscription.name}"?`)) {
        router.delete(route('subscriptions.destroy', props.subscription.id));
    }
};

const reactivate = () => {
    router.post(route('subscriptions.reactivate', props.subscription.id));
};

const toggleActive = () => {
    router.post(route('subscriptions.toggle-active', props.subscription.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Editar: ${props.subscription.name}`" />
    <AuthenticatedLayout :title="props.subscription.name">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 md:gap-6">
            <!-- Form (2/3) -->
            <div class="lg:col-span-2">
                <form @submit.prevent="submit" class="card-elevated p-6 md:p-8 space-y-6">
                    <!-- Live preview -->
                    <div class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50">
                        <div
                            class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl transition-all duration-200 ease-out"
                            :style="{ backgroundColor: (form.color || '#ef4444') + '1a', color: form.color || '#ef4444' }"
                        >{{ form.icon }}</div>
                        <div>
                            <p class="font-semibold tracking-tight">{{ form.name || 'Sem nome' }}</p>
                            <p class="text-sm text-slate-500 tabular-nums">R$ {{ parseFloat(form.amount || 0).toFixed(2).replace('.', ',') }} / mês</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-2">Serviço</label>
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
                            <label class="block text-sm font-medium mb-2">Valor mensal</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">R$</span>
                                <input
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="input pl-10 tabular-nums text-lg font-semibold"
                                    :class="form.errors.amount ? 'border-expense' : ''"
                                    required
                                />
                            </div>
                            <p v-if="form.errors.amount" class="text-xs text-expense mt-1">{{ form.errors.amount }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Dia de cobrança</label>
                            <input v-model.number="form.billing_day" type="number" min="1" max="31" class="input tabular-nums" required />
                            <p v-if="form.errors.billing_day" class="text-xs text-expense mt-1">{{ form.errors.billing_day }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Conta (opcional)</label>
                            <select v-model="form.account_id" class="input">
                                <option value="">— Sem conta vinculada —</option>
                                <option v-for="a in props.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Categoria</label>
                        <select v-model="form.category_id" class="input">
                            <option value="">— Sem categoria —</option>
                            <option v-for="c in props.categories" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-3">Ícone</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="i in props.icons"
                                :key="i"
                                type="button"
                                @click="form.icon = i"
                                :class="[
                                    'w-11 h-11 rounded-xl text-xl flex items-center justify-center border transition-all duration-200 ease-out',
                                    form.icon === i
                                        ? 'border-slate-900 dark:border-white scale-110 shadow-sm'
                                        : 'border-slate-200 dark:border-slate-700 hover:scale-105 hover:bg-slate-50 dark:hover:bg-slate-800',
                                ]"
                            >{{ i }}</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-3">Cor</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="c in props.colors"
                                :key="c"
                                type="button"
                                @click="form.color = c"
                                :class="[
                                    'w-8 h-8 rounded-full border-2 transition-all duration-200 ease-out',
                                    form.color === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent hover:scale-110',
                                ]"
                                :style="{ backgroundColor: c }"
                            ></button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Notas (opcional)</label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            maxlength="500"
                            placeholder="Plano família, renovou em jan/2026..."
                            class="input resize-none"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button v-if="!props.subscription.is_cancelled" type="button" @click="cancel" class="text-sm text-expense hover:underline">Cancelar assinatura</button>
                        <div class="flex items-center gap-3 ml-auto">
                            <Link :href="route('subscriptions.index')" class="btn-ghost">Voltar</Link>
                            <button type="submit" :disabled="form.processing" class="btn-primary">
                                {{ form.processing ? 'Salvando...' : 'Salvar' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Side panel (1/3) — status + next billing -->
            <div class="space-y-4">
                <div class="card-elevated p-5 md:p-6">
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Status</p>
                    <p class="text-lg font-semibold mt-2">
                        <span v-if="props.subscription.is_cancelled" class="text-slate-400">Cancelada</span>
                        <span v-else-if="!props.subscription.active" class="text-amber-600">Pausada</span>
                        <span v-else class="text-emerald-600">Ativa</span>
                    </p>

                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium mt-5">Próxima cobrança</p>
                    <p class="text-2xl font-bold tabular-nums mt-2 tracking-tight">
                        {{ formatCents(props.subscription.amount_cents) }}
                    </p>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ new Date(props.subscription.next_billing_at).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }) }}
                    </p>
                    <p v-if="!props.subscription.is_cancelled" class="text-xs text-slate-500 mt-0.5">
                        em {{ props.subscription.days_until_billing }} dia{{ props.subscription.days_until_billing === 1 ? '' : 's' }}
                    </p>
                </div>

                <div class="card-elevated p-5 md:p-6">
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Projeção</p>
                    <div class="mt-3 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600 dark:text-slate-400">Mensal</span>
                            <span class="text-sm font-semibold tabular-nums">{{ formatCents(props.subscription.monthly_cents) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-600 dark:text-slate-400">Anual</span>
                            <span class="text-sm font-semibold tabular-nums">{{ formatCents(props.subscription.yearly_cents) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div v-if="!props.subscription.is_cancelled" class="card-elevated p-5">
                    <button @click="toggleActive" class="w-full text-sm py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium transition-colors">
                        {{ props.subscription.active ? 'Pausar' : 'Reativar' }}
                    </button>
                </div>
                <div v-else class="card-elevated p-5">
                    <button @click="reactivate" class="w-full text-sm py-2.5 rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 hover:opacity-90 font-medium transition-opacity">
                        Reativar assinatura
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

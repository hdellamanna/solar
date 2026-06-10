<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    icons: { type: Array, default: () => [] },
    colors: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    amount: '',
    billing_day: new Date().getDate(),
    account_id: '',
    category_id: '',
    icon: '🎬',
    color: '#ef4444',
    notes: '',
});

const submit = () => {
    form.post(route('subscriptions.store'));
};
</script>

<template>
    <Head title="Nova assinatura" />
    <AuthenticatedLayout title="Nova assinatura">
        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="card-elevated p-6 md:p-8 space-y-6">
                <!-- Preview badge (Apple-style live preview) -->
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50">
                    <div
                        class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl transition-all duration-200 ease-out"
                        :style="{ backgroundColor: (form.color || '#ef4444') + '1a', color: form.color || '#ef4444' }"
                    >{{ form.icon }}</div>
                    <div>
                        <p class="font-semibold tracking-tight">{{ form.name || 'Nova assinatura' }}</p>
                        <p class="text-sm text-slate-500 tabular-nums">{{ form.amount ? 'R$ ' + parseFloat(form.amount).toFixed(2).replace('.', ',') : 'R$ 0,00' }} / mês</p>
                    </div>
                </div>

                <!-- Name + amount -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-2">Serviço</label>
                        <input
                            v-model="form.name"
                            type="text"
                            maxlength="80"
                            placeholder="Ex: Netflix, Spotify, iCloud..."
                            class="input"
                            :class="form.errors.name ? 'border-expense' : ''"
                            required
                            autofocus
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
                                placeholder="0,00"
                                class="input pl-10 tabular-nums text-lg font-semibold"
                                :class="form.errors.amount ? 'border-expense' : ''"
                                required
                            />
                        </div>
                        <p v-if="form.errors.amount" class="text-xs text-expense mt-1">{{ form.errors.amount }}</p>
                    </div>
                </div>

                <!-- Billing day + account -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Dia de cobrança</label>
                        <input
                            v-model.number="form.billing_day"
                            type="number"
                            min="1"
                            max="31"
                            class="input tabular-nums"
                            :class="form.errors.billing_day ? 'border-expense' : ''"
                            required
                        />
                        <p class="text-xs text-slate-500 mt-1">Dia do mês que a cobrança cai.</p>
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

                <!-- Category -->
                <div>
                    <label class="block text-sm font-medium mb-2">Categoria</label>
                    <select v-model="form.category_id" class="input">
                        <option value="">— Sem categoria —</option>
                        <option v-for="c in props.categories" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
                    </select>
                </div>

                <!-- Icon picker (Apple-style: large, hover scale) -->
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

                <!-- Color picker -->
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
                            :aria-label="`Cor ${c}`"
                        ></button>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium mb-2">Notas (opcional)</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        maxlength="500"
                        placeholder="Plano família, renovou em jan/2026, cobrar no cartão final 1234..."
                        class="input resize-none"
                    ></textarea>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Link :href="route('subscriptions.index')" class="btn-ghost">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Salvando...' : 'Cadastrar' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    types:      { type: Object, default: () => ({}) },
    typeColors: { type: Object, default: () => ({}) },
});

const form = useForm({
    name:          '',
    type:          'stock',
    ticker:        '',
    quantity:      '',
    average_price: '',
    current_price: '',
    currency:      'BRL',
    acquired_at:   '',
    notes:         '',
});

const submit = () => {
    form.post(route('investments.store'));
};

const typeOptions = Object.entries(props.types).map(([value, label]) => ({ value, label }));
</script>

<template>
    <Head title="Novo investimento" />
    <AuthenticatedLayout title="Novo investimento">
        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="card-elevated p-6 md:p-8 space-y-6">
                <div class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50">
                    <div
                        class="w-12 h-12 rounded-2xl flex items-center justify-center text-sm font-bold uppercase transition-all duration-200 ease-out"
                        :style="{ backgroundColor: (props.typeColors[form.type] || '#64748b') + '1a', color: props.typeColors[form.type] || '#64748b' }"
                    >{{ form.ticker || (form.type === 'crypto' ? '₿' : form.type === 'stock' ? 'AÇ' : form.type === 'fund' ? 'F' : form.type === 'fixed_income' ? 'RF' : 'TD') }}</div>
                    <div class="min-w-0">
                        <p class="font-semibold tracking-tight truncate">{{ form.name || 'Novo investimento' }}</p>
                        <p class="text-sm text-slate-500 truncate">{{ props.types[form.type] || 'Tipo' }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Nome</label>
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="100"
                        placeholder="Ex: Tesouro Selic 2029, ITSA4, Bitcoin..."
                        class="input"
                        :class="form.errors.name ? 'border-expense' : ''"
                        required
                        autofocus
                    />
                    <p v-if="form.errors.name" class="text-xs text-expense mt-1">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Tipo</label>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-1 p-1 rounded-2xl bg-slate-100 dark:bg-slate-800/60">
                        <button
                            v-for="opt in typeOptions"
                            :key="opt.value"
                            type="button"
                            @click="form.type = opt.value"
                            :class="[
                                'px-2 py-2 rounded-xl text-xs font-medium transition-all duration-200 ease-out text-center',
                                form.type === opt.value
                                    ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                            ]"
                        >{{ opt.label }}</button>
                    </div>
                    <p v-if="form.errors.type" class="text-xs text-expense mt-1">{{ form.errors.type }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Ticker (opcional)</label>
                        <input
                            v-model="form.ticker"
                            type="text"
                            maxlength="20"
                            placeholder="ITSA4, HASH11, BTC..."
                            class="input font-mono uppercase"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Moeda</label>
                        <select v-model="form.currency" class="input">
                            <option value="BRL">BRL — Real</option>
                            <option value="USD">USD — Dólar</option>
                            <option value="EUR">EUR — Euro</option>
                            <option value="GBP">GBP — Libra</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Data de compra</label>
                        <input
                            v-model="form.acquired_at"
                            type="date"
                            class="input tabular-nums"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Quantidade</label>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="form.quantity = Math.max(0, (parseFloat(form.quantity) || 0) - 1).toString()" class="w-10 h-10 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-lg font-medium transition-colors">−</button>
                        <input
                            v-model="form.quantity"
                            type="number"
                            step="0.00000001"
                            min="0"
                            placeholder="100"
                            class="input flex-1 tabular-nums text-lg font-semibold"
                            :class="form.errors.quantity ? 'border-expense' : ''"
                            required
                        />
                        <button type="button" @click="form.quantity = ((parseFloat(form.quantity) || 0) + 1).toString()" class="w-10 h-10 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-lg font-medium transition-colors">+</button>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Aceita frações (ex: 0,05 BTC).</p>
                    <p v-if="form.errors.quantity" class="text-xs text-expense mt-1">{{ form.errors.quantity }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Preço médio (por unidade)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">{{ form.currency === 'USD' ? '$' : form.currency === 'EUR' ? '€' : form.currency === 'GBP' ? '£' : 'R$' }}</span>
                            <input
                                v-model="form.average_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0,00"
                                class="input pl-10 tabular-nums text-lg font-semibold"
                                :class="form.errors.average_price ? 'border-expense' : ''"
                                required
                            />
                        </div>
                        <p v-if="form.errors.average_price" class="text-xs text-expense mt-1">{{ form.errors.average_price }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Preço atual <span class="text-slate-400 font-normal">(opcional)</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">{{ form.currency === 'USD' ? '$' : form.currency === 'EUR' ? '€' : form.currency === 'GBP' ? '£' : 'R$' }}</span>
                            <input
                                v-model="form.current_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0,00"
                                class="input pl-10 tabular-nums text-lg font-semibold"
                                :class="form.errors.current_price ? 'border-expense' : ''"
                            />
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Deixe em branco se ainda não sabe.</p>
                        <p v-if="form.errors.current_price" class="text-xs text-expense mt-1">{{ form.errors.current_price }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Notas (opcional)</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        maxlength="1000"
                        placeholder="Estratégia, corretora, observações..."
                        class="input resize-none"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Link :href="route('investments.index')" class="btn-ghost">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Salvando...' : 'Cadastrar' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

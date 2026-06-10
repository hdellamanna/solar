<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    colors: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    target_amount: '',
    deadline: '',
    icon: '🎯',
    color: props.colors[0] || '#f59e0b',
});

const icons = ['🎯', '✈️', '🚗', '🏠', '💻', '📚', '💍', '🛟', '🔨', '💰', '🎓', '✈️'];

const submit = () => {
    form.post(route('goals.store'));
};
</script>

<template>
    <Head title="Nova meta" />
    <AuthenticatedLayout title="Nova meta de economia">
        <div class="max-w-xl">
            <form @submit.prevent="submit" class="card p-6 space-y-5">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Nome da meta</label>
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="80"
                        placeholder="Ex: Reserva de emergência, Viagem, Notebook novo..."
                        class="input"
                        :class="form.errors.name ? 'border-expense' : ''"
                        required
                        autofocus
                    />
                    <p v-if="form.errors.name" class="text-xs text-expense mt-1">{{ form.errors.name }}</p>
                </div>

                <!-- Target amount -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Valor alvo (R$)</label>
                    <input
                        v-model="form.target_amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="10000.00"
                        class="input text-lg font-semibold"
                        :class="form.errors.target_amount ? 'border-expense' : ''"
                        required
                    />
                    <p v-if="form.errors.target_amount" class="text-xs text-expense mt-1">{{ form.errors.target_amount }}</p>
                </div>

                <!-- Deadline -->
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

                <!-- Icon + color -->
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

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <Link :href="route('goals.index')" class="btn-ghost">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Criando...' : 'Criar meta' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    theme: props.user?.theme ?? 'system',
});
const submit = () => form.patch(route('profile.update'));

// FASE 5 — AI categorize toggle.
const aiForm = useForm({ use_ai_categorize: Boolean(props.user?.use_ai_categorize) });
const aiSaving = ref(false);
const aiMessage = ref(null);
let aiMessageTimer = null;

watch(() => props.user?.use_ai_categorize, (val) => {
    aiForm.use_ai_categorize = Boolean(val);
});

function flashAiMessage(text, kind) {
    aiMessage.value = { text, kind };
    if (aiMessageTimer) clearTimeout(aiMessageTimer);
    aiMessageTimer = setTimeout(() => { aiMessage.value = null; }, 4000);
}

async function submitAiPreference() {
    aiSaving.value = true;
    try {
        await aiForm.patch(route('profile.ai-preference'), {
            preserveScroll: true,
            onSuccess: () => flashAiMessage(
                aiForm.use_ai_categorize
                    ? 'Sugestão por IA ativada.'
                    : 'Sugestão por IA desativada.',
                'success',
            ),
            onError: (errors) => {
                const first = Object.values(errors)[0];
                flashAiMessage(first || 'Não foi possível atualizar a preferência.', 'error');
            },
        });
    } finally {
        aiSaving.value = false;
    }
}
</script>

<template>
    <Head title="Perfil" />
    <AuthenticatedLayout title="Perfil">
        <div class="max-w-lg space-y-4">
            <form @submit.prevent="submit" class="card p-6 space-y-4">
                <h2 class="font-semibold">Dados básicos</h2>
                <div>
                    <label class="block text-sm font-medium mb-1">Nome</label>
                    <input v-model="form.name" type="text" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input v-model="form.email" type="email" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tema preferido</label>
                    <select v-model="form.theme" class="input">
                        <option value="light">Claro</option>
                        <option value="dark">Escuro</option>
                        <option value="system">Sistema</option>
                    </select>
                </div>
                <button class="btn-primary" :disabled="form.processing">Salvar</button>
            </form>

            <form @submit.prevent="submitAiPreference" class="card p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">✨</span>
                    <div class="flex-1">
                        <h2 class="font-semibold">Sugestão de categoria por IA</h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Quando ativada, o Solar sugere automaticamente a categoria das suas transações
                            com base na descrição (ex.: "iFood" → Alimentação). As sugestões rodam
                            <strong>offline</strong> por padrão e o resultado é cacheado por 30 dias.
                        </p>
                    </div>
                </div>

                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <span class="relative">
                        <input v-model="aiForm.use_ai_categorize" type="checkbox" class="sr-only peer">
                        <span class="block w-10 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-brand-500 transition-colors"></span>
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow peer-checked:translate-x-4 transition-transform"></span>
                    </span>
                    <span class="text-sm font-medium">Usar IA para sugerir categorias</span>
                </label>

                <p
                    v-if="aiMessage"
                    :class="[
                        'text-xs',
                        aiMessage.kind === 'success' ? 'text-brand-600 dark:text-brand-400' : 'text-expense',
                    ]"
                >
                    {{ aiMessage.text }}
                </p>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-primary" :disabled="aiSaving">
                        <span v-if="aiSaving">Salvando...</span>
                        <span v-else>Salvar preferência</span>
                    </button>
                    <span class="text-xs text-slate-500">Off por padrão (privacidade).</span>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

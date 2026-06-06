<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import { useTag } from '@/Composables/useTag';
import { formatCents } from '@/Composables/useFormat';

const props = defineProps({
    tags: { type: Array, default: () => [] },
});

const { tagColor, tagIcon, readableTextOn } = useTag();
const editingId = ref(null);
const showForm = ref(false);

const form = useForm({
    name: '',
    color: '#3b82f6',
    icon: '🏷️',
});

const isEditing = computed(() => editingId.value !== null);

const startCreate = () => {
    editingId.value = null;
    form.reset();
    form.color = '#3b82f6';
    form.icon = '🏷️';
    showForm.value = true;
};

const startEdit = (tag) => {
    editingId.value = tag.id;
    form.name = tag.name;
    form.color = tag.color || '#6b7280';
    form.icon = tag.icon || '🏷️';
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(route('tags.update', editingId.value), {
            onSuccess: () => cancelForm(),
        });
    } else {
        form.post(route('tags.store'), {
            onSuccess: () => cancelForm(),
        });
    }
};

const destroy = (tag) => {
    if (confirm(`Excluir a tag "${tag.name}"?`)) {
        router.delete(route('tags.destroy', tag.id));
    }
};

const totalFor = (tag) => formatCents(Math.abs(tag.total_cents || 0));
</script>

<template>
    <Head title="Tags" />
    <AuthenticatedLayout title="Tags">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
            <p class="text-sm text-slate-500">{{ props.tags.length }} tag(s) cadastrada(s)</p>
            <button v-if="!showForm" @click="startCreate" class="btn-primary">+ Nova tag</button>
            <button v-else @click="cancelForm" class="btn-ghost">Cancelar</button>
        </div>

        <!-- Create / Edit form -->
        <div v-if="showForm" class="card p-5 md:p-6 mb-4 max-w-xl">
            <h2 class="font-semibold mb-3">{{ isEditing ? 'Editar tag' : 'Nova tag' }}</h2>
            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Nome</label>
                    <input v-model="form.name" type="text" maxlength="60" placeholder="Ex: Trabalho, Pessoal..." class="input" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Cor</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.color" type="color" class="h-10 w-12 rounded cursor-pointer border border-slate-200 dark:border-slate-700">
                            <input v-model="form.color" type="text" placeholder="#3b82f6" class="input flex-1">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Ícone (emoji)</label>
                        <input v-model="form.icon" type="text" maxlength="32" placeholder="🏷️" class="input">
                    </div>
                </div>
                <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-sm">
                    <p v-for="(err, field) in form.errors" :key="field" class="text-expense">{{ err }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <span v-if="form.processing">Salvando...</span>
                        <span v-else>{{ isEditing ? 'Atualizar' : 'Criar' }}</span>
                    </button>
                    <button type="button" @click="cancelForm" class="btn-ghost">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Empty state -->
        <div v-if="props.tags.length === 0 && !showForm" class="card p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
            </svg>
            <h3 class="font-semibold mb-1">Nenhuma tag ainda</h3>
            <p class="text-sm text-slate-500 mb-4">Crie tags para organizar e filtrar suas transações.</p>
            <button @click="startCreate" class="btn-primary inline-flex">+ Criar primeira tag</button>
        </div>

        <!-- Tag grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            <div v-for="tag in props.tags" :key="tag.id" class="card p-4 flex flex-col gap-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-lg shrink-0"
                            :style="{ backgroundColor: tagColor(tag.color), color: readableTextOn(tag.color) }"
                        >{{ tagIcon(tag.icon) }}</span>
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ tag.name }}</p>
                            <p class="text-xs text-slate-500">#{{ tag.slug }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs mt-1">
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-2">
                        <p class="text-slate-500">Transações</p>
                        <p class="font-semibold text-sm">{{ tag.transactions_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-2">
                        <p class="text-slate-500">Total</p>
                        <p class="font-semibold text-sm" :class="(tag.total_cents ?? 0) >= 0 ? 'text-income' : 'text-expense'">
                            {{ totalFor(tag) }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-1 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button @click="startEdit(tag)" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        Editar
                    </button>
                    <button @click="destroy(tag)" class="text-xs text-slate-500 hover:text-expense inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

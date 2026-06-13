<script setup>
/*
 * Settings — index (FASE 4D / Auth Phase 3).
 *
 * Landing page for the future settings surface. Currently only
 * lists the Security subsection (2FA + trusted devices). New
 * subsections (notifications, data export, theme tweaks) will be
 * added here as the design lands.
 */
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const settingsSections = [
    {
        key: 'security',
        href: 'settings.security',
        name: 'Seguranca',
        description: 'Verificacao em duas etapas, dispositivos confiaveis, sessoes ativas.',
        icon: 'M12 2l8 4v6c0 5-3.5 9.4-8 10-4.5-.6-8-5-8-10V6l8-4z',
        accent: 'from-primary-500 to-primary-700',
    },
    {
        key: 'profile',
        href: 'profile.edit',
        name: 'Perfil',
        description: 'Nome, email, tema preferido e a preferencia de categoria por IA.',
        icon: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
        accent: 'from-solar-500 to-solar-600',
    },
];
</script>

<template>
    <Head title="Configuracoes · Solar Money" />
    <AuthenticatedLayout title="Configuracoes">
        <div class="max-w-3xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight">Configuracoes</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">
                    Personalize sua conta e proteja seu acesso.
                </p>
            </div>

            <ul class="space-y-3">
                <li v-for="section in settingsSections" :key="section.key">
                    <Link
                        :href="route(section.href)"
                        class="card p-5 flex items-center gap-4 hover:shadow-soft
                               hover:border-primary-200/70 dark:hover:border-primary-500/30
                               transition-all duration-200 group"
                    >
                        <div
                            class="w-11 h-11 rounded-xl grid place-items-center shrink-0
                                   bg-gradient-to-br text-white shadow-soft"
                            :class="section.accent"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path :d="section.icon" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold">{{ section.name }}</p>
                            <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">
                                {{ section.description }}
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-ink-400 group-hover:text-primary-500
                                    group-hover:translate-x-0.5 transition-all"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>

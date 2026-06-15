<script setup>
/*
 * Settings — index (FASE 4D / Auth Phase 3 / FASE 7 i18n).
 *
 * Landing page for the settings surface. Cards are listed in the
 * order: Segurança, Perfil, Idioma, Aparência, then the future
 * "Inteligência artificial" placeholder.
 */
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useLocale } from '@/Composables/useLocale';
import { useT } from '@/Composables/useT';
import { computed } from 'vue';

const { locale: activeLocale, available: availableLocales } = useLocale();
const { t } = useT();

// Resolve the human label for the active locale so the Idioma
// card can show "Português (Brasil)" instead of "pt-BR".
const activeLocaleLabel = computed(() => {
    const code = activeLocale.value;
    const match = availableLocales.value.find((l) => l.code === code);
    return match ? match.name : code;
});

const settingsSections = computed(() => [
    {
        key: 'security',
        href: 'settings.security',
        name: t('app.security'),
        description: 'Verificacao em duas etapas, dispositivos confiaveis, sessoes ativas.',
        icon: 'M12 2l8 4v6c0 5-3.5 9.4-8 10-4.5-.6-8-5-8-10V6l8-4z',
        accent: 'from-primary-500 to-primary-700',
    },
    {
        key: 'profile',
        href: 'profile.edit',
        name: t('app.profile'),
        description: 'Nome, email, tema preferido e a preferencia de categoria por IA.',
        icon: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
        accent: 'from-solar-500 to-solar-600',
    },
    {
        key: 'idioma',
        href: 'settings.idioma.show',
        name: t('app.language'),
        description: 'Idioma da interface. Atual: ' + activeLocaleLabel.value + '.',
        icon: 'M12 2a10 10 0 100 20 10 10 0 000-20zM2 12h20M12 2a14 14 0 010 20M12 2a14 14 0 000 20',
        accent: 'from-emerald-500 to-teal-700',
    },
    {
        key: 'appearance',
        href: 'settings.appearance.show',
        name: t('app.appearance'),
        description: 'Animacoes, intensidade do glass e parallax. Respeita a preferencia do sistema.',
        icon: 'M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M5 19l3-3M16 8l3-3',
        accent: 'from-amber-500 to-amber-700',
    },
    {
        key: 'ai',
        href: 'settings.appearance.show',
        name: 'Inteligencia artificial',
        description: 'Assistente IA em todas as paginas (em breve - FASE 8).',
        icon: 'M12 2a3 3 0 00-3 3v1H7a3 3 0 00-3 3v8a3 3 0 003 3h2v2l3-2h5a3 3 0 003-3V9a3 3 0 00-3-3h-2V5a3 3 0 00-3-3z',
        accent: 'from-purple-500 to-fuchsia-600',
        disabled: true,
        badge: 'Em breve',
    },
]);
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
                    <component
                        :is="section.disabled ? 'div' : Link"
                        :href="section.disabled ? null : route(section.href)"
                        :class="['card p-5 flex items-center gap-4 transition-all duration-200 group',
                                 section.disabled ? 'opacity-60 cursor-not-allowed' : 'hover:shadow-soft hover:border-primary-200/70 dark:hover:border-primary-500/30']"
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
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  :d="section.disabled ? 'M12 8v4M12 16h.01M12 2a10 10 0 100 20 10 10 0 000-20z' : 'M9 5l7 7-7 7'" />
                        </svg>
                        <span v-if="section.badge"
                              class="ml-1 px-2 py-0.5 text-[10px] rounded-full
                                     bg-amber-100 text-amber-700
                                     dark:bg-amber-500/15 dark:text-amber-300
                                     font-semibold uppercase tracking-wide">
                            {{ section.badge }}
                        </span>
                    </component>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>

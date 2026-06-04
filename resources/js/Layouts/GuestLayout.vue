<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gradient-to-br from-slate-50 via-amber-50/30 to-slate-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
        <div class="w-full max-w-sm">
            <Link href="/" class="flex items-center justify-center gap-2 mb-8 text-brand-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span class="text-2xl font-bold">Solar</span>
            </Link>
            <slot />
            <p class="text-center text-xs text-slate-400 mt-8">Suas finanças pessoais, com luz própria.</p>
        </div>
    </div>
</template>

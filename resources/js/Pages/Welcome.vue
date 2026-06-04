<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Bem-vindo" />
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full text-center space-y-6">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <h1 class="text-4xl font-bold tracking-tight">Solar</h1>
            <p class="text-lg text-slate-600 dark:text-slate-400">
                Suas financas pessoais, com luz propria.
            </p>
            <p class="text-sm text-slate-500 dark:text-slate-500">
                Clone modernizado do Microsoft Money Sunset, construido em Laravel + Vue 3.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center pt-4">
                <Link href="/login" class="btn-primary">Entrar</Link>
                <Link href="/register" class="btn-ghost">Criar conta</Link>
            </div>
        </div>
    </div>
</template>

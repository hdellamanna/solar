<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { useShortcuts } from '@/Composables/useShortcuts';
import { useGlobalSearch } from '@/Composables/useGlobalSearch';
import { initials, formatCents, formatDate } from '@/Composables/useFormat';

const props = defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { isDark, init: initTheme, toggle: toggleTheme } = useTheme();
const sidebarOpen = ref(false);
const userMenuOpen = ref(false);

// Global search
const searchInput = ref(null);
const searchOpen = ref(false);
const searchWrapper = ref(null);
const { query: searchQuery, results: searchResults, loading: searchLoading, hasResults: searchHasResults, clear: clearSearch } = useGlobalSearch();

const focusSearch = () => {
    searchOpen.value = true;
    nextTick(() => searchInput.value?.focus());
};
const blurSearch = () => {
    // Slight delay so click-on-result can fire first.
    setTimeout(() => { searchOpen.value = false; }, 120);
};

const onSearchKeydown = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        searchOpen.value = false;
        searchInput.value?.blur();
    } else if (e.key === 'Enter') {
        if (searchQuery.value.trim().length >= 2) {
            e.preventDefault();
            router.get(route('transactions.index'), { search: searchQuery.value.trim() });
            searchOpen.value = false;
        }
    }
};

const goTo = (href) => {
    searchOpen.value = false;
    router.visit(href);
};

const onDocClick = (e) => {
    if (!searchWrapper.value) return;
    if (!searchWrapper.value.contains(e.target)) {
        searchOpen.value = false;
    }
};

onMounted(() => {
    initTheme(user.value?.theme);
    useShortcuts({
        newTransaction: () => router.visit(route('transactions.create')),
        dashboard: () => router.visit(route('dashboard')),
        transactions: () => router.visit(route('transactions.index')),
        accounts: () => router.visit(route('accounts.index')),
        search: focusSearch,
    });
    document.addEventListener('mousedown', onDocClick);
});
onUnmounted(() => {
    document.removeEventListener('mousedown', onDocClick);
});

const nav = [
    { name: 'Dashboard', route: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Transações', route: 'transactions.index', icon: 'M3 10h18M3 6h18M3 14h18M3 18h18' },
    { name: 'Contas', route: 'accounts.index', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
    { name: 'Recorrências', route: 'recurrences.index', icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' },
    { name: 'Tags', route: 'tags.index', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' },
    { name: 'Orçamentos', route: 'budgets.index', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { name: 'Metas', route: 'goals.index', icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
    { name: 'Assinaturas', route: 'subscriptions.index', icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4' },
    { name: 'PIX', route: 'pix.index', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { name: 'Relatórios', route: 'reports.index', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
];

const isActive = (routeName) => {
    if (!page.url) return false;
    const path = route(routeName);
    return page.url.startsWith(path);
};

const logout = () => router.post(route('logout'));

const totalResults = () => {
    const r = searchResults.value;
    return r.accounts.length + r.categories.length + r.transactions.length + r.tags.length;
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
        <!-- Sidebar desktop -->
        <aside class="hidden md:flex md:flex-col md:fixed md:inset-y-0 md:w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-2 px-6 h-16 border-b border-slate-200 dark:border-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span class="text-xl font-bold">Solar</span>
            </div>
            <nav class="flex-1 p-3 space-y-1">
                <Link v-for="item in nav" :key="item.name" :href="route(item.route)" :class="['flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors', isActive(item.route) ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800']">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    {{ item.name }}
                </Link>
            </nav>
            <div class="p-3 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3 px-3 py-2">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white font-semibold text-sm">
                        {{ initials(user?.name) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ user?.name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ user?.email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Sidebar mobile (drawer) -->
        <Transition
            enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="sidebarOpen" class="md:hidden fixed inset-0 z-40 bg-slate-900/50" @click="sidebarOpen = false"></div>
        </Transition>
        <Transition
            enter-active-class="transition duration-200 transform" enter-from-class="-translate-x-full" enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 transform" leave-from-class="translate-x-0" leave-to-class="-translate-x-full">
            <aside v-if="sidebarOpen" class="md:hidden fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col">
                <div class="flex items-center justify-between px-6 h-16 border-b border-slate-200 dark:border-slate-800">
                    <span class="text-xl font-bold">Solar</span>
                    <button @click="sidebarOpen = false" class="p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 p-3 space-y-1">
                    <Link v-for="item in nav" :key="item.name" :href="route(item.route)" @click="sidebarOpen = false" :class="['flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium', isActive(item.route) ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800']">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        {{ item.name }}
                    </Link>
                </nav>
            </aside>
        </Transition>

        <!-- Main content area -->
        <div class="md:pl-64 flex flex-col min-h-screen">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 bg-white/80 dark:bg-slate-900/80 backdrop-blur border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between gap-3 h-16 px-4 md:px-6">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <button @click="sidebarOpen = true" class="md:hidden p-1 -ml-1 text-slate-600 dark:text-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <h1 class="text-lg font-semibold hidden sm:block">{{ title }}</h1>

                        <!-- Global search -->
                        <div ref="searchWrapper" class="relative flex-1 max-w-md">
                            <div class="relative">
                                <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                                </svg>
                                <input
                                    ref="searchInput"
                                    v-model="searchQuery"
                                    @focus="searchOpen = true"
                                    @keydown="onSearchKeydown"
                                    type="text"
                                    placeholder="Buscar... (pressione /)"
                                    class="w-full pl-9 pr-16 py-2 text-sm rounded-lg bg-slate-100 dark:bg-slate-800 border border-transparent focus:border-brand-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none transition-colors"
                                />
                                <kbd class="hidden sm:flex absolute right-2 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-xs font-mono text-slate-500 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded">/</kbd>
                            </div>

                            <!-- Dropdown -->
                            <Transition
                                enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
                                <div v-if="searchOpen && searchQuery.trim().length >= 2" class="absolute left-0 right-0 mt-2 card p-0 max-h-[70vh] overflow-y-auto z-50">
                                    <div v-if="searchLoading" class="p-4 text-sm text-slate-500 text-center">Buscando...</div>
                                    <div v-else-if="!searchHasResults()" class="p-6 text-sm text-slate-500 text-center">
                                        Nenhum resultado para "<strong>{{ searchQuery }}</strong>"
                                    </div>
                                    <div v-else>
                                        <!-- Accounts -->
                                        <div v-if="searchResults.accounts.length" class="border-b border-slate-100 dark:border-slate-800">
                                            <div class="px-3 py-1.5 text-xs font-semibold uppercase text-slate-400 bg-slate-50 dark:bg-slate-800/50">Contas</div>
                                            <button v-for="a in searchResults.accounts" :key="'acc-' + a.id" @click="goTo(route('accounts.index', { search: a.name }))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: a.color || '#94a3b8' }"></span>
                                                <span class="text-sm font-medium flex-1 truncate">{{ a.name }}</span>
                                                <span class="text-xs text-slate-400">{{ a.type }}</span>
                                            </button>
                                        </div>

                                        <!-- Categories -->
                                        <div v-if="searchResults.categories.length" class="border-b border-slate-100 dark:border-slate-800">
                                            <div class="px-3 py-1.5 text-xs font-semibold uppercase text-slate-400 bg-slate-50 dark:bg-slate-800/50">Categorias</div>
                                            <button v-for="c in searchResults.categories" :key="'cat-' + c.id" @click="goTo(route('transactions.index', { category_ids: [c.id] }))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                                <span class="text-base">{{ c.icon || '📦' }}</span>
                                                <span class="text-sm font-medium flex-1 truncate">{{ c.name }}</span>
                                                <span class="text-xs text-slate-400">{{ c.type === 'income' ? 'Receita' : 'Despesa' }}</span>
                                            </button>
                                        </div>

                                        <!-- Tags -->
                                        <div v-if="searchResults.tags.length" class="border-b border-slate-100 dark:border-slate-800">
                                            <div class="px-3 py-1.5 text-xs font-semibold uppercase text-slate-400 bg-slate-50 dark:bg-slate-800/50">Tags</div>
                                            <button v-for="t in searchResults.tags" :key="'tag-' + t.id" @click="goTo(route('transactions.index', { search: t.name }))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: t.color || '#94a3b8' }"></span>
                                                <span class="text-sm font-medium">#{{ t.name }}</span>
                                            </button>
                                        </div>

                                        <!-- Transactions -->
                                        <div v-if="searchResults.transactions.length">
                                            <div class="px-3 py-1.5 text-xs font-semibold uppercase text-slate-400 bg-slate-50 dark:bg-slate-800/50">Transações</div>
                                            <button v-for="t in searchResults.transactions" :key="'tx-' + t.id" @click="goTo(route('transactions.edit', t.id))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                                <span class="w-2 h-2 rounded-full" :class="t.type === 'income' ? 'bg-emerald-500' : (t.type === 'transfer' ? 'bg-blue-500' : 'bg-rose-500')"></span>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium truncate">{{ t.description }}</p>
                                                    <p class="text-xs text-slate-400 truncate">{{ formatDate(t.date) }} · {{ t.account?.name }}</p>
                                                </div>
                                                <span class="text-sm font-semibold tabular-nums" :class="t.type === 'income' ? 'text-income' : 'text-expense'">
                                                    {{ t.type === 'income' ? '+' : '-' }}{{ formatCents(Math.abs(t.amount_cents)) }}
                                                </span>
                                            </button>
                                        </div>

                                        <div class="border-t border-slate-100 dark:border-slate-800 p-2 text-center">
                                            <button @click="goTo(route('transactions.index', { search: searchQuery }))" class="text-xs text-brand-600 hover:underline">
                                                Ver todos ({{ totalResults() }}) em Transações →
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="toggleTheme" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" :title="isDark ? 'Modo claro' : 'Modo escuro'">
                            <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        </button>
                        <div class="relative">
                            <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white font-semibold text-xs">
                                    {{ initials(user?.name) }}
                                </div>
                            </button>
                            <div v-if="userMenuOpen" @click="userMenuOpen = false" class="absolute right-0 mt-1 w-48 card p-1 z-50">
                                <Link :href="route('profile.edit')" class="block px-3 py-2 text-sm rounded hover:bg-slate-100 dark:hover:bg-slate-800">Perfil</Link>
                                <button @click="logout" class="block w-full text-left px-3 py-2 text-sm rounded text-expense hover:bg-slate-100 dark:hover:bg-slate-800">Sair</button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6 pb-24 md:pb-6">
                <slot />
            </main>

            <!-- Bottom bar mobile -->
            <nav class="md:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
                <div class="grid grid-cols-5 h-16">
                    <Link :href="route('dashboard')" :class="['flex flex-col items-center justify-center gap-1 text-xs', isActive('dashboard') ? 'text-brand-600' : 'text-slate-500']">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        Início
                    </Link>
                    <Link :href="route('transactions.index')" :class="['flex flex-col items-center justify-center gap-1 text-xs', isActive('transactions.index') ? 'text-brand-600' : 'text-slate-500']">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M3 18h18M3 6h18" /></svg>
                        Trans.
                    </Link>
                    <Link :href="route('transactions.create')" class="flex flex-col items-center justify-center -mt-6">
                        <span class="w-12 h-12 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-white flex items-center justify-center shadow-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        </span>
                    </Link>
                    <Link :href="route('budgets.index')" :class="['flex flex-col items-center justify-center gap-1 text-xs', isActive('budgets.index') ? 'text-brand-600' : 'text-slate-500']">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2" /></svg>
                        Orçam.
                    </Link>
                    <Link :href="route('accounts.index')" :class="['flex flex-col items-center justify-center gap-1 text-xs', isActive('accounts.index') ? 'text-brand-600' : 'text-slate-500']">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                        Mais
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { useShortcuts } from '@/Composables/useShortcuts';
import { useGlobalSearch } from '@/Composables/useGlobalSearch';
import { initials, formatCents, formatDate } from '@/Composables/useFormat';
import { useT } from '@/Composables/useT';
import AppFooter from '@/Components/AppFooter.vue';

const props = defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { isDark, init: initTheme, toggle: toggleTheme } = useTheme();
const { t } = useT();
const sidebarOpen = ref(false);
const userMenuOpen = ref(false);

// ─── Email verification banner (FASE 4D) ──────────────────────────────
// Shows at the top of the main content area when the authenticated
// user has not yet verified their email. Dismissable per-session.
const isUnverified = computed(() => {
    const u = user.value;
    if (!u) return false;
    // The backend shares `email_verified_at` as an ISO 8601 string
    // (or null). Treat null/undefined/missing as "unverified".
    if (!('email_verified_at' in u)) return false; // prop absent → don't know → don't show
    return u.email_verified_at === null || u.email_verified_at === undefined;
});
const bannerDismissed = ref(false);
onMounted(() => {
    try {
        bannerDismissed.value = sessionStorage.getItem('verify_banner_dismissed') === '1';
    } catch (e) {
        // sessionStorage may be unavailable (private mode, SSR) — ignore
    }
});
const dismissBanner = () => {
    bannerDismissed.value = true;
    try { sessionStorage.setItem('verify_banner_dismissed', '1'); } catch (e) { /* ignore */ }
};
// Reset dismissal once the user becomes verified.
watch(isUnverified, (v) => {
    if (!v) {
        bannerDismissed.value = false;
        try { sessionStorage.removeItem('verify_banner_dismissed'); } catch (e) { /* ignore */ }
    }
});
// Local "resend" state — useForm would be cleaner but we want to keep
// the layout focused; a plain POST is enough.
const resendState = ref({ processing: false, cooldown: false });
let cooldownTimer = null;
const resendVerification = () => {
    if (resendState.value.processing || resendState.value.cooldown) return;
    resendState.value.processing = true;
    router.post(route('verification.resend'), {}, {
        preserveScroll: true,
        onFinish: () => {
            resendState.value.processing = false;
            resendState.value.cooldown = true;
            if (cooldownTimer) clearTimeout(cooldownTimer);
            cooldownTimer = setTimeout(() => {
                resendState.value.cooldown = false;
                cooldownTimer = null;
            }, 30 * 1000);
        },
    });
};

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
const goTo = (href) => { searchOpen.value = false; router.visit(href); };
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
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

// ─── Nav with Lucide-style icons (24x24, 1.6 stroke for premium feel) ───
const nav = [
    { section: 'PRINCIPAL', items: [
        { name: 'Dashboard',     route: 'dashboard',         icon: 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z' },
        { name: 'Transações',    route: 'transactions.index', icon: 'M4 6h16M4 12h16M4 18h12' },
        { name: 'Contas',        route: 'accounts.index',    icon: 'M3 8h18v4H3V8zm0 6h18v2H3v-2zm0-12h18v2H3V2z' },
    ]},
    { section: 'PLANEJAMENTO', items: [
        { name: 'Metas',         route: 'goals.index',         icon: 'M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21z' },
        { name: 'Orçamentos',    route: 'budgets.index',       icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
        { name: 'Assinaturas',   route: 'subscriptions.index', icon: 'M4 4h16v4H4V4zm0 6h16v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6zm4 4h8' },
    ]},
    { section: 'PATRIMÔNIO', items: [
        { name: 'Investimentos', route: 'investments.index', icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
        { name: 'Dívidas',       route: 'debts.index',        icon: 'M12 8c-1.7 0-3 1.3-3 3s1.3 3 3 3 3 1.3 3 3-1.3 3-3 3M3 12a9 9 0 1018 0 9 9 0 00-18 0z' },
        { name: 'Recorrências',  route: 'recurrences.index',  icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2' },
    ]},
    { section: 'PAGAR & ANALISAR', items: [
        { name: 'PIX',           route: 'pix.index',         icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
        { name: 'Tags',          route: 'tags.index',        icon: 'M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' },
        { name: 'Relatórios',    route: 'reports.index',     icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    ]},
    { section: 'CONTA', items: [
        { name: 'Configurações',  route: 'settings.index',  icon: 'M10.325 4.317a1.724 1.724 0 013.35 0 1.724 1.724 0 002.573 1.066 1.724 1.724 0 012.572 1.066 1.724 1.724 0 001.066 2.573 1.724 1.724 0 010 3.35 1.724 1.724 0 00-1.066 2.573 1.724 1.724 0 01-2.572 1.066 1.724 1.724 0 00-2.573 1.066 1.724 1.724 0 01-3.35 0 1.724 1.724 0 00-2.573-1.066 1.724 1.724 0 01-2.572-1.066 1.724 1.724 0 00-1.066-2.573 1.724 1.724 0 010-3.35 1.724 1.724 0 001.066-2.573 1.724 1.724 0 012.572-1.066 1.724 1.724 0 002.573-1.066zM12 15a3 3 0 100-6 3 3 0 000 6z' },
    ]},
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
    <div class="min-h-screen bg-ink-50 dark:bg-ink-950 text-ink-900 dark:text-ink-50 font-body mesh-canvas">
        <!-- Decorative ambient mesh — sits behind everything, drifts slowly -->
        <div class="fixed inset-0 -z-10 pointer-events-none">
            <div class="absolute -top-40 -right-40 w-[700px] h-[700px] rounded-full opacity-40 blur-3xl animate-mesh-drift-a"
                 style="background: radial-gradient(circle, rgba(255, 138, 61, 0.55), transparent 70%);"></div>
            <div class="absolute top-1/3 -left-40 w-[600px] h-[600px] rounded-full opacity-30 blur-3xl animate-mesh-drift-b"
                 style="background: radial-gradient(circle, rgba(124, 58, 237, 0.45), transparent 70%);"></div>
            <div class="absolute bottom-0 right-1/3 w-[500px] h-[500px] rounded-full opacity-20 blur-3xl animate-mesh-drift-a"
                 style="animation-delay: -12s; background: radial-gradient(circle, rgba(255, 201, 60, 0.5), transparent 70%);"></div>
        </div>

        <!-- ─── Desktop sidebar (visible md+) ─── -->
        <aside class="hidden md:flex md:flex-col md:fixed md:inset-y-0 md:w-64
                      glass !rounded-none border-r border-white/40 dark:border-white/5
                      z-30">
            <!-- Brand -->
            <div class="px-5 h-16 flex items-center border-b border-ink-200/40 dark:border-white/5">
                <Link href="/" class="flex items-center gap-2.5 group sun-wrap relative">
                    <div class="relative w-9 h-9 rounded-xl bg-gradient-to-br from-solar-500 to-solar-600
                                grid place-items-center shadow-glow-solar
                                transition-transform duration-500 ease-spring group-hover:scale-110 group-hover:rotate-12">
                        <svg viewBox="0 0 32 32" class="w-5 h-5 animate-sun-rotate" aria-hidden="true" style="animation-duration: 24s;">
                            <g transform="translate(16 16)">
                                <g v-for="i in 8" :key="i" :transform="`rotate(${i * 45})`">
                                    <rect x="-1.2" y="-12" width="2.4" height="4.5" rx="1.2" fill="white" />
                                </g>
                                <circle cx="0" cy="0" r="5.5" fill="white" />
                                <rect x="-0.6" y="-3.5" width="1.2" height="7" rx="0.6" fill="#0b0f1a" />
                            </g>
                        </svg>
                        <div class="sun-lens"></div>
                    </div>
                    <div class="leading-tight">
                        <div class="font-display font-extrabold text-base tracking-tight text-gradient-aurora">Solar</div>
                        <div class="font-display text-[10px] uppercase tracking-[0.18em] text-ink-400">Money</div>
                    </div>
                </Link>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-3 py-4 space-y-5 overflow-y-auto">
                <div v-for="group in nav" :key="group.section">
                    <div class="px-3 mb-1.5 text-[11px] font-medium text-ink-400/70 dark:text-ink-500/80 select-none">
                        {{ group.section }}
                    </div>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items" :key="item.name"
                            :href="route(item.route)"
                            :class="[
                                'nav-item group',
                                isActive(item.route) ? 'nav-item-active' : ''
                            ]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px] shrink-0"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            <span class="truncate">{{ item.name }}</span>
                            <span v-if="isActive(item.route)" class="ml-auto w-1.5 h-1.5 rounded-full bg-primary-500"></span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- User card at bottom -->
            <div class="p-3 border-t border-ink-200/60 dark:border-ink-800/60">
                <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-ink-100/60 dark:hover:bg-ink-800/60 transition-colors group">
                    <div class="relative w-10 h-10 rounded-full
                                bg-gradient-to-br from-primary-500 to-primary-700
                                grid place-items-center text-white font-display font-bold text-sm
                                shadow-soft">
                        {{ initials(user?.name) }}
                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full
                                     bg-emerald-500 border-2 border-white dark:border-ink-900"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ user?.name }}</p>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ user?.email }}</p>
                    </div>
                    <button @click="userMenuOpen = !userMenuOpen"
                            class="p-1 rounded text-ink-400 hover:text-ink-700 dark:hover:text-ink-200
                                   transition-colors cursor-pointer">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                </div>
                <Transition
                    enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div v-if="userMenuOpen" class="mt-1.5 card p-1 text-sm">
                        <Link :href="route('profile.edit')" class="block px-3 py-2 rounded-lg hover:bg-ink-100 dark:hover:bg-ink-800/60 transition-colors">{{ t('app.profile') }}</Link>
                        <Link :href="route('settings.index')" class="block px-3 py-2 rounded-lg hover:bg-ink-100 dark:hover:bg-ink-800/60 transition-colors">{{ t('app.settings') }}</Link>
                        <Link :href="route('settings.idioma.show')" class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-ink-100 dark:hover:bg-ink-800/60 transition-colors">
                            <span>{{ t('app.language') }}</span>
                            <span class="text-[11px] text-ink-400/80 font-mono">
                                {{ page.props.app?.locale || 'pt-BR' }}
                            </span>
                        </Link>
                        <button @click="logout" class="block w-full text-left px-3 py-2 rounded-lg text-expense hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">{{ t('app.logout') }}</button>
                    </div>
                </Transition>
            </div>
        </aside>

        <!-- ─── Mobile sidebar (drawer) ─── -->
        <Transition
            enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="sidebarOpen" class="md:hidden fixed inset-0 z-40 bg-ink-950/30 backdrop-blur-md" @click="sidebarOpen = false"></div>
        </Transition>
        <Transition
            enter-active-class="transition-all duration-500 ease-spring" enter-from-class="-translate-x-full opacity-0" enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200" leave-from-class="translate-x-0" leave-to-class="-translate-x-full">
            <aside v-if="sidebarOpen"
                   class="md:hidden fixed inset-y-0 left-0 z-50 w-72 glass !rounded-none flex flex-col">
                <div class="flex items-center justify-between px-5 h-16 border-b border-ink-200/40 dark:border-white/5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-solar-500 to-solar-600 grid place-items-center shadow-glow-solar">
                            <svg viewBox="0 0 32 32" class="w-5 h-5 animate-sun-rotate" style="animation-duration: 24s;" aria-hidden="true">
                                <g transform="translate(16 16)">
                                    <g v-for="i in 8" :key="i" :transform="`rotate(${i * 45})`">
                                        <rect x="-1.2" y="-12" width="2.4" height="4.5" rx="1.2" fill="white" />
                                    </g>
                                    <circle cx="0" cy="0" r="5.5" fill="white" />
                                </g>
                            </svg>
                        </div>
                        <span class="font-display font-extrabold text-base text-gradient-aurora">Solar Money</span>
                    </div>
                    <button @click="sidebarOpen = false" class="p-1 cursor-pointer text-ink-600 dark:text-ink-300">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 p-3 space-y-5 overflow-y-auto">
                    <div v-for="group in nav" :key="group.section">
                        <div class="px-3 mb-1.5 text-[11px] font-medium text-ink-400/70 select-none">{{ group.section }}</div>
                        <Link v-for="item in group.items" :key="item.name" :href="route(item.route)" @click="sidebarOpen = false"
                              :class="['nav-item', isActive(item.route) ? 'nav-item-active' : '']">
                            <svg class="w-[18px] h-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            {{ item.name }}
                        </Link>
                    </div>
                </nav>
            </aside>
        </Transition>

        <!-- ─── Main content area ─── -->
        <div class="md:pl-64 flex flex-col min-h-screen">
            <!-- Top bar — liquid glass -->
            <header class="sticky top-0 z-20 glass !rounded-none border-b border-white/30 dark:border-white/5">
                <div class="flex items-center justify-between gap-3 h-16 px-4 md:px-6">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <button @click="sidebarOpen = true"
                                class="md:hidden p-1.5 -ml-1 text-ink-600 dark:text-ink-300 cursor-pointer">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <h1 class="font-display text-lg font-bold hidden sm:block truncate">{{ title }}</h1>

                        <div ref="searchWrapper" class="relative flex-1 max-w-md">
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                                </svg>
                                <input ref="searchInput" v-model="searchQuery"
                                       @focus="searchOpen = true" @keydown="onSearchKeydown"
                                       type="text" placeholder="Buscar... (pressione /)"
                                       class="w-full pl-9 pr-16 py-2 text-sm rounded-xl
                                              bg-ink-100/60 dark:bg-ink-800/60
                                              border border-transparent
                                              focus:border-primary-500 focus:bg-white dark:focus:bg-ink-900
                                              focus:ring-2 focus:ring-primary-500/20
                                              outline-none transition-all" />
                                <kbd class="hidden sm:flex absolute right-2 top-1/2 -translate-y-1/2 px-1.5 py-0.5
                                           text-[10px] font-mono text-ink-500 bg-white dark:bg-ink-900
                                           border border-ink-200 dark:border-ink-700 rounded">/</kbd>
                            </div>

                            <Transition
                                enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
                                <div v-if="searchOpen && searchQuery.trim().length >= 2"
                                     class="absolute left-0 right-0 mt-2 card p-0 max-h-[70vh] overflow-y-auto z-50">
                                    <div v-if="searchLoading" class="p-4 text-sm text-ink-500 text-center">Buscando...</div>
                                    <div v-else-if="!searchHasResults()" class="p-6 text-sm text-ink-500 text-center">
                                        Nenhum resultado para "<strong>{{ searchQuery }}</strong>"
                                    </div>
                                    <div v-else>
                                        <div v-if="searchResults.accounts.length" class="border-b border-ink-100 dark:border-ink-800">
                                            <div class="px-3 py-1.5 text-[11px] font-medium text-ink-500 bg-ink-50/40 dark:bg-ink-800/30">Contas</div>
                                            <button v-for="a in searchResults.accounts" :key="'acc-' + a.id" @click="goTo(route('accounts.index', { search: a.name }))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-ink-50 dark:hover:bg-ink-800/60">
                                                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: a.color || '#94a3b8' }"></span>
                                                <span class="text-sm font-medium flex-1 truncate">{{ a.name }}</span>
                                                <span class="text-xs text-ink-400">{{ a.type }}</span>
                                            </button>
                                        </div>
                                        <div v-if="searchResults.categories.length" class="border-b border-ink-100 dark:border-ink-800">
                                            <div class="px-3 py-1.5 text-[11px] font-medium text-ink-500 bg-ink-50/40 dark:bg-ink-800/30">Categorias</div>
                                            <button v-for="c in searchResults.categories" :key="'cat-' + c.id" @click="goTo(route('transactions.index', { category_ids: [c.id] }))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-ink-50 dark:hover:bg-ink-800/60">
                                                <span class="text-base">{{ c.icon || '📦' }}</span>
                                                <span class="text-sm font-medium flex-1 truncate">{{ c.name }}</span>
                                                <span class="text-xs text-ink-400">{{ c.type === 'income' ? 'Receita' : 'Despesa' }}</span>
                                            </button>
                                        </div>
                                        <div v-if="searchResults.tags.length" class="border-b border-ink-100 dark:border-ink-800">
                                            <div class="px-3 py-1.5 text-[11px] font-medium text-ink-500 bg-ink-50/40 dark:bg-ink-800/30">Tags</div>
                                            <button v-for="t in searchResults.tags" :key="'tag-' + t.id" @click="goTo(route('transactions.index', { search: t.name }))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-ink-50 dark:hover:bg-ink-800/60">
                                                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: t.color || '#94a3b8' }"></span>
                                                <span class="text-sm font-medium">#{{ t.name }}</span>
                                            </button>
                                        </div>
                                        <div v-if="searchResults.transactions.length">
                                            <div class="px-3 py-1.5 text-[11px] font-medium text-ink-500 bg-ink-50/40 dark:bg-ink-800/30">Transações</div>
                                            <button v-for="t in searchResults.transactions" :key="'tx-' + t.id" @click="goTo(route('transactions.edit', t.id))" class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-ink-50 dark:hover:bg-ink-800/60">
                                                <span class="w-2 h-2 rounded-full" :class="t.type === 'income' ? 'bg-emerald-500' : (t.type === 'transfer' ? 'bg-blue-500' : 'bg-rose-500')"></span>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium truncate">{{ t.description }}</p>
                                                    <p class="text-xs text-ink-400 truncate">{{ formatDate(t.date) }} · {{ t.account?.name }}</p>
                                                </div>
                                                <span class="text-sm font-semibold tabular-nums" :class="t.type === 'income' ? 'text-income' : 'text-expense'">
                                                    {{ t.type === 'income' ? '+' : '-' }}{{ formatCents(Math.abs(t.amount_cents)) }}
                                                </span>
                                            </button>
                                        </div>
                                        <div class="border-t border-ink-100 dark:border-ink-800 p-2 text-center">
                                            <button @click="goTo(route('transactions.index', { search: searchQuery }))" class="text-xs text-primary-600 hover:underline">
                                                Ver todos ({{ totalResults() }}) em Transações →
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button @click="toggleTheme"
                                class="p-2 rounded-xl hover:bg-ink-100 dark:hover:bg-ink-800/60
                                       text-ink-600 dark:text-ink-300 transition-colors cursor-pointer"
                                :title="isDark ? 'Modo claro' : 'Modo escuro'">
                            <svg v-if="isDark" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="4" />
                                <path stroke-linecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                            </svg>
                            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                            </svg>
                        </button>
                        <Link :href="route('transactions.create')" class="btn-primary hidden sm:inline-flex text-xs">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Nova transação
                        </Link>
                    </div>
                </div>
            </header>

            <!--
                Email verification banner (FASE 4D).
                Amber alert pinned to the top of the main content area
                (not the sidebar) when the authenticated user has not
                yet confirmed their email. Dismissable for the session.
            -->
            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0 -translate-y-2">
                <div
                    v-if="isUnverified && !bannerDismissed"
                    class="mx-4 md:mx-6 lg:mx-8 mt-4 md:mt-6"
                    role="alert"
                >
                    <div class="flex items-start gap-3 p-3.5 rounded-2xl
                                bg-amber-50/85 dark:bg-amber-500/10
                                border border-amber-200/70 dark:border-amber-500/30
                                shadow-soft
                                text-amber-900 dark:text-amber-200">
                        <!-- Amber envelope icon -->
                        <div class="w-9 h-9 rounded-xl grid place-items-center shrink-0
                                    bg-amber-100 dark:bg-amber-500/20
                                    text-amber-700 dark:text-amber-300">
                            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="3" />
                                <path d="M3 7l9 6 9-6" />
                            </svg>
                        </div>

                        <div class="flex-1 min-w-0 text-sm leading-snug">
                            <p class="font-semibold">Confirme seu email para liberar todas as funcionalidades.</p>
                            <p class="mt-0.5 text-amber-800/85 dark:text-amber-200/85 text-xs">
                                Enviamos um link para
                                <span class="font-semibold break-all">{{ user?.email }}</span>
                                — o link expira em 60 minutos.
                            </p>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <button
                                type="button"
                                @click="resendVerification"
                                :disabled="resendState.processing || resendState.cooldown"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold
                                       bg-amber-600 hover:bg-amber-700
                                       text-white shadow-sm
                                       transition-colors duration-200
                                       disabled:opacity-60 disabled:cursor-not-allowed
                                       cursor-pointer"
                            >
                                <span v-if="resendState.processing">Enviando...</span>
                                <span v-else-if="resendState.cooldown">Aguarde 30s</span>
                                <span v-else>Reenviar</span>
                            </button>
                            <button
                                type="button"
                                @click="logout"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold
                                       text-amber-800 dark:text-amber-200
                                       hover:bg-amber-100 dark:hover:bg-amber-500/20
                                       transition-colors duration-200 cursor-pointer"
                            >
                                Sair
                            </button>
                            <button
                                type="button"
                                @click="dismissBanner"
                                class="p-1.5 rounded-lg text-amber-700/80 dark:text-amber-300/80
                                       hover:bg-amber-100 dark:hover:bg-amber-500/20
                                       transition-colors duration-200 cursor-pointer"
                                aria-label="Fechar"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <main class="flex-1 px-4 md:px-6 lg:px-8 py-6 md:py-8 pb-32 md:pb-16 max-w-7xl w-full mx-auto">
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    mode="out-in"
                >
                    <div :key="$page.url">
                        <slot />
                    </div>
                </Transition>
            </main>

            <!-- Mobile bottom bar — liquid glass -->
            <nav class="md:hidden fixed bottom-0 inset-x-0 z-30
                         glass !rounded-none border-t border-white/30 dark:border-white/5">
                <div class="grid grid-cols-5 h-16 relative">
                    <Link :href="route('dashboard')"
                          :class="['flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium',
                                   isActive('dashboard') ? 'text-primary-600 dark:text-primary-400' : 'text-ink-400']">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" />
                        </svg>
                        Início
                    </Link>
                    <Link :href="route('transactions.index')"
                          :class="['flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium',
                                   isActive('transactions.index') ? 'text-primary-600 dark:text-primary-400' : 'text-ink-400']">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h12" />
                        </svg>
                        Trans.
                    </Link>
                    <Link :href="route('transactions.create')"
                          class="flex flex-col items-center justify-center -mt-5">
                        <span class="w-14 h-14 rounded-full btn-primary !p-0
                                     text-white grid place-items-center
                                     border-4 border-white dark:border-ink-950
                                     transition-transform active:scale-95">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                    </Link>
                    <Link :href="route('investments.index')"
                          :class="['flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium',
                                   isActive('investments.index') ? 'text-primary-600 dark:text-primary-400' : 'text-ink-400']">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        Invest.
                    </Link>
                    <Link :href="route('accounts.index')"
                          :class="['flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium',
                                   isActive('accounts.index') ? 'text-primary-600 dark:text-primary-400' : 'text-ink-400']">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h18v4H3V8zm0 6h18v2H3v-2z" />
                        </svg>
                        Mais
                    </Link>
                </div>
            </nav>
        </div>
        <AppFooter />
    </div>
</template>

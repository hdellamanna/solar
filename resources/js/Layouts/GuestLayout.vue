<script setup>
import AppFooter from '@/Components/AppFooter.vue';

import { Link } from '@inertiajs/vue3';
import { useT } from '@/Composables/useT';

defineProps({
    title: { type: String, default: null },
});

const { t } = useT();
</script>

<template>
    <div class="min-h-screen flex bg-ink-50 dark:bg-ink-950">
        <!-- LEFT: visual brand panel (hidden on mobile) -->
        <aside class="hidden lg:flex flex-col w-[44%] xl:w-[48%] relative overflow-hidden bg-ink-950 text-white mesh-canvas">
            <!-- Layered gradient mesh background -->
            <div class="absolute inset-0 bg-mesh-dark"></div>
            <div class="absolute -top-40 -right-40 w-[600px] h-[600px] rounded-full opacity-50 blur-3xl animate-mesh-drift-a"
                 style="background: radial-gradient(circle, rgba(255, 138, 61, 0.55), transparent 60%);"></div>
            <div class="absolute -bottom-40 -left-40 w-[600px] h-[600px] rounded-full opacity-40 blur-3xl animate-mesh-drift-b"
                 style="background: radial-gradient(circle, rgba(124, 58, 237, 0.55), transparent 60%);"></div>

            <!-- Decorative slow-rotating sun ring (subtle) -->
            <svg
                class="absolute -top-32 -right-32 w-[700px] h-[700px] opacity-25 animate-sun-rotate"
                viewBox="0 0 512 512" aria-hidden="true"
                style="animation-duration: 90s;"
            >
                <g transform="translate(256 256)">
                    <g v-for="i in 12" :key="i" :transform="`rotate(${i * 30})`">
                        <rect x="-3" y="-240" width="6" height="40" rx="3" fill="url(#authGradient)" />
                    </g>
                    <defs>
                        <linearGradient id="authGradient" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#FF8A3D" />
                            <stop offset="100%" stop-color="#FFC93C" />
                        </linearGradient>
                    </defs>
                </g>
            </svg>

            <!-- Content stack -->
            <div class="relative z-10 flex flex-col h-full p-10 xl:p-14">
                <!-- Top: brand -->
                <Link href="/" class="inline-flex items-center gap-3 group sun-wrap">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-solar-500 to-solar-600
                                grid place-items-center shadow-glow-solar
                                transition-transform duration-500 ease-spring group-hover:scale-110 group-hover:rotate-12">
                        <!-- Mini sun mark -->
                        <svg viewBox="0 0 32 32" class="w-7 h-7 animate-sun-rotate" aria-hidden="true" style="animation-duration: 30s;">
                            <g transform="translate(16 16)">
                                <g v-for="i in 8" :key="i" :transform="`rotate(${i * 45})`">
                                    <rect x="-1.5" y="-13" width="3" height="5" rx="1.5" fill="white" />
                                </g>
                                <circle cx="0" cy="0" r="5.5" fill="white" />
                                <rect x="-0.7" y="-3.5" width="1.4" height="7" rx="0.7" fill="#0b0f1a" />
                            </g>
                        </svg>
                        <div class="sun-lens"></div>
                    </div>
                    <div>
                        <div class="text-xl font-display font-bold tracking-tight text-gradient-aurora">{{ t('app.brand') }}</div>
                        <div class="text-[11px] uppercase tracking-[0.18em] text-white/50">{{ t('app.tagline') }}</div>
                    </div>
                </Link>

                <!-- Center: marketing copy -->
                <div class="flex-1 flex flex-col justify-center max-w-md">
                    <h1 class="font-display text-display-md xl:text-display-lg leading-[1.05] tracking-tight">
                        Mais que um app.
                        <br />
                        <span class="text-gradient-solar">Luz sobre seu dinheiro.</span>
                    </h1>
                    <p class="mt-6 text-white/70 text-lg leading-relaxed">
                        Investimentos, dívidas, metas, assinaturas e PIX em um só lugar —
                        com conversão multi-moeda automática e um simulador SAC vs Price
                        pra você ver o final do jogo antes de jogar.
                    </p>

                    <!-- Mini visual cards floating — liquid glass -->
                    <div class="mt-12 grid grid-cols-2 gap-3 max-w-sm">
                        <div class="card-glass p-4 animate-fade-up stagger-1">
                            <div class="text-xs text-white/60">Saldo</div>
                            <div class="font-mono text-xl mt-1 num">R$ 14.320,50</div>
                            <div class="text-xs text-emerald-300 mt-1">▲ +12,4%</div>
                        </div>
                        <div class="card-glass p-4 translate-y-4 animate-fade-up stagger-2">
                            <div class="text-xs text-white/60">Investido</div>
                            <div class="font-mono text-xl mt-1 num">R$ 8.750,00</div>
                            <div class="text-xs text-solar-300 mt-1">BTC + ITSA4 + LFT</div>
                        </div>
                        <div class="card-glass p-4 -translate-y-2 animate-fade-up stagger-3">
                            <div class="text-xs text-white/60">Dívidas ativas</div>
                            <div class="font-mono text-xl mt-1 num">R$ 25.000</div>
                            <div class="text-xs text-white/60 mt-1">SAC · 36 meses</div>
                        </div>
                        <div class="card-glass p-4 translate-y-2 animate-fade-up stagger-4">
                            <div class="text-xs text-white/60">Assinaturas</div>
                            <div class="font-mono text-xl mt-1 num">R$ 137,88</div>
                            <div class="text-xs text-white/60 mt-1">4 ativas</div>
                        </div>
                    </div>
                </div>

                <!-- Bottom: brand promise -->
                <div class="text-xs text-white/40">
                    Feito com ☀︎ no Brasil · pt-BR first
                </div>
            </div>
        </aside>

        <!-- RIGHT: form area -->
        <main class="flex-1 flex flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-10 relative">
            <!-- Mobile brand (shown only < lg) -->
            <Link href="/" class="flex items-center gap-2 mb-8 lg:hidden">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-solar-500 to-solar-600 grid place-items-center shadow-glow-solar">
                    <svg viewBox="0 0 32 32" class="w-6 h-6" aria-hidden="true">
                        <g transform="translate(16 16)">
                            <g v-for="i in 8" :key="i" :transform="`rotate(${i * 45})`">
                                <rect x="-1.2" y="-12" width="2.4" height="4" rx="1.2" fill="white" />
                            </g>
                            <circle cx="0" cy="0" r="5" fill="white" />
                        </g>
                    </svg>
                </div>
                <span class="text-lg font-display font-bold">{{ t('app.brand') }}</span>
            </Link>

            <div class="w-full max-w-sm">
                <slot />
            </div>
        </main>
        <AppFooter />
    </div>
</template>

<script setup>
/**
 * PwaInstallBanner — non-blocking prompt that fires when the browser has
 * captured a `beforeinstallprompt` and the user has not dismissed the
 * prompt within the last 30 days.
 */
import { usePwaInstall } from '@/Composables/usePwaInstall';

const { shouldShowBanner, install, dismiss } = usePwaInstall();

async function onInstall() {
    await install();
}

function onDismiss() {
    dismiss();
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="shouldShowBanner"
            class="fixed z-40 left-1/2 -translate-x-1/2 bottom-20 md:bottom-6 md:right-6 md:left-auto md:translate-x-0 w-[min(94vw,420px)]"
            role="region"
            aria-label="Instalar Solar"
        >
            <div
                class="card-elevated p-4 flex items-start gap-3 backdrop-blur bg-white/90 dark:bg-slate-900/90"
                style="box-shadow: 0 18px 40px rgba(15,23,42,0.18), 0 4px 12px rgba(15,23,42,0.08);"
            >
                <!-- Mark icon -->
                <div
                    class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center"
                    style="background: linear-gradient(135deg, #FF8A3D 0%, #FFC93C 100%);"
                    aria-hidden="true"
                >
                    <svg viewBox="0 0 512 512" class="w-7 h-7">
                        <g transform="translate(256 256)">
                            <g>
                                <g transform="rotate(0)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(30)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(60)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(90)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(120)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(150)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(180)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(210)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(240)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(270)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(300)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                                <g transform="rotate(330)"><rect x="-16" y="-210" width="32" height="72" rx="16" ry="16" fill="#FAFAF7"/></g>
                            </g>
                            <circle cx="0" cy="0" r="96" fill="#FAFAF7"/>
                        </g>
                        <rect x="237" y="170" width="38" height="172" rx="19" ry="19" fill="#0F172A"/>
                        <circle cx="256" cy="170" r="10" fill="#0F172A"/>
                    </svg>
                </div>

                <!-- Copy -->
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                        Instalar Solar
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">
                        Adicione à tela inicial para acesso rápido, offline e sem barra do navegador.
                    </p>

                    <div class="flex items-center gap-2 mt-3">
                        <button
                            type="button"
                            @click="onInstall"
                            class="btn-primary text-xs px-3 py-1.5"
                        >
                            Instalar
                        </button>
                        <button
                            type="button"
                            @click="onDismiss"
                            class="btn-ghost text-xs px-3 py-1.5"
                        >
                            Agora não
                        </button>
                    </div>
                </div>

                <!-- Close icon -->
                <button
                    type="button"
                    @click="onDismiss"
                    aria-label="Dispensar"
                    class="flex-shrink-0 -mt-1 -mr-1 p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </Transition>
</template>

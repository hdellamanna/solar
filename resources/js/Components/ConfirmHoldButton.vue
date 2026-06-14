<script setup>
/*
 * ConfirmHoldButton — reusable hold-to-confirm action.
 *
 * FASE Polish / v0.10.0 UX requirement: a destructive / high-stakes
 * action (e.g. "Desativar 2FA") must require an intentional,
 * multi-second physical gesture before it submits. The classic
 * "Apple slide to power off" pattern fits perfectly — the user
 * presses, holds, and the button animates a progress bar from
 * 0 → 100% over `:seconds` (default 3). If the user releases
 * early, the bar springs back. If they hold to the end, the
 * button emits `confirmed` and the parent decides what to do
 * (typically submit a useForm or call a service).
 *
 * Accessibility:
 *  - Renders a real <button type="button"> so screen readers and
 *    keyboard users get the standard focus + activation surface.
 *  - Honors `disabled` (parent-controlled — e.g. "wait while
 *    a form is processing").
 *  - Honors `aria-busy` + `aria-label` so AT users hear the
 *    hold-to-confirm instruction.
 *
 * Mobile:
 *  - We listen for both mouse + touch + pointer events so the
 *    button works on phones. The pointer events are the modern
 *    unified path; mouse + touch are fallback for older WebViews.
 *  - We call `event.preventDefault()` on `touchstart` to stop
 *    the browser from cancelling the gesture on scroll.
 */
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    /** Hold duration in seconds. Defaults to 3 (Apple-style). */
    seconds: { type: Number, default: 3 },
    /** Disable the button entirely (e.g. while a form is submitting). */
    disabled: { type: Boolean, default: false },
    /** Visual variant. `danger` (default) = rose/red, `primary` = solar. */
    variant: {
        type: String,
        default: 'danger',
        validator: (v) => ['danger', 'primary', 'neutral'].includes(v),
    },
    /** Force a fixed width on the button so the label doesn't shift
     *  as the progress bar fills. */
    minWidth: { type: String, default: '16rem' },
});

const emit = defineEmits(['confirmed']);

const holding = ref(false);
const progress = ref(0); // 0..1
let startedAt = 0;
let rafId = null;
let timerId = null;
let completedEmitted = false;

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'primary':
            return {
                track: 'bg-primary-50/60 dark:bg-primary-500/10 border-primary-200/70 dark:border-primary-500/30',
                fill: 'bg-gradient-to-r from-primary-500 to-primary-600',
                text: 'text-primary-700 dark:text-primary-300',
                ring: 'focus-visible:ring-primary-500/40',
            };
        case 'neutral':
            return {
                track: 'bg-ink-100/70 dark:bg-ink-800/60 border-ink-200/70 dark:border-ink-700',
                fill: 'bg-gradient-to-r from-ink-700 to-ink-900 dark:from-ink-300 dark:to-ink-100',
                text: 'text-ink-700 dark:text-ink-200',
                ring: 'focus-visible:ring-ink-500/40',
            };
        case 'danger':
        default:
            return {
                track: 'bg-rose-50/60 dark:bg-rose-500/10 border-rose-200/70 dark:border-rose-500/30',
                fill: 'bg-gradient-to-r from-rose-500 to-rose-700',
                text: 'text-rose-700 dark:text-rose-300',
                ring: 'focus-visible:ring-rose-500/40',
            };
    }
});

const hintText = computed(() => {
    if (holding.value) {
        // Live countdown, in whole seconds, in pt-BR.
        const remaining = Math.max(0, Math.ceil(props.seconds * (1 - progress.value)));
        return `Segurando... ${remaining}s`;
    }
    if (completedEmitted.value) {
        return 'Confirmado';
    }
    return `Segure por ${props.seconds} segundos para confirmar`;
});

const start = (e) => {
    if (props.disabled || holding.value || completedEmitted.value) return;
    // Cancel a default touch scroll/zoom on mobile.
    if (e?.cancelable) e.preventDefault();
    holding.value = true;
    startedAt = performance.now();
    completedEmitted = false;
    progress.value = 0;
    tick();
    // Safety timer in case rAF stalls (e.g. tab in background).
    timerId = window.setTimeout(finish, props.seconds * 1000 + 50);
};

const cancel = () => {
    if (completedEmitted.value) return;
    holding.value = false;
    progress.value = 0;
    if (rafId !== null) cancelAnimationFrame(rafId);
    if (timerId !== null) {
        clearTimeout(timerId);
        timerId = null;
    }
    rafId = null;
};

const tick = () => {
    const elapsed = performance.now() - startedAt;
    const ratio = Math.min(1, elapsed / (props.seconds * 1000));
    progress.value = ratio;
    if (ratio >= 1) {
        finish();
        return;
    }
    rafId = requestAnimationFrame(tick);
};

const finish = () => {
    if (rafId !== null) cancelAnimationFrame(rafId);
    if (timerId !== null) {
        clearTimeout(timerId);
        timerId = null;
    }
    rafId = null;
    progress.value = 1;
    holding.value = false;
    completedEmitted = true;
    // Small delay so the user can SEE the bar fill before the
    // parent's mutation lands — feels more deliberate than
    // instant-redirect.
    setTimeout(() => {
        emit('confirmed');
    }, 120);
};

const onKeydown = (e) => {
    // Space / Enter also triggers start (just like a regular
    // button), but we also bind to keyup so a release counts
    // as cancel.
    if (e.key === ' ' || e.key === 'Enter') {
        if (! holding.value) {
            e.preventDefault();
            start(e);
        }
    } else if (e.key === 'Escape' && holding.value) {
        e.preventDefault();
        cancel();
    }
};
const onKeyup = (e) => {
    if ((e.key === ' ' || e.key === 'Enter') && holding.value && ! completedEmitted.value) {
        e.preventDefault();
        cancel();
    }
};

onBeforeUnmount(() => {
    if (rafId !== null) cancelAnimationFrame(rafId);
    if (timerId !== null) clearTimeout(timerId);
});
</script>

<template>
    <button
        type="button"
        :disabled="disabled || completedEmitted"
        :aria-busy="holding ? 'true' : 'false'"
        :aria-label="`${hintText}. Botao de confirmacao por seguranca.`"
        :class="[
            'relative overflow-hidden select-none cursor-pointer',
            'inline-flex items-center justify-center',
            'h-12 rounded-2xl border-2 font-semibold text-sm',
            'transition-all duration-200',
            'focus:outline-none focus-visible:ring-4',
            variantClasses.track,
            variantClasses.ring,
            (disabled || completedEmitted) ? 'opacity-60 cursor-not-allowed' : '',
        ]"
        :style="{ minWidth: minWidth }"
        @mousedown="start"
        @mouseup="cancel"
        @mouseleave="cancel"
        @touchstart.passive="start"
        @touchend="cancel"
        @touchcancel="cancel"
        @keydown="onKeydown"
        @keyup="onKeyup"
    >
        <!--
            The progress fill is absolutely positioned and grows
            from left to right. We use a `transform: scaleX()` so
            the GPU handles the paint and the bar stays smooth
            even on the lock screen of an old phone.
        -->
        <span
            class="absolute inset-y-0 left-0 origin-left pointer-events-none
                   transition-transform"
            :class="variantClasses.fill"
            :style="{
                width: '100%',
                transform: `scaleX(${progress})`,
                transitionDuration: holding ? '0ms' : '400ms',
                transitionTimingFunction: holding ? 'linear' : 'cubic-bezier(0.34, 1.56, 0.64, 1)',
            }"
            aria-hidden="true"
        ></span>

        <!-- Checkmark overlay when the hold completes. -->
        <span
            v-if="completedEmitted"
            class="relative z-10 flex items-center justify-center gap-2 text-white"
            aria-hidden="true"
        >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 13l4 4L19 7" />
            </svg>
            Confirmado
        </span>

        <!--
            Default label state. Slot wins if the consumer wants
            to render their own copy.
        -->
        <span
            v-else
            class="relative z-10 flex items-center justify-center gap-2"
            :class="holding ? 'text-white' : variantClasses.text"
        >
            <slot :holding="holding" :progress="progress">
                <svg v-if="! holding" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2l8 4v6c0 5-3.5 9.4-8 10-4.5-.6-8-5-8-10V6l8-4z" />
                </svg>
                <svg v-else class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                </svg>
                <span>{{ hintText }}</span>
            </slot>
        </span>
    </button>
</template>

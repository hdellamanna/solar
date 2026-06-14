/**
 * useMotionPreference — exposes the user's motion preference (auto/reduced/full)
 * + 3 granular flags (backdrop, spring, parallax) as reactive computeds.
 *
 * Reads from Inertia shared props (set server-side by
 * {@see \App\Services\UserMotionPreference}). The 3-flags have already been
 * resolved against the OS-level `prefers-reduced-motion` query.
 *
 * Listens to `matchMedia('(prefers-reduced-motion: reduce)')` change events
 * and dispatches a `solar:motion-change` custom event so other components
 * (the AppFooter, the AiAgentSlot, the ConfirmHoldButton) can re-sync their
 * local state without re-rendering the whole tree.
 */
import { computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useMotionPreference() {
    const page = usePage();

    const motion = computed(() => page.props.motion ?? {});
    const preference = computed(() => motion.value.preference ?? 'auto');
    const backdrop = computed(() => motion.value.backdrop !== false);
    const spring = computed(() => motion.value.spring !== false);
    const parallax = computed(() => motion.value.parallax !== false);

    const isReduced = computed(() => preference.value === 'reduced');
    const isFull = computed(() => preference.value === 'full');

    /**
     * Apply the resolved state to the <html> data attributes. Called on mount
     * and whenever any of the 3 flags change. Idempotent.
     */
    function applyToDocument() {
        if (typeof document === 'undefined') return;
        const root = document.documentElement;
        root.setAttribute('data-motion', preference.value);
        root.setAttribute('data-motion-backdrop', backdrop.value ? '1' : '0');
        root.setAttribute('data-motion-spring', spring.value ? '1' : '0');
        root.setAttribute('data-motion-parallax', parallax.value ? '1' : '0');
    }

    /**
     * OS-level listener. When the user toggles "Reduce motion" in their
     * system settings without reloading, the resolvedMotion might change
     * even if the user's stored preference is `auto`. We can't recompute
     * here (preference is server-resolved), so we just re-broadcast the
     * event so the app can refresh from the server if needed.
     */
    let mq = null;
    function onMqChange() {
        if (typeof window === 'undefined') return;
        window.dispatchEvent(new CustomEvent('solar:motion-change', {
            detail: { source: 'os' },
        }));
    }

    onMounted(() => {
        applyToDocument();
        if (typeof window === 'undefined') return;
        mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (mq && mq.addEventListener) {
            mq.addEventListener('change', onMqChange);
        } else if (mq && mq.addListener) {
            // Safari < 14 fallback
            mq.addListener(onMqChange);
        }
    });

    onUnmounted(() => {
        if (mq && mq.removeEventListener) {
            mq.removeEventListener('change', onMqChange);
        } else if (mq && mq.removeListener) {
            mq.removeListener(onMqChange);
        }
    });

    return {
        preference,
        backdrop,
        spring,
        parallax,
        isReduced,
        isFull,
        applyToDocument,
    };
}

/**
 * usePwaInstall — exposes the deferred browser install prompt.
 *
 * Browsers fire `beforeinstallprompt` when the PWA meets installability
 * criteria. We capture that event, expose a reactive `canInstall` ref,
 * and provide a `prompt()` that triggers the native dialog. The user can
 * also dismiss the banner — we remember that choice in localStorage for
 * 30 days so we do not nag them.
 *
 * Usage:
 *   import { usePwaInstall } from '@/Composables/usePwaInstall';
 *   const { canInstall, isDismissed, install } = usePwaInstall();
 */
import { ref, computed } from 'vue';

const STORAGE_KEY = 'solar-pwa-install-dismissed';
const DISMISS_DURATION_DAYS = 30;
const DISMISS_DURATION_MS = DISMISS_DURATION_DAYS * 24 * 60 * 60 * 1000;

/* Module-singleton state — multiple components can call usePwaInstall()
 * and they all share the same captured prompt. */
const deferredPrompt = ref(null);
const installed = ref(false);
const dismissedUntil = ref(0);

function readDismissed() {
    if (typeof localStorage === 'undefined') return 0;
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return 0;
    const ts = Number.parseInt(raw, 10);
    return Number.isFinite(ts) ? ts : 0;
}

function writeDismissed() {
    if (typeof localStorage === 'undefined') return;
    const until = Date.now() + DISMISS_DURATION_MS;
    localStorage.setItem(STORAGE_KEY, String(until));
    dismissedUntil.value = until;
}

function onBeforeInstallPrompt(e) {
    e.preventDefault();
    deferredPrompt.value = e;
}

function onAppInstalled() {
    installed.value = true;
    deferredPrompt.value = null;
    if (typeof localStorage !== 'undefined') {
        localStorage.removeItem(STORAGE_KEY);
    }
}

let listenersAttached = false;
function ensureGlobalListeners() {
    if (listenersAttached || typeof window === 'undefined') return;
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onAppInstalled);
    listenersAttached = true;
}

export function usePwaInstall() {
    ensureGlobalListeners();

    if (typeof window !== 'undefined' && dismissedUntil.value === 0) {
        dismissedUntil.value = readDismissed();
    }

    const canInstall = computed(
        () => !!deferredPrompt.value && !installed.value,
    );

    const isDismissed = computed(
        () => Date.now() < dismissedUntil.value,
    );

    const shouldShowBanner = computed(
        () => canInstall.value && !isDismissed.value,
    );

    async function install() {
        const ev = deferredPrompt.value;
        if (!ev) return { outcome: 'unavailable' };
        ev.prompt();
        try {
            const choice = await ev.userChoice;
            deferredPrompt.value = null;
            if (choice && choice.outcome === 'accepted') {
                installed.value = true;
            } else {
                writeDismissed();
            }
            return choice || { outcome: 'unknown' };
        } catch (err) {
            return { outcome: 'error', error: err };
        }
    }

    function dismiss() {
        writeDismissed();
    }

    return {
        canInstall,
        isDismissed,
        shouldShowBanner,
        installed,
        install,
        dismiss,
    };
}

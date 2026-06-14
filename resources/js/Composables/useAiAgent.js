/**
 * useAiAgent — state machine for the FASE 8 AI agent slot (chrome only,
 * wired in v0.11.0; the actual Groq + tool-use integration lands in FASE 8).
 *
 * Exposes:
 *  - isOpen: reactive boolean
 *  - open(), close(), toggle()
 *  - focusInput() — called by the slot after the panel mounts
 *  - registerInput(el) — slot passes its <input> ref so we can focus it
 *
 * Listens for Cmd+K (Mac) / Ctrl+K (other) at the document level and
 * dispatches solar:ai-agent-toggle so the slot can open/close. Escape closes.
 *
 * The submit handler is intentionally a no-op stub — FASE 8 replaces it
 * with a real `fetch('/api/ai/chat', { method: 'POST', body: ... })` call.
 */
import { onMounted, onUnmounted, ref } from 'vue';

const isOpen = ref(false);
let inputEl = null;
let lastFocused = null;

function onKeydown(event) {
    // Cmd+K on Mac, Ctrl+K elsewhere. Don't intercept browser shortcuts
    // (Cmd+L for address bar etc.) — only when the modifier matches.
    const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
    const cmdK = isMac ? event.metaKey : event.ctrlKey;
    if (cmdK && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        isOpen.value = !isOpen.value;
        return;
    }
    if (isOpen.value && event.key === 'Escape') {
        event.preventDefault();
        isOpen.value = false;
    }
}

export function useAiAgent() {
    function open() {
        if (typeof document !== 'undefined') {
            lastFocused = document.activeElement;
        }
        isOpen.value = true;
    }
    function close() {
        isOpen.value = false;
        // Restore focus to the previously-focused element (a11y).
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }
    function toggle() {
        if (isOpen.value) {
            close();
        } else {
            open();
        }
    }
    function registerInput(el) {
        inputEl = el;
    }
    function focusInput() {
        if (inputEl && typeof inputEl.focus === 'function') {
            inputEl.focus();
        }
    }

    onMounted(() => {
        if (typeof document === 'undefined') return;
        document.addEventListener('keydown', onKeydown);
    });
    onUnmounted(() => {
        if (typeof document === 'undefined') return;
        document.removeEventListener('keydown', onKeydown);
    });

    return { isOpen, open, close, toggle, registerInput, focusInput };
}

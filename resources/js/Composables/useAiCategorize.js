/**
 * useAiCategorize — FASE 5 AI category suggestion composable.
 */
import { ref } from 'vue';

export function useAiCategorize() {
    const loading = ref(false);
    const lastResult = ref(null);
    const lastError = ref(null);

    async function suggest(description) {
        loading.value = true;
        lastError.value = null;
        lastResult.value = null;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/api/ai/suggest-category', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ description }),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                lastError.value = body.message || `Erro ${res.status}`;
                return { ok: false, payload: null, message: lastError.value, status: res.status };
            }
            lastResult.value = body;
            return { ok: true, payload: body, message: null, status: res.status };
        } catch (err) {
            const message = err?.message || 'Erro de rede.';
            lastError.value = message;
            return { ok: false, payload: null, message, status: 0 };
        } finally {
            loading.value = false;
        }
    }

    function reset() {
        lastResult.value = null;
        lastError.value = null;
    }

    return { loading, lastResult, lastError, suggest, reset };
}

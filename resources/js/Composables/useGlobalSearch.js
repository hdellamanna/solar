import { ref, watch, onUnmounted } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';

/**
 * Reactive global search composable.
 *
 * Calls GET /api/search?q=... with a 200ms debounce and exposes the
 * grouped result set. Cancels in-flight requests when the query
 * changes again to avoid out-of-order results.
 *
 * @param {object} [options]
 * @param {number} [options.limit=5]    Max results per group.
 * @param {number} [options.debounce=200] Debounce in ms.
 * @param {number} [options.minLength=2] Minimum chars before firing.
 * @returns {{
 *   query: import('vue').Ref<string>,
 *   results: import('vue').Ref<{
 *     accounts: Array<object>,
 *     categories: Array<object>,
 *     transactions: Array<object>,
 *     tags: Array<object>,
 *   }>,
 *   loading: import('vue').Ref<boolean>,
 *   error: import('vue').Ref<string|null>,
 *   hasResults: import('vue').Ref<boolean>,
 *   clear: () => void,
 * }}
 */
export function useGlobalSearch(options = {}) {
    const limit = options.limit ?? 5;
    const debounceMs = options.debounce ?? 200;
    const minLength = options.minLength ?? 2;

    const query = ref('');
    const results = ref({
        accounts: [],
        categories: [],
        transactions: [],
        tags: [],
    });
    const loading = ref(false);
    const error = ref(null);

    let activeToken = 0;

    const run = useDebounceFn(async () => {
        const term = query.value.trim();
        if (term.length < minLength) {
            results.value = { accounts: [], categories: [], transactions: [], tags: [] };
            error.value = null;
            loading.value = false;
            return;
        }
        const token = ++activeToken;
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get('/api/search', {
                params: { q: term, limit },
            });
            if (token !== activeToken) return; // stale
            results.value = {
                accounts: data.accounts ?? [],
                categories: data.categories ?? [],
                transactions: data.transactions ?? [],
                tags: data.tags ?? [],
            };
        } catch (e) {
            if (token !== activeToken) return;
            error.value = e?.response?.data?.message ?? e?.message ?? 'Erro na busca';
            results.value = { accounts: [], categories: [], transactions: [], tags: [] };
        } finally {
            if (token === activeToken) loading.value = false;
        }
    }, debounceMs);

    watch(query, () => { run(); });

    const hasResults = () => {
        const r = results.value;
        return (r.accounts.length + r.categories.length + r.transactions.length + r.tags.length) > 0;
    };

    const clear = () => {
        query.value = '';
        activeToken++;
        loading.value = false;
        error.value = null;
        results.value = { accounts: [], categories: [], transactions: [], tags: [] };
    };

    onUnmounted(() => { activeToken++; });

    return { query, results, loading, error, hasResults, clear };
}

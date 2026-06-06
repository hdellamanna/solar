import { reactive, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Reactive bridge between the Transactions/Index filter form and the
 * URL query string. Reading is done once from `initial` (the prop
 * passed by the controller) and writing pushes a new Inertia GET,
 * preserving scroll and state.
 *
 * @param {object} initial
 * @returns {{
 *   state: import('vue').Reactive<{
 *     search: string,
 *     period: string,
 *     from: string,
 *     to: string,
 *     type: string,
 *     status: string,
 *     account_ids: number[],
 *     category_ids: number[],
 *     amount_min: string,
 *     amount_max: string,
 *   }>,
 *   hasActiveFilters: import('vue').ComputedRef<boolean>,
 *   apply: () => void,
 *   clear: () => void,
 *   toggleAccount: (id: number) => void,
 *   toggleCategory: (id: number) => void,
 * }}
 */
export function useTransactionFilters(initial = {}) {
    const state = reactive({
        search: initial.search ?? '',
        period: initial.period ?? '',
        from: initial.from ?? '',
        to: initial.to ?? '',
        type: initial.type ?? '',
        status: initial.status ?? '',
        account_ids: Array.isArray(initial.account_ids) ? [...initial.account_ids] : [],
        category_ids: Array.isArray(initial.category_ids) ? [...initial.category_ids] : [],
        amount_min: initial.amount_min ?? '',
        amount_max: initial.amount_max ?? '',
    });

    const blank = {
        search: '',
        period: '',
        from: '',
        to: '',
        type: '',
        status: '',
        account_ids: [],
        category_ids: [],
        amount_min: '',
        amount_max: '',
    };

    const buildParams = () => {
        const params = {};
        if (state.search) params.search = state.search;
        if (state.period) params.period = state.period;
        if (state.period === 'custom') {
            if (state.from) params.from = state.from;
            if (state.to) params.to = state.to;
        }
        if (state.type) params.type = state.type;
        if (state.status) params.status = state.status;
        if (state.account_ids.length) params.account_ids = state.account_ids;
        if (state.category_ids.length) params.category_ids = state.category_ids;
        if (state.amount_min !== '' && state.amount_min !== null) params.amount_min = state.amount_min;
        if (state.amount_max !== '' && state.amount_max !== null) params.amount_max = state.amount_max;
        return params;
    };

    const apply = () => {
        router.get(route('transactions.index'), buildParams(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clear = () => {
        Object.assign(state, JSON.parse(JSON.stringify(blank)));
        apply();
    };

    const toggleAccount = (id) => {
        const idx = state.account_ids.indexOf(id);
        if (idx >= 0) state.account_ids.splice(idx, 1);
        else state.account_ids.push(id);
    };
    const toggleCategory = (id) => {
        const idx = state.category_ids.indexOf(id);
        if (idx >= 0) state.category_ids.splice(idx, 1);
        else state.category_ids.push(id);
    };

    const hasActiveFilters = computed(() => Object.keys(buildParams()).length > 0);

    return { state, hasActiveFilters, apply, clear, toggleAccount, toggleCategory };
}

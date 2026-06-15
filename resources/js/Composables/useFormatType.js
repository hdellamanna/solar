import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useLocale } from './useLocale';

/**
 * Returns a `formatType(code)` function for a given enum group
 * (e.g. `account`, `transaction`, `frequency`, `recurrence`,
 * `subscription.status`, `goal.status`).
 *
 * FASE 7 design (per `docs/fase-7-i18n/design.md`):
 *   - The labels live in `lang/{locale}/enums.php`.
 *   - The active locale is exposed via `usePage().props.app.locale`.
 *
 * However, the FASE 7 backend does NOT yet publish the full
 * enums map as an Inertia shared prop — only `app.locale`,
 * `app.available_locales`, `app.brand`. This composable is
 * forward-compatible: if the backend exposes `props.app.enums`
 * later, we read from it; otherwise we fall back to a small
 * built-in dictionary for the few groups the UI needs *today*
 * (account + transaction), so the existing screens keep
 * working with localized labels in dev. A `console.warn` is
 * emitted once per missing group in dev.
 *
 * Usage:
 *   const formatType = useFormatType('account');
 *   formatType('checking'); // → "Conta corrente" / "Cuenta corriente" / "Checking account"
 *
 * @param {string} group - the enum group name (e.g. 'account')
 * @returns {(code: string|null|undefined) => string}
 */
export function useFormatType(group) {
    const page = usePage();
    const { locale } = useLocale();

    return (code) => {
        if (!code) return '';
        // Touch the locale ref so the function is reactive.
        void locale.value;

        // 1) Preferred: read from `props.app.enums` (if/when the
        //    backend publishes it).
        const published = page.props.app?.enums?.[group];
        if (published && typeof published === 'object' && published[code]) {
            return published[code];
        }

        // 2) Fallback: built-in dictionary for the groups the
        //    UI actually renders today. The keys come from
        //    `lang/{locale}/enums.php` — keep them in sync.
        const dict = FALLBACK_ENUMS[locale.value]?.[group];
        if (dict && dict[code]) return dict[code];

        // 3) Last resort: warn in dev and return the raw code so
        //    the UI is still readable.
        if (typeof console !== 'undefined' && !formatType._warned?.has(group)) {
            if (!formatType._warned) formatType._warned = new Set();
            formatType._warned.add(group);
            // eslint-disable-next-line no-console
            console.warn(
                `[useFormatType] no enum map for group "${group}" in locale "${locale.value}". ` +
                'Add props.app.enums to the Inertia shared payload or extend FALLBACK_ENUMS.'
            );
        }
        return String(code);
    };
}

/**
 * Built-in fallback dictionary for the few enum groups the UI
 * renders today. Mirrors the `lang/{locale}/enums.php` shape
 * — keep both in sync.
 */
const FALLBACK_ENUMS = {
    'pt-BR': {
        transaction: {
            income: 'Receita',
            expense: 'Despesa',
            transfer: 'Transferência',
        },
        account: {
            checking: 'Conta corrente',
            savings: 'Poupança',
            credit: 'Cartão de crédito',
            credit_card: 'Cartão de crédito',
            investment: 'Investimento',
            cash: 'Dinheiro',
            multi_currency: 'Multi-moeda',
            other: 'Outro',
            crypto: 'Criptomoedas',
        },
        recurrence: {
            daily: 'Diário',
            weekly: 'Semanal',
            monthly: 'Mensal',
            yearly: 'Anual',
        },
        'subscription.status': {
            active: 'Ativa',
            cancelled: 'Cancelada',
        },
        'goal.status': {
            in_progress: 'Em andamento',
            completed: 'Concluída',
        },
    },
    es: {
        transaction: {
            income: 'Ingreso',
            expense: 'Gasto',
            transfer: 'Transferencia',
        },
        account: {
            checking: 'Cuenta corriente',
            savings: 'Ahorro',
            credit: 'Tarjeta de crédito',
            credit_card: 'Tarjeta de crédito',
            investment: 'Inversión',
            cash: 'Efectivo',
            multi_currency: 'Multi-moneda',
            other: 'Otro',
            crypto: 'Criptomonedas',
        },
        recurrence: {
            daily: 'Diario',
            weekly: 'Semanal',
            monthly: 'Mensual',
            yearly: 'Anual',
        },
        'subscription.status': {
            active: 'Activa',
            cancelled: 'Cancelada',
        },
        'goal.status': {
            in_progress: 'En curso',
            completed: 'Completada',
        },
    },
    en: {
        transaction: {
            income: 'Income',
            expense: 'Expense',
            transfer: 'Transfer',
        },
        account: {
            checking: 'Checking account',
            savings: 'Savings',
            credit: 'Credit card',
            credit_card: 'Credit card',
            investment: 'Investment',
            cash: 'Cash',
            multi_currency: 'Multi-currency',
            other: 'Other',
            crypto: 'Cryptocurrencies',
        },
        recurrence: {
            daily: 'Daily',
            weekly: 'Weekly',
            monthly: 'Monthly',
            yearly: 'Yearly',
        },
        'subscription.status': {
            active: 'Active',
            cancelled: 'Cancelled',
        },
        'goal.status': {
            in_progress: 'In progress',
            completed: 'Completed',
        },
    },
};

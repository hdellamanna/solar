import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Reactive access to the locale and available-locales Inertia
 * shared props.
 *
 * The values are read from `usePage().props.app` (populated by
 * `App\Http\Middleware\HandleInertiaRequests::share()`), so the
 * composable is fully reactive — switching locale via
 * `router.reload({ only: ['app'] })` will update the computed
 * values automatically.
 *
 * Both `locale` and `available` are `computed` refs, NOT plain
 * values, so consumers can use them in templates and watchers
 * without re-fetching the page.
 *
 * @returns {{ locale: ComputedRef<string>, available: ComputedRef<Array<{code: string, name: string, english_name: string}>> }}
 */
export function useLocale() {
    const page = usePage();

    const locale = computed(() => {
        const fromProps = page.props.app?.locale;
        if (typeof fromProps === 'string' && fromProps.length > 0) {
            return fromProps;
        }
        return 'pt-BR';
    });

    const available = computed(() => {
        const fromProps = page.props.app?.available_locales;
        return Array.isArray(fromProps) ? fromProps : [];
    });

    return { locale, available };
}

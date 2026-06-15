import { computed, unref, isRef } from 'vue';
import { useLocale } from './useLocale';

/**
 * Compute the localized name for an entity that carries
 * `name_pt`, `name_es`, `name_en` columns (Categories, Tags,
 * and any other row in the FASE 7 schema).
 *
 * Returns a `ComputedRef<string>` so consumers can drop the
 * result into templates directly.
 *
 * Fallback chain (per the design doc):
 *   requested locale  →  pt-BR  →  es  →  en  →  `#<id>`
 *
 * The `entity` argument can be a plain object, a `ref`/`reactive`
 * proxy, or a function returning one of those. Refs and functions
 * are unwrapped transparently.
 *
 * @param {Object|Ref|Function|null|undefined} entity
 * @returns {ComputedRef<string>}
 */
export function useLocalizedName(entity) {
    const { locale } = useLocale();

    const resolveEntity = () => {
        let value = entity;
        if (typeof value === 'function') {
            value = value();
        }
        if (isRef(value)) {
            value = value.value;
        }
        return value;
    };

    return computed(() => {
        const e = resolveEntity();
        if (!e || typeof e !== 'object') return '';

        const short = (locale.value || 'pt-BR').split('-')[0]; // pt-BR → pt
        const key = `name_${short}`; // name_pt / name_es / name_en

        // 1) Active locale
        if (e[key]) return e[key];
        // 2) pt-BR (the seed language)
        if (e.name_pt) return e.name_pt;
        // 3) Spanish
        if (e.name_es) return e.name_es;
        // 4) English
        if (e.name_en) return e.name_en;
        // 5) Legacy `name` column (Account, Goal, Subscription, Budget, etc.)
        if (e.name) return e.name;
        // 6) Final id fallback
        if (e.id !== undefined && e.id !== null) return `#${e.id}`;

        return '';
    });
}

/**
 * Plain helper variant for the cases where a component is
 * already inside a `setup()` and wants to pass the resolved
 * string to a child as a prop. Mirrors `useLocalizedName`'s
 * fallback chain.
 *
 * @param {Object|null|undefined} entity
 * @param {string} localeCode - Active locale code (e.g. 'pt-BR', 'es', 'en')
 * @returns {string}
 */
export function localizedNameOf(entity, localeCode = 'pt-BR') {
    if (!entity || typeof entity !== 'object') return '';
    const short = (localeCode || 'pt-BR').split('-')[0];
    const key = `name_${short}`;
    return (
        entity[key] ||
        entity.name_pt ||
        entity.name_es ||
        entity.name_en ||
        entity.name ||
        (entity.id !== undefined && entity.id !== null ? `#${entity.id}` : '')
    );
}

// Suppress lint warning on the `unref` import — kept available
// for callers that want to compose with the resolved value.
void unref;

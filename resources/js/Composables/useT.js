import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useLocale } from './useLocale';

/**
 * Lightweight i18n composable — `t(key, params)`.
 *
 * Strategy:
 *   1) Prefer `page.props.translations` if the backend publishes
 *      a curated `translations` map (FASE 7 follow-up — not yet
 *      shipped; see `docs/fase-7-i18n/design.md` §"Frontend").
 *   2) Fall back to `window.__SOLAR_I18N__` (set by the initial
 *      Inertia payload when the backend hoists translations to
 *      the global — same source as #1 in practice).
 *   3) Fall back to a built-in dictionary for the keys the UI
 *      renders today. Mirrors `lang/{locale}/app.php` and
 *      keeps the app working before the backend adds the prop.
 *   4) If nothing matches, return the key itself so the
 *      developer sees a clear `app.save` placeholder in the UI
 *      instead of an empty string.
 *
 * The composable is reactive to the active locale: switching
 * the locale via the Inertia shared prop (or via the new
 * `Settings/Idioma` page) immediately changes the result.
 *
 * Replaces the previous design's plan to add `vue-i18n`. The
 * no-dep path is small, fully reactive, and easier to remove
 * if/when the project standardizes on `vue-i18n` later.
 *
 * @returns {{ t: (key: string, params?: Record<string, string|number>) => string, locale: ComputedRef<string> }}
 */
export function useT() {
    const page = usePage();
    const { locale } = useLocale();

    const translations = computed(() => {
        // 1) Inertia shared prop
        const fromProps = page.props.translations;
        if (fromProps && typeof fromProps === 'object') {
            return fromProps;
        }
        // 2) Global hoist (initial paint)
        if (typeof window !== 'undefined' && window.__SOLAR_I18N__) {
            return window.__SOLAR_I18N__;
        }
        // 3) Built-in dictionary keyed by locale → flat key → string
        return BUILTIN[locale.value] || BUILTIN['pt-BR'];
    });

    /**
     * Look up a translation. Supports:
     *   t('app.save')                 → "Salvar"
     *   t('app.minutes_ago', { count: 3 }) → "3 min atrás" (replaces :count)
     *   t('app.does_not_exist')       → "app.does_not_exist" (safe default)
     */
    const t = (key, params) => {
        if (!key) return '';
        const value = resolve(translations.value, key);
        if (value === null || value === undefined) {
            if (typeof console !== 'undefined' && !t._warned?.has(key)) {
                if (!t._warned) t._warned = new Set();
                t._warned.add(key);
                // eslint-disable-next-line no-console
                console.warn(`[useT] missing translation for "${key}" in locale "${locale.value}"`);
            }
            return key;
        }
        if (typeof value === 'string' && params) {
            return interpolate(value, params);
        }
        return String(value);
    };

    return { t, locale };
}

/**
 * Resolve a dot-notation key against a nested object.
 * Returns `null` if any segment is missing.
 */
function resolve(obj, key) {
    if (!obj) return null;
    const parts = key.split('.');
    let cursor = obj;
    for (const part of parts) {
        if (cursor && typeof cursor === 'object' && part in cursor) {
            cursor = cursor[part];
        } else {
            return null;
        }
    }
    return cursor;
}

/**
 * Replace `:name` placeholders with values from `params`.
 */
function interpolate(template, params) {
    return template.replace(/:(\w+)/g, (match, name) => {
        if (Object.prototype.hasOwnProperty.call(params, name)) {
            return String(params[name]);
        }
        return match;
    });
}

/**
 * Built-in fallback dictionary — mirrors the
 * `lang/{locale}/app.php` keys the front-end needs today
 * (chrome, nav, common actions, time-ago fragments).
 *
 * Keep in sync with `lang/{locale}/app.php` until the
 * backend publishes `props.translations` and we delete this.
 */
const BUILTIN = {
    'pt-BR': {
        app: {
            brand: 'Solar Money',
            tagline: 'Finanças pessoais simples',
            save: 'Salvar',
            saving: 'Salvando…',
            cancel: 'Cancelar',
            edit: 'Editar',
            delete: 'Excluir',
            back: 'Voltar',
            next: 'Próximo',
            previous: 'Anterior',
            loading: 'Carregando…',
            search: 'Buscar',
            login: 'Entrar',
            logout: 'Sair',
            register: 'Criar conta',
            dashboard: 'Painel',
            accounts: 'Contas',
            transactions: 'Transações',
            subscriptions: 'Assinaturas',
            goals: 'Metas',
            investments: 'Investimentos',
            debts: 'Dívidas',
            pix: 'PIX',
            tags: 'Tags',
            budgets: 'Orçamentos',
            reports: 'Relatórios',
            recurrences: 'Recorrências',
            settings: 'Configurações',
            profile: 'Perfil',
            security: 'Segurança',
            appearance: 'Aparência',
            language: 'Idioma',
            tutorial: 'Tutorial',
            about: 'Sobre',
            save_success: 'Preferências salvas.',
            language_save_success: 'Idioma atualizado.',
            profile_save_success: 'Perfil atualizado.',
            yes: 'Sim',
            no: 'Não',
            none: 'Nenhum',
            optional: 'Opcional',
            required: 'Obrigatório',
            error_generic: 'Algo deu errado. Tente novamente.',
            not_found: 'Não encontrado.',
            unauthorized: 'Você não tem permissão para fazer isso.',
            just_now: 'agora mesmo',
            minutes_ago: ':count min atrás',
            hours_ago: ':count h atrás',
            days_ago: ':count d atrás',
        },
    },
    es: {
        app: {
            brand: 'Solar Money',
            tagline: 'Finanzas personales simples',
            save: 'Guardar',
            saving: 'Guardando…',
            cancel: 'Cancelar',
            edit: 'Editar',
            delete: 'Eliminar',
            back: 'Atrás',
            next: 'Siguiente',
            previous: 'Anterior',
            loading: 'Cargando…',
            search: 'Buscar',
            login: 'Entrar',
            logout: 'Salir',
            register: 'Crear cuenta',
            dashboard: 'Panel',
            accounts: 'Cuentas',
            transactions: 'Transacciones',
            subscriptions: 'Suscripciones',
            goals: 'Metas',
            investments: 'Inversiones',
            debts: 'Deudas',
            pix: 'PIX',
            tags: 'Etiquetas',
            budgets: 'Presupuestos',
            reports: 'Informes',
            recurrences: 'Recurrencias',
            settings: 'Ajustes',
            profile: 'Perfil',
            security: 'Seguridad',
            appearance: 'Apariencia',
            language: 'Idioma',
            tutorial: 'Tutorial',
            about: 'Acerca de',
            save_success: 'Preferencias guardadas.',
            language_save_success: 'Idioma actualizado.',
            profile_save_success: 'Perfil actualizado.',
            yes: 'Sí',
            no: 'No',
            none: 'Ninguno',
            optional: 'Opcional',
            required: 'Obligatorio',
            error_generic: 'Algo salió mal. Inténtalo de nuevo.',
            not_found: 'No encontrado.',
            unauthorized: 'No tienes permiso para hacer esto.',
            just_now: 'ahora mismo',
            minutes_ago: 'hace :count min',
            hours_ago: 'hace :count h',
            days_ago: 'hace :count d',
        },
    },
    en: {
        app: {
            brand: 'Solar Money',
            tagline: 'Personal finance, simple',
            save: 'Save',
            saving: 'Saving…',
            cancel: 'Cancel',
            edit: 'Edit',
            delete: 'Delete',
            back: 'Back',
            next: 'Next',
            previous: 'Previous',
            loading: 'Loading…',
            search: 'Search',
            login: 'Sign in',
            logout: 'Sign out',
            register: 'Create account',
            dashboard: 'Dashboard',
            accounts: 'Accounts',
            transactions: 'Transactions',
            subscriptions: 'Subscriptions',
            goals: 'Goals',
            investments: 'Investments',
            debts: 'Debts',
            pix: 'PIX',
            tags: 'Tags',
            budgets: 'Budgets',
            reports: 'Reports',
            recurrences: 'Recurrences',
            settings: 'Settings',
            profile: 'Profile',
            security: 'Security',
            appearance: 'Appearance',
            language: 'Language',
            tutorial: 'Tutorial',
            about: 'About',
            save_success: 'Preferences saved.',
            language_save_success: 'Language updated.',
            profile_save_success: 'Profile updated.',
            yes: 'Yes',
            no: 'No',
            none: 'None',
            optional: 'Optional',
            required: 'Required',
            error_generic: 'Something went wrong. Please try again.',
            not_found: 'Not found.',
            unauthorized: 'You do not have permission to do that.',
            just_now: 'just now',
            minutes_ago: ':count min ago',
            hours_ago: ':count h ago',
            days_ago: ':count d ago',
        },
    },
};

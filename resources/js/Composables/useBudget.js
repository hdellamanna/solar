/**
 * Pure helpers for budget status / progress logic shared between
 * the budgets page and the dashboard mini-cards.
 */

/**
 * Status buckets produced by the backend.
 */
export const BUDGET_STATUS = {
    safe: { label: 'Saudável', tone: 'safe' },
    warning: { label: 'Atenção', tone: 'warning' },
    exceeded: { label: 'Estourado', tone: 'exceeded' },
};

/**
 * Returns 'safe' | 'warning' | 'exceeded' given current spent and amount.
 * @param {number} spentCents
 * @param {number} amountCents
 * @param {number} threshold
 */
export function computeStatus(spentCents, amountCents, threshold = 80) {
    if (!amountCents || amountCents <= 0) return 'safe';
    const pct = (spentCents / amountCents) * 100;
    if (pct >= 100) return 'exceeded';
    if (pct >= threshold) return 'warning';
    return 'safe';
}

/**
 * Clamp progress to 0-100 as a number.
 */
export function computeProgress(spentCents, amountCents) {
    if (!amountCents || amountCents <= 0) return 0;
    const pct = (spentCents / amountCents) * 100;
    return Math.max(0, Math.min(100, pct));
}

/**
 * Tailwind classes for the status badge.
 */
export function statusBadgeClass(status) {
    switch (status) {
        case 'exceeded':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300';
        case 'warning':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        default:
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
    }
}

/**
 * Map a hex color to a Tailwind background class. Falls back to brand.
 * Used as a curated palette that doesn't require Tailwind safelisting.
 */
const COLOR_MAP = {
    '#10b981': 'bg-emerald-500',
    '#22c55e': 'bg-green-500',
    '#84cc16': 'bg-lime-500',
    '#f59e0b': 'bg-amber-500',
    '#eab308': 'bg-yellow-500',
    '#ef4444': 'bg-red-500',
    '#dc2626': 'bg-red-600',
    '#3b82f6': 'bg-blue-500',
    '#6366f1': 'bg-indigo-500',
    '#8b5cf6': 'bg-violet-500',
    '#ec4899': 'bg-pink-500',
    '#06b6d4': 'bg-cyan-500',
    '#f97316': 'bg-orange-500',
    '#a855f7': 'bg-purple-500',
};

/**
 * Returns a Tailwind class that paints a bar with the given hex color.
 * Falls back to brand amber if unknown.
 */
export function colorBarClass(hex) {
    if (!hex) return 'bg-brand-500';
    return COLOR_MAP[hex.toLowerCase()] || 'bg-brand-500';
}

/**
 * Status label in pt-BR.
 */
export function statusLabel(status) {
    return BUDGET_STATUS[status]?.label ?? 'Saudável';
}

import { ref, computed } from 'vue';

/**
 * Composable helpers for working with user tags.
 *
 * Exposes reactive helpers for tag option lists, color resolution and icon
 * resolution that can be shared across forms, lists and autocompletes.
 */
export function useTag() {
    /**
     * Build an option list compatible with native <select> or <datalist>.
     *
     * @param {Array} tags
     * @returns {{value: number|undefined, label: string, color: string|null, icon: string|null}[]}
     */
    const tagOptions = (tags = []) => {
        return tags.map((t) => ({
            value: t.id,
            label: `${t.icon ? t.icon + ' ' : ''}${t.name}`,
            color: t.color || '#6b7280',
            icon: t.icon || null,
        }));
    };

    /**
     * Resolve a tag color with a sane default.
     *
     * @param {string|null|undefined} color
     * @returns {string}
     */
    const tagColor = (color) => {
        if (!color) return '#6b7280';
        return color;
    };

    /**
     * Resolve a tag icon (emoji) with a sane default.
     *
     * @param {string|null|undefined} icon
     * @returns {string}
     */
    const tagIcon = (icon) => {
        return icon || '🏷️';
    };

    /**
     * Produce a contrasting (black/white) text color for a given hex bg.
     *
     * @param {string} hex
     * @returns {string}
     */
    const readableTextOn = (hex) => {
        if (!hex) return '#fff';
        const h = hex.replace('#', '');
        const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
        const r = parseInt(full.substr(0, 2), 16);
        const g = parseInt(full.substr(2, 2), 16);
        const b = parseInt(full.substr(4, 2), 16);
        const yiq = (r * 299 + g * 587 + b * 114) / 1000;
        return yiq >= 160 ? '#0f172a' : '#ffffff';
    };

    return { tagOptions, tagColor, tagIcon, readableTextOn };
}

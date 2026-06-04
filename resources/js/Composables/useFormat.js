const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const dateFmt = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
const dateMonthFmt = new Intl.DateTimeFormat('pt-BR', { month: 'short', year: '2-digit' });

export function formatCents(cents) {
    if (cents === null || cents === undefined || isNaN(cents)) return brl.format(0);
    return brl.format(cents / 100);
}

export function formatDate(iso) {
    if (!iso) return '';
    return dateFmt.format(new Date(iso));
}

export function formatMonth(iso) {
    if (!iso) return '';
    return dateMonthFmt.format(new Date(iso));
}

export function initials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

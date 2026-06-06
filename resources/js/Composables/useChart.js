/**
 * useChart — ApexCharts configuration helpers for Solar.
 *
 * Each helper returns a plain object suitable to be passed to <apexchart>:
 *
 *   <apexchart type="line" :options="useLineConfig()" :series="..." />
 *
 * Dark mode is read at call time AND observed via MutationObserver: every
 * helper accepts a `isDark` ref and the module wires a watcher that mutates
 * the returned config in place when the user toggles the theme, so the
 * rendered SVG updates without needing to rebuild the entire chart.
 *
 * Locale: pt-BR via Intl.NumberFormat for tooltip / label values.
 */

import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

// --- Theme constants -------------------------------------------------------

export const THEME = {
    brand:   '#f59e0b',
    income:  '#10b981',
    expense: '#ef4444',
    info:    '#3b82f6',
    warn:    '#f97316',
    palette: [
        '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6',
        '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16',
        '#06b6d4', '#a855f7',
    ],
};

// pt-BR formatters reused everywhere.
const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const brlPrecise = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });
const dateLabel = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', 'month': '2-digit' });
const monthLabel = new Intl.DateTimeFormat('pt-BR', { month: 'short', year: '2-digit' });
const dayLabel = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit' });

export function formatBRL(cents) {
    if (cents === null || cents === undefined || isNaN(cents)) return brl.format(0);
    return brl.format(cents / 100);
}

export function formatBRLPrecise(cents) {
    if (cents === null || cents === undefined || isNaN(cents)) return brlPrecise.format(0);
    return brlPrecise.format(cents / 100);
}

// --- Dark mode reactive wiring --------------------------------------------

/**
 * Returns a Vue ref that mirrors `document.documentElement.classList.contains('dark')`
 * and stays in sync via a MutationObserver. The observer is detached on
 * component unmount.
 */
export function useDarkMode() {
    const isDark = ref(false);
    let observer = null;

    const read = () => {
        if (typeof document === 'undefined') return false;
        return document.documentElement.classList.contains('dark');
    };

    onMounted(() => {
        isDark.value = read();
        observer = new MutationObserver(() => {
            isDark.value = read();
        });
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
    });

    onBeforeUnmount(() => {
        if (observer) observer.disconnect();
    });

    return isDark;
}

// Shared theme tokens derived from `isDark`.
function tokens(isDark) {
    if (isDark) {
        return {
            foreground: '#e2e8f0', // slate-200
            muted: '#94a3b8',      // slate-400
            border: '#334155',     // slate-700
            grid: '#1e293b',       // slate-800
            tooltipBg: '#0f172a',  // slate-900
        };
    }
    return {
        foreground: '#1e293b', // slate-800
        muted: '#64748b',      // slate-500
        border: '#e2e8f0',     // slate-200
        grid: '#f1f5f9',       // slate-100
        tooltipBg: '#ffffff',
    };
}

// --- Tooltip / label helpers ----------------------------------------------

function baseTooltip({ isDark, valuePrefix = 'R$ ', valueIsCents = true }) {
    return {
        theme: isDark.value ? 'dark' : 'light',
        style: { fontSize: '12px', fontFamily: 'Inter, sans-serif' },
        y: {
            formatter: (val) => {
                if (val === null || val === undefined) return '—';
                const cents = valueIsCents ? Number(val) : Math.round(Number(val) * 100);
                return valuePrefix + formatBRL(cents);
            },
        },
    };
}

function baseXaxis({ categories, isDark, type = 'category' }) {
    return {
        categories,
        type,
        labels: {
            style: { colors: tokens(isDark).muted, fontSize: '11px', fontFamily: 'Inter, sans-serif' },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    };
}

function baseGrid(isDark) {
    return {
        borderColor: tokens(isDark).grid,
        strokeDashArray: 3,
        padding: { top: 0, right: 10, bottom: 0, left: 10 },
        yaxis: { lines: { show: true } },
        xaxis: { lines: { show: false } },
    };
}

function baseChart({ isDark, height = 320, sparkline = false, stacked = false }) {
    return {
        chart: {
            fontFamily: 'Inter, sans-serif',
            background: 'transparent',
            foreColor: tokens(isDark).muted,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { speed: 400 },
            stacked,
            sparkline: { enabled: sparkline },
            height,
        },
        theme: { mode: isDark.value ? 'dark' : 'light' },
        grid: baseGrid(isDark),
        dataLabels: { enabled: false },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            labels: { colors: tokens(isDark).muted },
            markers: { width: 10, height: 10, radius: 12 },
            fontSize: '12px',
            fontFamily: 'Inter, sans-serif',
            itemMargin: { horizontal: 8, vertical: 0 },
        },
        noData: {
            text: 'Sem dados no período',
            style: { color: tokens(isDark).muted, fontSize: '14px', fontFamily: 'Inter, sans-serif' },
        },
    };
}

// --- Public helpers --------------------------------------------------------

/**
 * Pie chart config (no center hole). Use for share-of-something.
 * Series must be numbers (raw cents), labels array same length.
 */
export function usePieConfig({ labels, isDark, height = 320 } = {}) {
    const dark = isDark ?? useDarkMode();
    const cfg = {
        ...baseChart({ isDark: dark, height }),
        labels,
        colors: THEME.palette,
        stroke: { width: 0 },
        tooltip: baseTooltip({ isDark: dark }),
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            labels: { colors: tokens(dark).muted },
            markers: { width: 10, height: 10, radius: 12 },
            fontSize: '12px',
            fontFamily: 'Inter, sans-serif',
            formatter: (seriesName, opts) => {
                const value = opts.w.globals.series[opts.seriesIndex];
                const total = opts.w.globals.series.reduce((a, b) => a + b, 0);
                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                return `${seriesName} — ${formatBRLPrecise(value)} (${pct}%)`;
            },
        },
        plotOptions: {
            pie: {
                expandOnClick: false,
                dataLabels: { offset: -4 },
            },
        },
    };

    // Live update palette on dark mode toggle.
    watch(dark, () => {
        cfg.theme.mode = dark.value ? 'dark' : 'light';
        cfg.grid = baseGrid(dark);
        cfg.legend.labels = { colors: tokens(dark).muted };
        cfg.tooltip.theme = dark.value ? 'dark' : 'light';
    });

    return cfg;
}

/**
 * Donut chart config (center hole + total in middle).
 * `totalLabel` is rendered in the donut hole.
 */
export function useDonutConfig({ labels, isDark, totalLabel = 'Total', height = 320 } = {}) {
    const dark = isDark ?? useDarkMode();
    const cfg = {
        ...baseChart({ isDark: dark, height }),
        labels,
        colors: THEME.palette,
        stroke: { width: 2, colors: [dark.value ? '#0f172a' : '#ffffff'] },
        fill: { type: 'solid' },
        tooltip: baseTooltip({ isDark: dark }),
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    background: 'transparent',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            color: tokens(dark).muted,
                            fontSize: '12px',
                            fontFamily: 'Inter, sans-serif',
                        },
                        value: {
                            show: true,
                            color: tokens(dark).foreground,
                            fontSize: '18px',
                            fontWeight: 600,
                            fontFamily: 'Inter, sans-serif',
                            formatter: (val) => formatBRL(Number(val)),
                        },
                        total: {
                            show: true,
                            label: totalLabel,
                            color: tokens(dark).muted,
                            fontSize: '12px',
                            fontFamily: 'Inter, sans-serif',
                            formatter: (w) => {
                                const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                return formatBRL(total);
                            },
                        },
                    },
                },
            },
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            labels: { colors: tokens(dark).muted },
            markers: { width: 10, height: 10, radius: 12 },
            fontSize: '12px',
            fontFamily: 'Inter, sans-serif',
            formatter: (seriesName, opts) => {
                const value = opts.w.globals.series[opts.seriesIndex];
                const total = opts.w.globals.series.reduce((a, b) => a + b, 0);
                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                return `${seriesName} — ${formatBRLPrecise(value)} (${pct}%)`;
            },
        },
    };

    watch(dark, () => {
        cfg.theme.mode = dark.value ? 'dark' : 'light';
        cfg.grid = baseGrid(dark);
        cfg.legend.labels = { colors: tokens(dark).muted };
        cfg.tooltip.theme = dark.value ? 'dark' : 'light';
        cfg.plotOptions.pie.donut.labels.name.color = tokens(dark).muted;
        cfg.plotOptions.pie.donut.labels.value.color = tokens(dark).foreground;
        cfg.plotOptions.pie.donut.labels.total.color = tokens(dark).muted;
        cfg.stroke.colors = [dark.value ? '#0f172a' : '#ffffff'];
    });

    return cfg;
}

/**
 * Multi-series line chart (e.g. monthly income / expense / net).
 * `series` shape: [{ name, data: number[] }], data values are cents.
 * `categories` shape: ['YYYY-MM', ...] — rendered as Mmm/yy in pt-BR.
 */
export function useLineConfig({ categories, series, isDark, height = 320, stacked = false, colors } = {}) {
    const dark = isDark ?? useDarkMode();
    const seriesColors = colors || [THEME.income, THEME.expense, THEME.info];
    const cfg = {
        ...baseChart({ isDark: dark, height, stacked }),
        series,
        colors: seriesColors,
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: {
            ...baseXaxis({ categories, isDark: dark }),
            labels: {
                ...baseXaxis({ categories, isDark: dark }).labels,
                formatter: (val) => {
                    // val comes as 'YYYY-MM' string
                    if (!val) return '';
                    const [y, m] = val.split('-');
                    const d = new Date(Number(y), Number(m) - 1, 1);
                    return monthLabel.format(d);
                },
            },
        },
        yaxis: {
            labels: {
                style: { colors: tokens(dark).muted, fontSize: '11px', fontFamily: 'Inter, sans-serif' },
                formatter: (val) => {
                    const abs = Math.abs(Number(val));
                    if (abs >= 1_000_000) return `R$ ${(val / 1000000).toFixed(1)}M`;
                    if (abs >= 1_000) return `R$ ${(val / 1000).toFixed(1)}k`;
                    return `R$ ${val}`;
                },
            },
        },
        tooltip: {
            ...baseTooltip({ isDark: dark }),
            x: {
                formatter: (val) => {
                    if (!val) return '';
                    const [y, m] = String(val).split('-');
                    const d = new Date(Number(y), Number(m) - 1, 1);
                    return monthLabel.format(d);
                },
            },
            shared: true,
            intersect: false,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 0.4,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [0, 95, 100],
            },
        },
    };

    watch(dark, () => {
        cfg.theme.mode = dark.value ? 'dark' : 'light';
        cfg.grid = baseGrid(dark);
        cfg.xaxis.labels.style = { colors: tokens(dark).muted, fontSize: '11px' };
        cfg.yaxis.labels.style = { colors: tokens(dark).muted, fontSize: '11px' };
        cfg.tooltip.theme = dark.value ? 'dark' : 'light';
    });

    return cfg;
}

/**
 * Bar chart (vertical). Data values are cents.
 */
export function useBarConfig({ categories, series, isDark, height = 320, horizontal = false, colors } = {}) {
    const dark = isDark ?? useDarkMode();
    const seriesColors = colors || [THEME.expense];
    const cfg = {
        ...baseChart({ isDark: dark, height }),
        series,
        colors: seriesColors,
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '55%',
                horizontal,
                distributed: series.length === 1,
                dataLabels: { position: horizontal ? 'top' : 'top' },
            },
        },
        stroke: { show: true, width: 0, colors: ['transparent'] },
        xaxis: {
            ...baseXaxis({ categories, isDark: dark }),
            labels: {
                ...baseXaxis({ categories, isDark: dark }).labels,
                formatter: horizontal
                    ? (val) => formatBRL(Number(val))
                    : (val) => {
                        // 'YYYY-MM-DD' → 'dd/mm'
                        if (!val) return '';
                        if (typeof val === 'string' && val.length === 10) {
                            return `${val.slice(8, 10)}/${val.slice(5, 7)}`;
                        }
                        return String(val);
                    },
            },
        },
        yaxis: horizontal
            ? {
                labels: {
                    style: { colors: tokens(dark).muted, fontSize: '11px' },
                    formatter: (val) => formatBRL(Number(val)),
                },
              }
            : {
                labels: {
                    style: { colors: tokens(dark).muted, fontSize: '11px' },
                    formatter: (val) => {
                        const abs = Math.abs(Number(val));
                        if (abs >= 1_000_000) return `R$ ${(val / 1000000).toFixed(1)}M`;
                        if (abs >= 1_000) return `R$ ${(val / 1000).toFixed(1)}k`;
                        return `R$ ${val}`;
                    },
                },
              },
        tooltip: baseTooltip({ isDark: dark }),
        legend: { show: series.length > 1 },
    };

    watch(dark, () => {
        cfg.theme.mode = dark.value ? 'dark' : 'light';
        cfg.grid = baseGrid(dark);
        cfg.xaxis.labels.style = { colors: tokens(dark).muted, fontSize: '11px' };
        cfg.yaxis.labels.style = { colors: tokens(dark).muted, fontSize: '11px' };
        cfg.tooltip.theme = dark.value ? 'dark' : 'light';
    });

    return cfg;
}

/**
 * Area chart (filled line). Same options as line chart but always with
 * gradient fill — good for cumulative visuals like "saldo acumulado".
 */
export function useAreaConfig({ categories, series, isDark, height = 320, colors } = {}) {
    const cfg = useLineConfig({ categories, series, isDark, height, colors });
    cfg.stroke = { curve: 'smooth', width: 2.5 };
    cfg.fill = {
        type: 'gradient',
        gradient: {
            shadeIntensity: 0.5,
            opacityFrom: 0.6,
            opacityTo: 0.1,
            stops: [0, 90, 100],
        },
    };
    return cfg;
}

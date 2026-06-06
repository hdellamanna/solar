<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates financial data for the Reports page.
 *
 * Every public method issues grouped/summed SQL queries and never loads the
 * full transaction table into memory, so it scales to large histories.
 */
class ReportService
{
    /**
     * Categorical color palette (12 harmonic hues) used for pie/donut/bar charts
     * where categories are not color-bound by the user's data.
     *
     * @var string[]
     */
    public const CATEGORY_PALETTE = [
        '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6',
        '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16',
        '#06b6d4', '#a855f7',
    ];

    /**
     * Fixed color per account type (used in accountDistribution donut).
     */
    private const ACCOUNT_TYPE_COLORS = [
        'checking'     => '#3b82f6',
        'savings'      => '#10b981',
        'credit_card'  => '#ef4444',
        'cash'         => '#f59e0b',
        'investment'   => '#8b5cf6',
        'crypto'       => '#f97316',
    ];

    /**
     * Build the last $months months of income/expense/net.
     * Returns one entry per month even when there's no activity, so the line
     * chart always has continuous x-axis labels.
     *
     * @return array<int, array{month: string, income: int, expense: int, net: int}>
     */
    public function monthlyFlow(User $user, int $months = 12): array
    {
        $months = max(1, $months);
        $start = CarbonImmutable::today()->subMonths($months - 1)->startOfMonth();

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereIn('type', ['income', 'expense'])
            ->where('date', '>=', $start->toDateString())
            ->selectRaw("$monthExpr as bucket")
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount_cents ELSE 0 END) as income_cents")
            ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount_cents ELSE 0 END) as expense_cents")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $out = [];
        $cursor = $start;
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);
            $income = (int) ($row->income_cents ?? 0);
            $expense = (int) ($row->expense_cents ?? 0);
            $out[] = [
                'month' => $key,
                'income' => $income,
                'expense' => $expense,
                'net' => $income + $expense, // expense already negative
            ];
            $cursor = $cursor->addMonth();
        }

        return $out;
    }

    /**
     * Top expense categories in a period, ordered by absolute value desc.
     * Each entry is enriched with a stable color and a percentage of the total.
     *
     * @return array<int, array{name: string, value_cents: int, percent: float, color: string}>
     */
    public function categoryBreakdown(User $user, string $from, string $to, int $limit = 10): array
    {
        $driver = DB::connection()->getDriverName();
        $absExpr = $driver === 'sqlite'
            ? 'ABS(SUM(transactions.amount_cents))'
            : 'SUM(ABS(transactions.amount_cents))';

        $rows = DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.status', 'paid')
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.date', [$from, $to])
            ->groupBy('transactions.category_id', 'categories.name', 'categories.color')
            ->selectRaw('categories.name as name')
            ->selectRaw('categories.color as color')
            ->selectRaw('SUM(transactions.amount_cents) as total_cents')
            ->selectRaw("$absExpr as abs_total_cents")
            ->orderByDesc('abs_total_cents')
            ->limit($limit)
            ->get();

        $grandTotal = (int) $rows->sum(fn ($r) => abs((int) $r->total_cents));
        if ($grandTotal === 0) {
            return [];
        }

        $palette = self::CATEGORY_PALETTE;
        $fallback = 0;
        return $rows->map(function ($r) use ($grandTotal, $palette, &$fallback) {
            $value = (int) $r->total_cents; // already negative
            $abs = abs($value);
            $color = $r->color ?: $palette[$fallback++ % count($palette)];
            return [
                'name' => $r->name ?? 'Sem categoria',
                'value_cents' => $value,
                'percent' => round(($abs / $grandTotal) * 100, 1),
                'color' => $color,
            ];
        })->all();
    }

    /**
     * Current balance per non-archived account, with a color derived from
     * the account type. Used to build the "Saldo por conta" donut.
     *
     * @return array<int, array{account_name: string, balance_cents: int, type: string, color: string}>
     */
    public function accountDistribution(User $user): array
    {
        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->where('archived', false)
            ->orderBy('name')
            ->get();

        return $accounts->map(function (Account $a) {
            return [
                'account_name' => $a->name,
                'balance_cents' => (int) $a->balance_cents,
                'type' => $a->type,
                'color' => $a->color ?: (self::ACCOUNT_TYPE_COLORS[$a->type] ?? '#f59e0b'),
            ];
        })->all();
    }

    /**
     * Daily expense totals (absolute cents) between $from and $to inclusive.
     * Fills missing days with 0 so the bar chart is continuous.
     *
     * @return array<int, array{date: string, value_cents: int}>
     */
    public function dailySpending(User $user, string $from, string $to): array
    {
        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->where('type', 'expense')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('date as d')
            ->selectRaw('SUM(amount_cents) as total_cents')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('total_cents', 'd');

        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $out = [];
        $cursor = $start;
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $out[] = [
                'date' => $key,
                'value_cents' => abs((int) ($rows[$key] ?? 0)),
            ];
            $cursor = $cursor->addDay();
        }
        return $out;
    }

    /**
     * Top merchants in a period, grouped by lowercased trimmed description.
     * Accent-folding is applied in PHP because SQLite's LOWER() is not Unicode
     * aware (e.g. "almoço" != "almoco"). Skips empty descriptions.
     *
     * @return array<int, array{description: string, total_cents: int, count: int}>
     */
    public function topMerchants(User $user, string $from, string $to, int $limit = 10): array
    {
        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->where('type', 'expense')
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('description')
            ->where('description', '<>', '')
            ->selectRaw('LOWER(TRIM(description)) as norm_key')
            ->selectRaw('TRIM(description) as display')
            ->selectRaw('amount_cents')
            ->get();

        $groups = [];
        foreach ($rows as $r) {
            $key = $this->normalizeKey($r->norm_key);
            if (!isset($groups[$key])) {
                $groups[$key] = ['description' => $r->display, 'total_cents' => 0, 'count' => 0];
            }
            $groups[$key]['total_cents'] += (int) $r->amount_cents;
            $groups[$key]['count'] += 1;
        }

        // Sort by absolute total descending
        usort($groups, fn ($a, $b) => abs($b['total_cents']) <=> abs($a['total_cents']));

        return array_slice(array_values($groups), 0, $limit);
    }

    /**
     * Normalize a merchant key: lowercase, trim, strip accents and punctuation.
     * This keeps "IFOOD - Almoço" and "ifood - almoco" in the same bucket.
     */
    /**
     * Group transactions by the FIRST WORD of the description (case + accent-insensitive).
     * This keeps "IFOOD - Almoço", "ifood - JANTAR" and "Ifood - Café" all in the "ifood" bucket.
     * Uses the first word only (a reasonable merchant heuristic) and keeps a display string
     * representative of the group.
     */
    private function normalizeKey(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        // Strip diacritics via iconv (ASCII transliteration; works without php-intl)
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $ascii !== false ? $ascii : $s;
        // Collapse non-alphanumerics to spaces
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        // Take only the first word as the merchant bucket
        $parts = explode(' ', $s);
        return $parts[0] ?? $s;
    }

    /**
     * Convenience aggregate used by the report header. Operates with a single
     * grouped SQL query against the filtered window.
     *
     * @return array{income: int, expense: int, net: int, count: int}
     */
    public function kpis(User $user, string $from, string $to): array
    {
        $row = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereBetween('date', [$from, $to])
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount_cents ELSE 0 END) as income_cents")
            ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount_cents ELSE 0 END) as expense_cents")
            ->selectRaw('COUNT(*) as cnt')
            ->first();

        $income = (int) ($row->income_cents ?? 0);
        $expense = (int) ($row->expense_cents ?? 0);
        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income + $expense,
            'count' => (int) ($row->cnt ?? 0),
        ];
    }
}

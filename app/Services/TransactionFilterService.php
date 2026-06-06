<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Builds a Transaction query from a validated set of filters.
 *
 * Supported filters (all optional, all combined with AND):
 *  - period     string  Preset key (this_month, last_month, last_3_months, last_6_months, this_year, custom)
 *  - from       string  Custom period start (Y-m-d), only when period=custom
 *  - to         string  Custom period end (Y-m-d), only when period=custom
 *  - type       string  income, expense, transfer (or any subset as array)
 *  - account_ids array<int>  Filter by any of these account ids
 *  - category_ids array<int> Filter by any of these category ids
 *  - status     string  paid, pending
 *  - amount_min float   Inclusive lower bound in reais
 *  - amount_max float   Inclusive upper bound in reais
 *  - search     string  Free text in description/notes
 */
class TransactionFilterService
{
    /**
     * Preset date ranges keyed by preset name.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public static function periodPresets(): array
    {
        $today = CarbonImmutable::today();
        return [
            'this_month' => [
                'from' => $today->startOfMonth()->toDateString(),
                'to' => $today->endOfMonth()->toDateString(),
            ],
            'last_month' => [
                'from' => $today->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'to' => $today->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'last_3_months' => [
                'from' => $today->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
                'to' => $today->endOfMonth()->toDateString(),
            ],
            'last_6_months' => [
                'from' => $today->subMonthsNoOverflow(5)->startOfMonth()->toDateString(),
                'to' => $today->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                'from' => $today->startOfYear()->toDateString(),
                'to' => $today->endOfYear()->toDateString(),
            ],
        ];
    }

    /**
     * Validate and normalize a request's filter payload.
     *
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        $data = $request->validate([
            'period' => 'nullable|string|in:this_month,last_month,last_3_months,last_6_months,this_year,custom',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'type' => 'nullable|string|in:income,expense,transfer',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer',
            'status' => 'nullable|string|in:paid,pending',
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|min:0',
            'search' => 'nullable|string|max:255',
        ]);

        // Normalize empty arrays / strings
        foreach (['account_ids', 'category_ids'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = array_values(array_unique(array_map('intval', $data[$key])));
                if (empty($data[$key])) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    /**
     * Apply validated filters to a Transaction query.
     */
    public function apply(Builder $query, array $filters): Builder
    {
        // Date period
        [$from, $to] = $this->resolvePeriod($filters);
        if ($from && $to) {
            $query->inPeriod($from, $to);
        }

        // Type
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Accounts (multi): include source OR destination
        if (! empty($filters['account_ids'])) {
            $ids = $filters['account_ids'];
            $query->where(function (Builder $q) use ($ids) {
                $q->whereIn('account_id', $ids)
                  ->orWhereIn('destination_account_id', $ids);
            });
        }

        // Categories (multi)
        if (! empty($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        // Amount range (reais -> cents). Uses ABS because the column is signed.
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $min = (int) round(((float) $filters['amount_min']) * 100);
            $query->whereRaw('ABS(amount_cents) >= ?', [$min]);
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $max = (int) round(((float) $filters['amount_max']) * 100);
            $query->whereRaw('ABS(amount_cents) <= ?', [$max]);
        }

        // Free text
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('description', 'like', $term)
                  ->orWhere('notes', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * Resolve the [from, to] date pair from a filter set, or [null, null] when no period set.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string|null, 1: string|null}
     */
    public function resolvePeriod(array $filters): array
    {
        $period = $filters['period'] ?? null;
        if ($period && $period !== 'custom' && isset(self::periodPresets()[$period])) {
            $preset = self::periodPresets()[$period];
            return [$preset['from'], $preset['to']];
        }
        if ($period === 'custom') {
            return [
                $filters['from'] ?? null,
                $filters['to'] ?? null,
            ];
        }
        return [null, null];
    }

    /**
     * Build a query starting from a base model, with eager loading and user scope applied.
     */
    public function baseQuery(int $userId): Builder
    {
        return Transaction::with(['account', 'destinationAccount', 'category', 'tags'])
            ->where('user_id', $userId)
            ->orderByDesc('date')
            ->orderByDesc('id');
    }
}

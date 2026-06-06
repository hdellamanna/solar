<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search across user-owned entities (accounts, categories, transactions, tags).
 */
class SearchController extends Controller
{
    /**
     * Minimum number of characters in the query before any search runs.
     */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Default and max number of results per group.
     */
    private const DEFAULT_LIMIT = 5;
    private const MAX_LIMIT = 25;

    /**
     * GET /api/search?q=...&limit=...
     *
     * Returns a grouped set of matches. The user only ever sees their own
     * (or, for categories/tags, the global default) records.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return response()->json([
                'query' => $q,
                'accounts' => [],
                'categories' => [],
                'transactions' => [],
                'tags' => [],
            ]);
        }

        $userId = (int) $request->user()->id;
        $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $accounts = Account::query()
            ->where('user_id', $userId)
            ->where(function ($qb) use ($term) {
                $qb->where('name', 'like', $term)
                   ->orWhere('type', 'like', $term);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'type', 'color', 'currency']);

        $categories = Category::query()
            ->accessibleBy($userId)
            ->where('name', 'like', $term)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'type', 'icon', 'color', 'is_default']);

        // Transactions: match description, notes, OR amount expressed in reais.
        // amount_cents is signed, so for numeric matches we look at ABS values.
        $numericQuery = preg_replace('/\D+/', '', $q);
        $transactions = Transaction::query()
            ->with(['account:id,name', 'category:id,name,icon,color'])
            ->where('user_id', $userId)
            ->where(function ($qb) use ($term, $numericQuery) {
                $qb->where('description', 'like', $term)
                   ->orWhere('notes', 'like', $term);
                if ($numericQuery !== '') {
                    $qb->orWhereRaw('CAST(ABS(amount_cents) AS TEXT) LIKE ?', ['%' . $numericQuery . '%']);
                    // Also try matching the decimal form (e.g. "12,50" -> 12.50)
                    $reais = (float) ($numericQuery[0] === '0' ? '0.' . substr($numericQuery, 1) : $numericQuery);
                    if ($reais > 0) {
                        $cents = (int) round($reais * 100);
                        $qb->orWhereRaw('ABS(amount_cents) = ?', [$cents]);
                    }
                }
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $tags = Tag::query()
            ->accessibleBy($userId)
            ->where('name', 'like', $term)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'color']);

        return response()->json([
            'query' => $q,
            'limit' => $limit,
            'accounts' => $accounts,
            'categories' => $categories,
            'transactions' => $transactions,
            'tags' => $tags,
        ]);
    }
}

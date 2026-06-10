<?php

namespace App\Http\Controllers;

use App\Models\PixKey;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dedicated PIX UI (FASE 4C).
 *
 * Surfaces a single screen that gives the user a quick overview of
 * their PIX activity: latest transactions marked `is_pix`, the most
 * used PIX keys, and a static BR Code generator (mock — it produces
 * a copy-paste string in the simplified EMV format for demonstration,
 * it is NOT a real, scannable BR Code).
 */
class PixController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = Auth::id();

        // Most recent PIX transactions (income + expense, paid + pending).
        $recentPix = Transaction::with(['account', 'category'])
            ->where('user_id', $userId)
            ->where('is_pix', true)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount_cents' => $t->amount_cents,
                'amount_decimal' => $t->amount_decimal,
                'date' => $t->date->toDateString(),
                'description' => $t->description,
                'pix_key' => $t->pix_key,
                'status' => $t->status,
                'account' => $t->account?->only(['id', 'name', 'color']),
                'category' => $t->category?->only(['id', 'name', 'color', 'icon']),
            ]);

        // Top used PIX keys (group by lowercased trim, count + total cents).
        $pixKeys = Transaction::where('user_id', $userId)
            ->where('is_pix', true)
            ->whereNotNull('pix_key')
            ->where('pix_key', '<>', '')
            ->selectRaw('LOWER(TRIM(pix_key)) as norm_key')
            ->selectRaw('TRIM(pix_key) as display')
            ->selectRaw('amount_cents')
            ->selectRaw('date')
            ->get()
            ->groupBy('norm_key')
            ->map(function ($rows, $key) {
                $count = $rows->count();
                $totalCents = (int) $rows->sum('amount_cents');
                $last = $rows->sortByDesc('date')->first();
                return [
                    'key' => $rows->first()->display,
                    'count' => $count,
                    'total_cents' => $totalCents,
                    'last_used_at' => $last->date?->toDateString(),
                ];
            })
            ->sortByDesc('count')
            ->take(8)
            ->values();

        // Totals (current month PIX activity).
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $monthPix = Transaction::where('user_id', $userId)
            ->where('is_pix', true)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw('SUM(CASE WHEN type = "income" THEN amount_cents ELSE 0 END) as received_cents')
            ->selectRaw('SUM(CASE WHEN type = "expense" THEN amount_cents ELSE 0 END) as sent_cents')
            ->selectRaw('COUNT(*) as cnt')
            ->first();
        $receivedCents = (int) ($monthPix->received_cents ?? 0);
        $sentCents = (int) ($monthPix->sent_cents ?? 0);

        // Active saved PIX keys (the user-defined set, if any).
        $savedKeys = PixKey::where('user_id', $userId)
            ->orderByDesc('is_primary')
            ->orderBy('label')
            ->get(['id', 'label', 'key', 'type', 'is_primary']);

        return Inertia::render('Pix/Index', [
            'recent' => $recentPix,
            'top_keys' => $pixKeys,
            'saved_keys' => $savedKeys,
            'month_totals' => [
                'received_cents' => $receivedCents,
                'sent_cents' => $sentCents,
                'count' => (int) ($monthPix->cnt ?? 0),
            ],
        ]);
    }
}

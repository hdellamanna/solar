<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FX rate provider backed by frankfurter.app (FASE 6A).
 *
 * Frankfurter is a free, no-key, no-rate-limit public API maintained
 * by the European Central Bank. The same source ECB uses for its
 * official reference rates. Returns historical rates for any past
 * business day (weekdays, no weekends / holidays) and the latest
 * available rate for "today".
 *
 * We cache each (base, quote) pair for 12 hours; the rate moves
 * roughly once per business day anyway. Cache is keyed on the
 * base + quote + date so a transaction on 2026-06-15 with USD as
 * the source and BRL as the target always resolves to the rate
 * that was live on that day, not today's rate.
 */
class FxRateService
{
    private const ENDPOINT = 'https://api.frankfurter.app';
    private const CACHE_TTL = 60 * 60 * 12; // 12 hours

    /**
     * Return the rate at which 1 unit of `$base` equals `$quote`.
     * Returns 1.0 when base === quote (identity), and null when the
     * upstream call fails (callers should handle null gracefully).
     */
    public function rate(string $base, string $quote, ?string $date = null): ?float
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        if ($base === $quote) {
            return 1.0;
        }

        $cacheKey = $this->cacheKey($base, $quote, $date);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($base, $quote, $date) {
            return $this->fetch($base, $quote, $date);
        });
    }

    /**
     * Convert a cents amount from `$from` currency to `$to` currency,
     * using the rate captured on `$date` (or the latest if null).
     * Returns the converted amount in cents, or null on rate failure.
     */
    public function convertCents(int $cents, string $from, string $to, ?string $date = null): ?int
    {
        $rate = $this->rate($from, $to, $date);
        if ($rate === null) {
            return null;
        }
        return (int) round($cents * $rate);
    }

    /**
     * Invalidate the cache for a (base, quote) pair so the next
     * call pulls a fresh rate. Used by the admin / nightly cron
     * to keep rates current without waiting for TTL.
     */
    public function refresh(string $base, string $quote, ?string $date = null): ?float
    {
        Cache::forget($this->cacheKey(strtoupper($base), strtoupper($quote), $date));
        return $this->rate($base, $quote, $date);
    }

    private function fetch(string $base, string $quote, ?string $date): ?float
    {
        $url = $date
            ? sprintf('%s/%s?from=%s&to=%s', self::ENDPOINT, $date, $base, $quote)
            : sprintf('%s/latest?from=%s&to=%s', self::ENDPOINT, $base, $quote);

        try {
            $response = Http::timeout(8)->get($url);
            if (!$response->successful()) {
                Log::warning('FX rate fetch failed', ['url' => $url, 'status' => $response->status()]);
                return null;
            }
            $rate = $response->json('rates.' . $quote);
            return is_numeric($rate) ? (float) $rate : null;
        } catch (\Throwable $e) {
            Log::warning('FX rate fetch exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function cacheKey(string $base, string $quote, ?string $date): string
    {
        return sprintf('fx:%s:%s:%s', $base, $quote, $date ?? 'latest');
    }
}

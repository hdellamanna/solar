<?php

namespace App\Services;

use App\Models\Recurrence;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Generates transactions from active recurrences within a date window.
 */
class RecurrenceService
{
    /**
     * Generate transactions for a single recurrence that are due on/before $cutoff.
     *
     * @return array{count: int, dates: array<int, string>}
     */
    public function generateFor(Recurrence $recurrence, CarbonImmutable $cutoff): array
    {
        if (! $recurrence->active) {
            return ['count' => 0, 'dates' => []];
        }
        if ($recurrence->ends_at && CarbonImmutable::parse($recurrence->ends_at)->lt($cutoff)) {
            // Recurrence is already finished.
            $recurrence->update(['active' => false]);
            return ['count' => 0, 'dates' => []];
        }

        $startBoundary = $recurrence->last_generated_at
            ? CarbonImmutable::parse($recurrence->last_generated_at)
            : CarbonImmutable::parse($recurrence->starts_at)->subDay();

        $cursor = $this->advance($startBoundary, $recurrence->frequency);
        $count = 0;
        $dates = [];
        $lastDate = null;

        while ($cursor->lte($cutoff)) {
            if ($recurrence->ends_at && $cursor->gt(CarbonImmutable::parse($recurrence->ends_at))) {
                break;
            }
            $this->createTransaction($recurrence, $cursor);
            $count++;
            $lastDate = $cursor;
            $dates[] = $cursor->toDateString();
            $cursor = $this->advance($cursor, $recurrence->frequency);
        }

        if ($count > 0 && $lastDate) {
            $recurrence->update(['last_generated_at' => $lastDate->toDateString()]);
        }

        return ['count' => $count, 'dates' => $dates];
    }

    /**
     * Generate transactions for every active recurrence due within the given
     * lookahead window (default: 7 days).
     *
     * @param bool $execute When false, returns the plan without writing (used by --dry-run).
     * @return array<int, array{recurrence: Recurrence, count: int, dates: Collection}>
     */
    public function generateAll(int $lookaheadDays = 7, bool $execute = true): array
    {
        $cutoff = CarbonImmutable::today()->addDays($lookaheadDays);
        $report = [];

        Recurrence::active()
            ->with(['account', 'category'])
            ->orderBy('id')
            ->get()
            ->each(function (Recurrence $r) use ($cutoff, $execute, &$report) {
                if ($execute) {
                    $result = $this->generateFor($r, $cutoff);
                    $dates = collect($result['dates']);
                    $count = $result['count'];
                } else {
                    $startBoundary = $r->last_generated_at
                        ? CarbonImmutable::parse($r->last_generated_at)
                        : CarbonImmutable::parse($r->starts_at)->subDay();
                    $cursor = $this->advance($startBoundary, $r->frequency);
                    $dates = collect();
                    while ($cursor->lte($cutoff)) {
                        if ($r->ends_at && $cursor->gt(CarbonImmutable::parse($r->ends_at))) {
                            break;
                        }
                        $dates->push($cursor->toDateString());
                        $cursor = $this->advance($cursor, $r->frequency);
                    }
                    $count = $dates->count();
                }
                $report[] = ['recurrence' => $r, 'count' => $count, 'dates' => $dates];
            });

        return $report;
    }

    /**
     * Advance a date by the recurrence frequency (one step forward).
     */
    public function advance(CarbonImmutable $current, string $frequency): CarbonImmutable
    {
        return match ($frequency) {
            'daily' => $current->addDay(),
            'weekly' => $current->addWeek(),
            'monthly' => $current->addMonth(),
            'yearly' => $current->addYear(),
            default => $current->addDay(),
        };
    }

    /**
     * Build and persist the actual Transaction row.
     */
    private function createTransaction(Recurrence $r, CarbonImmutable $date): Transaction
    {
        $amount = (int) $r->amount_cents;
        $signed = in_array($r->type, ['expense', 'transfer'], true) ? -abs($amount) : abs($amount);

        return Transaction::create([
            'user_id' => $r->user_id,
            'account_id' => $r->account_id,
            'destination_account_id' => null,
            'category_id' => $r->category_id,
            'type' => $r->type,
            'amount_cents' => $signed,
            'date' => $date->toDateString(),
            'description' => $r->description,
            'notes' => null,
            'status' => 'paid',
            'is_pix' => false,
            'pix_key' => null,
            'recurrence_id' => $r->id,
        ]);
    }
}

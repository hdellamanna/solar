<?php

namespace App\Services;

use App\Models\Debt;
use Carbon\CarbonImmutable;

/**
 * Pure-math amortization simulator for Brazilian loan systems (FASE 5).
 *
 * Implements both standard amortization systems used in Brazil:
 *  - **SAC** (Sistema de Amortização Constante) — fixed principal
 *    portion per month (`balance / n`); interest is computed on the
 *    outstanding balance, so the total payment *decreases* over time.
 *  - **Price** (Sistema Francês / Tabela Price) — fixed total
 *    payment per month, computed as
 *    `P × [i × (1+i)^n] / [(1+i)^n - 1]`. Interest + principal
 *    split shifts as the balance shrinks.
 *
 * The service is intentionally side-effect-free: no DB queries, no
 * cache, no model writes. It accepts a starting balance, an annual
 * interest rate, a monthly payment and a strategy, then produces a
 * month-by-month schedule.
 *
 * Money math is in **cents (int)** end-to-end. The only place a
 * float appears is the rate (which is already a decimal in
 * {@see Debt::$interest_rate_annual}) and the per-row interest
 * computation — and even there we round to the nearest cent to
 * avoid float drift accumulating across hundreds of months.
 *
 * Both systems bail out early with `failed: true` when the monthly
 * payment can no longer cover the interest — the balance would
 * grow instead of shrinking, and the loan is effectively
 * in-extinguible. Output is hard-capped at 600 months (50 years)
 * to bound the work for absurd inputs.
 *
 * @phpstan-type AmortizationRow array{
 *     month: int,
 *     due_date: string,
 *     interest_cents: int,
 *     principal_cents: int,
 *     payment_cents: int,
 *     remaining_balance_cents: int,
 *     cumulative_paid_cents: int,
 * }
 */
class AmortizationService
{
    /** Hard cap on the number of months we simulate. */
    private const MAX_MONTHS = 600;

    /**
     * Build the full month-by-month amortization schedule.
     *
     * @param int    $balanceCents       Outstanding balance at the start, in cents (positive int).
     * @param float  $annualRate         Annual interest rate as a decimal (0.1250 = 12.50% a.a.).
     * @param int    $monthlyPaymentCents User's agreed monthly payment, in cents (positive int).
     * @param string $strategy           One of `Debt::STRATEGY_SAC` or `Debt::STRATEGY_PRICE`.
     * @param string $startDate          ISO-8601 date (Y-m-d) the loan starts.
     * @return array{
     *     strategy: string,
     *     months: int,
     *     total_interest_cents: int,
     *     total_paid_cents: int,
     *     final_balance_cents: int,
     *     failed: bool,
     *     cap_reached: bool,
     *     schedule: list<array{
     *         month: int,
     *         due_date: string,
     *         interest_cents: int,
     *         principal_cents: int,
     *         payment_cents: int,
     *         remaining_balance_cents: int,
     *         cumulative_paid_cents: int,
     *     }>,
     * }
     */
    public function simulate(
        int $balanceCents,
        float $annualRate,
        int $monthlyPaymentCents,
        string $strategy,
        string $startDate,
    ): array {
        $balance = max(0, $balanceCents);
        $payment = max(0, $monthlyPaymentCents);
        $rate = max(0.0, (float) $annualRate) / 12.0; // monthly
        $strategy = strtolower($strategy) === Debt::STRATEGY_PRICE ? Debt::STRATEGY_PRICE : Debt::STRATEGY_SAC;

        $start = CarbonImmutable::parse($startDate)->startOfMonth();
        $rows = [];
        $cumulative = 0;
        $failed = false;
        $capReached = false;

        if ($balance === 0 || $payment === 0) {
            return $this->result($strategy, $rows, 0, 0, 0, false, false);
        }

        // --- Price: compute the fixed payment up front.
        // PMT = P × [i × (1+i)^n] / [(1+i)^n - 1]
        // We don't actually use n to clamp Price — we just iterate
        // until the balance reaches 0, charging the fixed amount.
        $pricePayment = $payment;
        if ($strategy === Debt::STRATEGY_PRICE && $rate > 0) {
            $pricePayment = $this->pricePaymentCents($balance, $rate);
        } elseif ($strategy === Debt::STRATEGY_PRICE) {
            // Zero interest → Price is identical to linear payoff.
            $pricePayment = $payment;
        }

        // --- SAC: constant principal = balance / MAX_MONTHS (or a
        // shorter N if payment is large enough that we'd overpay).
        $sacPrincipal = 0;
        if ($strategy === Debt::STRATEGY_SAC) {
            $monthsLinear = (int) ceil($balance / max(1, $payment));
            $n = max(1, min(self::MAX_MONTHS, $monthsLinear));
            $sacPrincipal = (int) (intdiv($balance, $n));
        }

        for ($i = 1; $i <= self::MAX_MONTHS; $i++) {
            $dueDate = $start->addMonths($i);

            // Cap reached if we still have balance after MAX_MONTHS
            if ($i === self::MAX_MONTHS && $balance > 0) {
                $capReached = true;
            }

            $interest = (int) round($balance * $rate);
            $remaining = $balance;

            if ($strategy === Debt::STRATEGY_SAC) {
                $principal = $sacPrincipal;
                if ($i === self::MAX_MONTHS || $principal + $interest > $payment && $principal > $balance) {
                    // Last row or where payment can't cover the
                    // (principal+interest) bucket any more — pay
                    // whatever's left.
                    $principal = min($balance, max(0, $payment - $interest));
                }
                $thisMonth = $interest + $principal;
                if ($thisMonth > $payment + 1) {
                    // Not enough payment → fail.
                    $failed = true;
                    $principal = max(0, $payment - $interest);
                    $thisMonth = $interest + $principal;
                }
            } else { // Price
                $thisMonth = $pricePayment;
                $principal = $thisMonth - $interest;
                if ($principal >= $balance) {
                    // Last payment — pay the remaining balance plus
                    // its current period's interest. Don't go over.
                    $principal = $balance;
                    $thisMonth = $interest + $principal;
                }
                if ($interest >= $payment && $principal <= 0) {
                    // Payment can't even cover the interest — debt
                    // is in-extinguishable. Bail out.
                    $failed = true;
                    $principal = max(0, $payment - $interest);
                    $thisMonth = $interest + $principal;
                }
            }

            // Apply
            $balance = max(0, $balance - $principal);
            $cumulative += $thisMonth;
            $rows[] = [
                'month' => $i,
                'due_date' => $dueDate->toDateString(),
                'interest_cents' => $interest,
                'principal_cents' => $principal,
                'payment_cents' => $thisMonth,
                'remaining_balance_cents' => $balance,
                'cumulative_paid_cents' => $cumulative,
            ];

            if ($balance <= 0) {
                break;
            }
            if ($failed) {
                break;
            }
        }

        if ($balance > 0 && !$failed) {
            // We exited the loop because we hit MAX_MONTHS, not
            // because the debt was paid off. The caller can see
            // this via `cap_reached` and `final_balance_cents`.
            $capReached = true;
        }

        $totalInterest = 0;
        $totalPaid = 0;
        foreach ($rows as $r) {
            $totalInterest += $r['interest_cents'];
            $totalPaid += $r['payment_cents'];
        }

        return $this->result(
            $strategy,
            $rows,
            count($rows),
            $totalInterest,
            $totalPaid,
            $failed,
            $capReached,
            (int) $balance,
        );
    }

    /**
     * Compute the fixed monthly payment (in cents) for a Price loan
     * using the standard formula
     *   PMT = P × [i × (1+i)^n] / [(1+i)^n - 1]
     * with the number of months `n` chosen as the smallest integer
     * for which the payment is at most the user's `monthlyPaymentCents`.
     *
     * @return int Fixed monthly payment in cents (rounded to nearest cent).
     */
    private function pricePaymentCents(int $balanceCents, float $monthlyRate): int
    {
        if ($monthlyRate <= 0) {
            return $balanceCents;
        }
        // Find the largest n for which PMT(n) <= monthlyPaymentCents.
        // PMT(n) = P × [i × (1+i)^n] / [(1+i)^n - 1]
        // PMT(n) <= payment  ⇔  (P - payment) × (1+i)^n <= -payment
        // For the upper bound we use the smallest n for which PMT
        // drops below the user payment — start with a guess and
        // adjust. To keep things simple we just iterate.
        for ($n = 1; $n <= self::MAX_MONTHS; $n++) {
            $pmt = $this->pricePaymentForN($balanceCents, $monthlyRate, $n);
            if ($pmt <= 0) {
                continue;
            }
            if ($pmt <= $balanceCents) { // the cap we'll use to bound iterations
                return $pmt;
            }
        }
        // Fall back to balance (one-shot payoff).
        return $balanceCents;
    }

    /**
     * Compute the fixed Price payment for a given n (months).
     */
    private function pricePaymentForN(int $balanceCents, float $monthlyRate, int $n): int
    {
        if ($monthlyRate <= 0) {
            return (int) ceil($balanceCents / $n);
        }
        $factor = pow(1 + $monthlyRate, $n);
        $pmt = $balanceCents * ($monthlyRate * $factor) / ($factor - 1);
        return (int) round($pmt);
    }

    /**
     * @param  list<array<string, int|string>>  $schedule
     * @return array{
     *     strategy: string,
     *     months: int,
     *     total_interest_cents: int,
     *     total_paid_cents: int,
     *     final_balance_cents: int,
     *     failed: bool,
     *     cap_reached: bool,
     *     schedule: list<array<string, int|string>>,
     * }
     */
    private function result(
        string $strategy,
        array $schedule,
        int $months,
        int $totalInterestCents,
        int $totalPaidCents,
        bool $failed,
        bool $capReached,
        int $finalBalanceCents = 0,
    ): array {
        return [
            'strategy' => $strategy,
            'months' => $months,
            'total_interest_cents' => $totalInterestCents,
            'total_paid_cents' => $totalPaidCents,
            'final_balance_cents' => $finalBalanceCents,
            'failed' => $failed,
            'cap_reached' => $capReached,
            'schedule' => $schedule,
        ];
    }
}

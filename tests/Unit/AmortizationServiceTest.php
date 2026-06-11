<?php

namespace Tests\Unit;

use App\Models\Debt;
use App\Services\AmortizationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for {@see AmortizationService} (FASE 5).
 *
 * Pure-math tests — no DB, no HTTP, no models. We just exercise
 * the `simulate()` method with a few canonical inputs and assert
 * the structural invariants of each amortization system (SAC vs
 * Price). All money is asserted in cents (int) to stay aligned
 * with the rest of the application.
 */
class AmortizationServiceTest extends TestCase
{
    private AmortizationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AmortizationService();
    }

    public function test_sac_amortization_produces_constant_principal(): void
    {
        // 12-month SAC, 0% interest, R$ 12.000 starting balance,
        // R$ 1.000 monthly payment → principal should be ~1000 / month.
        $result = $this->svc->simulate(
            balanceCents: 1_200_000,
            annualRate: 0.0,
            monthlyPaymentCents: 100_000,
            strategy: Debt::STRATEGY_SAC,
            startDate: '2026-01-01',
        );

        $this->assertSame(Debt::STRATEGY_SAC, $result['strategy']);
        $this->assertSame(12, $result['months']);
        $this->assertSame(0, $result['total_interest_cents']);
        $this->assertSame(1_200_000, $result['total_paid_cents']);
        $this->assertSame(0, $result['final_balance_cents']);
        $this->assertFalse($result['failed']);
        $this->assertFalse($result['cap_reached']);

        // Principal portion should be ~1000 (100_000 cents) per month
        // and may differ on the last row to land exactly on 0.
        $principals = array_column($result['schedule'], 'principal_cents');
        $firstPrincipal = $principals[0];
        for ($i = 1; $i < count($principals) - 1; $i++) {
            $this->assertSame($firstPrincipal, $principals[$i], "Principal at month $i ({$principals[$i]}) != month 1 ($firstPrincipal)");
        }
        // Total of all principals must equal the original balance exactly.
        $this->assertSame(1_200_000, array_sum($principals));
    }

    public function test_sac_interest_decreases_each_month(): void
    {
        // 24-month SAC, 1% a.m. (12% a.a.), R$ 24.000 starting, R$ 1.100 payment.
        $result = $this->svc->simulate(
            balanceCents: 2_400_000,
            annualRate: 0.12,
            monthlyPaymentCents: 110_000,
            strategy: Debt::STRATEGY_SAC,
            startDate: '2026-01-01',
        );

        $interests = array_column($result['schedule'], 'interest_cents');
        // Interest must be monotonically non-increasing for SAC.
        for ($i = 1; $i < count($interests); $i++) {
            $this->assertLessThanOrEqual($interests[$i - 1], $interests[$i], "Interest grew at month $i");
        }
        // First month's interest is ~ R$ 240 (balance * 1%).
        $this->assertEqualsWithDelta(24_000, $interests[0], 200);
    }

    public function test_price_amortization_produces_constant_payment(): void
    {
        // 12-month Price, 1% a.m., R$ 12.000 starting, R$ 1.100 payment.
        $result = $this->svc->simulate(
            balanceCents: 1_200_000,
            annualRate: 0.12,
            monthlyPaymentCents: 110_000,
            strategy: Debt::STRATEGY_PRICE,
            startDate: '2026-01-01',
        );

        $this->assertSame(Debt::STRATEGY_PRICE, $result['strategy']);
        $this->assertGreaterThan(1, $result['months']);
        $this->assertSame(0, $result['final_balance_cents']);
        $this->assertFalse($result['failed']);
        $this->assertFalse($result['cap_reached']);

        // All but the last row's payment should be the same.
        $payments = array_column($result['schedule'], 'payment_cents');
        $thisMonth = $payments[0];
        $this->assertGreaterThan(0, $thisMonth);
        for ($i = 1; $i < count($payments) - 1; $i++) {
            $this->assertSame($thisMonth, $payments[$i], "Price payment at month $i ({$payments[$i]}) != month 1 ($thisMonth)");
        }
    }

    public function test_price_schedule_pays_off_in_same_time_as_sac(): void
    {
        // Same principal/payment/rate as SAC test — Price with the
        // same monthly payment should pay off in ~ the same time.
        $sac = $this->svc->simulate(
            1_200_000, 0.12, 110_000, Debt::STRATEGY_SAC, '2026-01-01',
        );
        $price = $this->svc->simulate(
            1_200_000, 0.12, 110_000, Debt::STRATEGY_PRICE, '2026-01-01',
        );

        $this->assertSame($sac['months'], $price['months']);
        $this->assertSame(0, $price['final_balance_cents']);
        $this->assertSame(0, $sac['final_balance_cents']);
    }

    public function test_zero_interest_rate_is_linear_payoff(): void
    {
        // 18-month zero-interest, R$ 9.000 starting, R$ 500 payment.
        $result = $this->svc->simulate(
            balanceCents: 900_000,
            annualRate: 0.0,
            monthlyPaymentCents: 50_000,
            strategy: Debt::STRATEGY_SAC,
            startDate: '2026-01-01',
        );

        $this->assertSame(18, $result['months']);
        $this->assertSame(0, $result['total_interest_cents']);
        $this->assertSame(900_000, $result['total_paid_cents']);
        $this->assertSame(0, $result['final_balance_cents']);
    }

    public function test_insufficient_payment_flag_is_set_when_payment_below_monthly_interest(): void
    {
        // R$ 1.000 starting, 100% a.m. (so R$ 1.000 interest/month),
        // R$ 50 payment — payment is dwarfed by interest.
        $result = $this->svc->simulate(
            balanceCents: 100_000,
            annualRate: 12.0, // 1200% a.a. = 100% a.m.
            monthlyPaymentCents: 5_000,
            strategy: Debt::STRATEGY_SAC,
            startDate: '2026-01-01',
        );

        $this->assertTrue($result['failed'], 'Should bail out with failed: true');
        $this->assertSame(1, $result['months'], 'Should bail out on first row');
        $this->assertGreaterThan(0, $result['final_balance_cents']);
    }

    public function test_cap_at_600_months(): void
    {
        // Small payment that would never pay off a big balance — must
        // hard-cap at 600 months rather than infinite-loop.
        $result = $this->svc->simulate(
            balanceCents: 100_000_000,    // R$ 1.000.000
            annualRate: 0.0,
            monthlyPaymentCents: 100,     // R$ 1
            strategy: Debt::STRATEGY_SAC,
            startDate: '2026-01-01',
        );

        $this->assertTrue($result['cap_reached']);
        $this->assertSame(600, $result['months']);
        $this->assertGreaterThan(0, $result['final_balance_cents']);
    }

    public function test_no_float_drift_at_final_row(): void
    {
        // For a typical 36-month Price, the balance should reach 0
        // exactly (or within 1 cent of it) at the final row.
        $result = $this->svc->simulate(
            balanceCents: 360_000,    // R$ 3.600
            annualRate: 0.02,         // 2% a.m. (24% a.a.)
            monthlyPaymentCents: 14_000, // R$ 140
            strategy: Debt::STRATEGY_PRICE,
            startDate: '2026-01-01',
        );

        $this->assertGreaterThan(0, $result['months']);
        // Final row's balance should be 0 or extremely close.
        $finalBalance = $result['final_balance_cents'];
        $this->assertLessThanOrEqual(1, abs($finalBalance), "Final balance was $finalBalance cents — float drift detected");

        // And every intermediate row's balance should be 0 or positive.
        foreach ($result['schedule'] as $row) {
            $this->assertGreaterThanOrEqual(0, $row['remaining_balance_cents']);
        }
    }

    public function test_cumulative_paid_is_monotonically_increasing(): void
    {
        $result = $this->svc->simulate(
            balanceCents: 600_000,
            annualRate: 0.01,
            monthlyPaymentCents: 30_000,
            strategy: Debt::STRATEGY_SAC,
            startDate: '2026-01-01',
        );

        $cum = array_column($result['schedule'], 'cumulative_paid_cents');
        for ($i = 1; $i < count($cum); $i++) {
            $this->assertGreaterThan($cum[$i - 1], $cum[$i], "Cumulative total did not grow at month $i");
        }
    }

    public function test_due_dates_are_monthly_from_start_date(): void
    {
        $result = $this->svc->simulate(
            balanceCents: 300_000,
            annualRate: 0.0,
            monthlyPaymentCents: 50_000,
            strategy: Debt::STRATEGY_SAC,
            startDate: '2026-01-15',
        );

        $this->assertSame('2026-02-01', $result['schedule'][0]['due_date']);
        $this->assertSame('2026-03-01', $result['schedule'][1]['due_date']);
        $this->assertSame('2026-06-01', $result['schedule'][4]['due_date']);
    }
}

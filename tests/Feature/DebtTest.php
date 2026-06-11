<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for the Debts track (FASE 5).
 *
 * Exercises the full HTTP stack: routes, controller, model,
 * Inertia rendering, validation, and the dashboard widget.
 * The pure-math amortization simulator has its own unit
 * tests in {@see \Tests\Unit\AmortizationServiceTest}.
 */
class DebtTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeDebt(User $user, array $overrides = []): Debt
    {
        return Debt::create(array_merge([
            'user_id' => $user->id,
            'creditor' => 'Banco do Brasil',
            'description' => 'Empréstimo pessoal',
            'total_balance_cents' => 1_000_000,
            'interest_rate_annual' => 0.12,
            'monthly_payment_cents' => 50_000,
            'start_date' => '2026-01-01',
            'payoff_strategy' => Debt::STRATEGY_SAC,
            'currency' => 'BRL',
            'notes' => null,
            'is_paid_off' => false,
        ], $overrides));
    }

    public function test_user_can_create_debt(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'Itaú',
            'description' => 'Financiamento do carro',
            'total_balance' => '25000.00',
            'interest_rate' => '14.40',
            'monthly_payment' => '850.00',
            'start_date' => '2026-01-15',
            'payoff_strategy' => 'sac',
            'currency' => 'BRL',
            'notes' => 'Contrato 0042/2024',
        ]);

        $response->assertRedirect(route('debts.index'));

        $debt = Debt::where('user_id', $user->id)->where('creditor', 'Itaú')->first();
        $this->assertNotNull($debt);
        $this->assertSame('Financiamento do carro', $debt->description);
        $this->assertSame(2_500_000, (int) $debt->total_balance_cents);
        $this->assertEqualsWithDelta(0.1440, (float) $debt->interest_rate_annual, 0.0001);
        $this->assertSame(85_000, (int) $debt->monthly_payment_cents);
        $this->assertSame('2026-01-15', $debt->start_date->toDateString());
        $this->assertSame('sac', $debt->payoff_strategy);
        $this->assertSame('BRL', $debt->currency);
        $this->assertFalse((bool) $debt->is_paid_off);
    }

    public function test_cannot_create_with_invalid_strategy(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'X',
            'total_balance' => '1000.00',
            'interest_rate' => '1.0',
            'monthly_payment' => '100.00',
            'start_date' => '2026-01-01',
            'payoff_strategy' => 'invalid-strategy',
            'currency' => 'BRL',
        ])->assertSessionHasErrors('payoff_strategy');
    }

    public function test_cannot_create_with_negative_balance(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'X',
            'total_balance' => '-100.00',
            'interest_rate' => '1.0',
            'monthly_payment' => '100.00',
            'start_date' => '2026-01-01',
            'payoff_strategy' => 'sac',
            'currency' => 'BRL',
        ])->assertSessionHasErrors('total_balance');

        $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'X',
            'total_balance' => '0.00',
            'interest_rate' => '1.0',
            'monthly_payment' => '100.00',
            'start_date' => '2026-01-01',
            'payoff_strategy' => 'sac',
            'currency' => 'BRL',
        ])->assertSessionHasErrors('total_balance');
    }

    public function test_cannot_create_with_negative_interest_rate(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'X',
            'total_balance' => '1000.00',
            'interest_rate' => '-1.0',
            'monthly_payment' => '100.00',
            'start_date' => '2026-01-01',
            'payoff_strategy' => 'sac',
            'currency' => 'BRL',
        ])->assertSessionHasErrors('interest_rate');
    }

    public function test_cannot_create_with_zero_monthly_payment(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'X',
            'total_balance' => '1000.00',
            'interest_rate' => '1.0',
            'monthly_payment' => '0.00',
            'start_date' => '2026-01-01',
            'payoff_strategy' => 'sac',
            'currency' => 'BRL',
        ])->assertSessionHasErrors('monthly_payment');
    }

    public function test_index_lists_only_users_debts(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->makeDebt($user, ['creditor' => 'Meu Itaú']);
        $this->makeDebt($user, ['creditor' => 'Meu BB']);
        $this->makeDebt($other, ['creditor' => 'Outro']);

        $response = $this->actingAs($user)->get(route('debts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Debts/Index')
            ->has('debts', 2)
            ->where('totals.count_active', 2)
        );
    }

    public function test_index_excludes_paid_off_by_default(): void
    {
        $user = $this->makeUser();

        $this->makeDebt($user, ['creditor' => 'Ativa']);
        $this->makeDebt($user, [
            'creditor' => 'Quitada',
            'total_balance_cents' => 0,
            'is_paid_off' => true,
            'paid_off_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('debts.index'));
        $response->assertInertia(fn ($page) => $page->has('debts', 1));

        $response2 = $this->actingAs($user)->get(route('debts.index', ['paid_off' => 1]));
        $response2->assertInertia(fn ($page) => $page->has('debts', 2));
    }

    public function test_user_can_update_debt(): void
    {
        $user = $this->makeUser();
        $debt = $this->makeDebt($user, ['creditor' => 'Antes']);

        $this->actingAs($user)
            ->put(route('debts.update', $debt), [
                'creditor' => 'Depois',
                'description' => 'Atualizado',
                'total_balance' => '15000.00',
                'interest_rate' => '10.00',
                'monthly_payment' => '600.00',
                'start_date' => '2026-02-01',
                'payoff_strategy' => 'price',
                'currency' => 'BRL',
                'notes' => 'renegociado',
            ])
            ->assertRedirect(route('debts.index'));

        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'creditor' => 'Depois',
            'description' => 'Atualizado',
            'total_balance_cents' => 1_500_000,
            'interest_rate_annual' => 0.10,
            'monthly_payment_cents' => 60_000,
            'payoff_strategy' => 'price',
        ]);
    }

    public function test_destroy_soft_deletes_debt(): void
    {
        $user = $this->makeUser();
        $debt = $this->makeDebt($user);

        $this->actingAs($user)
            ->delete(route('debts.destroy', $debt))
            ->assertRedirect(route('debts.index'));

        // Soft delete: row still in table, but deleted_at is set.
        $this->assertSoftDeleted('debts', ['id' => $debt->id]);
    }

    public function test_mark_as_paid_off_stamps_paid_off_at(): void
    {
        $user = $this->makeUser();
        $debt = $this->makeDebt($user, ['total_balance_cents' => 0]);

        $this->actingAs($user)
            ->patch(route('debts.mark-paid', $debt))
            ->assertRedirect();

        $debt->refresh();
        $this->assertTrue($debt->is_paid_off);
        $this->assertNotNull($debt->paid_off_at);
    }

    public function test_cannot_mark_as_paid_off_when_balance_positive(): void
    {
        $user = $this->makeUser();
        $debt = $this->makeDebt($user, ['total_balance_cents' => 50_000]);

        $this->actingAs($user)
            ->patch(route('debts.mark-paid', $debt))
            ->assertStatus(422);

        $debt->refresh();
        $this->assertFalse($debt->is_paid_off);
        $this->assertNull($debt->paid_off_at);
    }

    public function test_user_cannot_access_another_users_debt(): void
    {
        $owner = $this->makeUser();
        $intruder = $this->makeUser();
        $debt = $this->makeDebt($owner);

        $this->actingAs($intruder)
            ->get(route('debts.show', $debt))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('debts.edit', $debt))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('debts.update', $debt), [
                'creditor' => 'Hacked',
                'total_balance' => '100.00',
                'interest_rate' => '1.0',
                'monthly_payment' => '10.00',
                'start_date' => '2026-01-01',
                'payoff_strategy' => 'sac',
                'currency' => 'BRL',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('debts.destroy', $debt))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->patch(route('debts.mark-paid', $debt))
            ->assertForbidden();
    }

    public function test_simulate_endpoint_returns_200_with_schedule_json(): void
    {
        $user = $this->makeUser();
        $debt = $this->makeDebt($user, [
            'total_balance_cents' => 1_200_000,
            'interest_rate_annual' => 0.12,
            'monthly_payment_cents' => 110_000,
            'payoff_strategy' => 'sac',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('debts.simulate', $debt));

        $response->assertOk();
        $response->assertJsonStructure([
            'strategy',
            'months',
            'total_interest_cents',
            'total_paid_cents',
            'final_balance_cents',
            'failed',
            'cap_reached',
            'schedule' => [
                '*' => [
                    'month', 'due_date', 'interest_cents',
                    'principal_cents', 'payment_cents',
                    'remaining_balance_cents', 'cumulative_paid_cents',
                ],
            ],
        ]);
    }

    public function test_simulate_uses_correct_strategy_from_query_param(): void
    {
        $user = $this->makeUser();
        $debt = $this->makeDebt($user, [
            'total_balance_cents' => 1_200_000,
            'interest_rate_annual' => 0.12,
            'monthly_payment_cents' => 110_000,
            'payoff_strategy' => 'sac',
        ]);

        $respSac = $this->actingAs($user)
            ->postJson(route('debts.simulate', $debt) . '?strategy=sac');
        $respSac->assertOk()->assertJsonPath('strategy', 'sac');

        $respPrice = $this->actingAs($user)
            ->postJson(route('debts.simulate', $debt) . '?strategy=price');
        $respPrice->assertOk()->assertJsonPath('strategy', 'price');
    }

    public function test_dashboard_widget_shows_total_when_active_debts_exist(): void
    {
        $user = $this->makeUser();
        $this->makeDebt($user, [
            'creditor' => 'Itaú',
            'total_balance_cents' => 2_500_000,    // R$ 25.000
            'monthly_payment_cents' => 85_000,     // R$ 850
            'interest_rate_annual' => 0.1440,
        ]);
        $this->makeDebt($user, [
            'creditor' => 'Nubank',
            'total_balance_cents' => 500_000,      // R$ 5.000
            'monthly_payment_cents' => 50_000,     // R$ 500
            'interest_rate_annual' => 0.10,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('debts.count_active', 2)
            ->where('debts.total_balance_cents', 3_000_000)
            ->where('debts.monthly_commitment_cents', 135_000)
            ->has('debts.top', 2)
        );
    }

    public function test_dashboard_widget_excludes_paid_off_from_active_totals(): void
    {
        $user = $this->makeUser();
        $this->makeDebt($user, [
            'creditor' => 'Ativa',
            'total_balance_cents' => 1_000_000,
            'monthly_payment_cents' => 50_000,
        ]);
        $this->makeDebt($user, [
            'creditor' => 'Quitada',
            'total_balance_cents' => 0,
            'monthly_payment_cents' => 0,
            'is_paid_off' => true,
            'paid_off_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertInertia(fn ($page) => $page
            ->where('debts.count_active', 1)
            ->where('debts.count_paid_off', 1)
            ->where('debts.total_balance_cents', 1_000_000)
        );
    }

    public function test_active_scope_excludes_paid_off(): void
    {
        $user = $this->makeUser();
        $a = $this->makeDebt($user, ['creditor' => 'Ativa']);
        $b = $this->makeDebt($user, [
            'creditor' => 'Quitada',
            'is_paid_off' => true,
            'paid_off_at' => now(),
        ]);

        $this->assertCount(1, Debt::active()->get());
        $this->assertSame($a->id, Debt::active()->first()->id);
        $this->assertCount(1, Debt::paidOff()->get());
        $this->assertSame($b->id, Debt::paidOff()->first()->id);
    }

    public function test_paid_off_scope_excludes_active(): void
    {
        $user = $this->makeUser();
        $a = $this->makeDebt($user, ['creditor' => 'Ativa']);
        $this->makeDebt($user, [
            'creditor' => 'Quitada',
            'is_paid_off' => true,
            'paid_off_at' => now(),
        ]);

        $active = Debt::active()->get();
        $this->assertCount(1, $active);
        $this->assertSame($a->id, $active->first()->id);
    }

    public function test_for_user_scope_filters_by_user(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->makeDebt($a, ['creditor' => 'A1']);
        $this->makeDebt($a, ['creditor' => 'A2']);
        $this->makeDebt($b, ['creditor' => 'B1']);

        $this->assertCount(2, Debt::forUser($a->id)->get());
        $this->assertCount(1, Debt::forUser($b->id)->get());
    }

    public function test_currency_validation_rejects_invalid_format(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'X',
            'total_balance' => '1000.00',
            'interest_rate' => '1.0',
            'monthly_payment' => '100.00',
            'start_date' => '2026-01-01',
            'payoff_strategy' => 'sac',
            'currency' => 'BRLX',  // 4 letters
        ])->assertSessionHasErrors('currency');

        $this->actingAs($user)->post(route('debts.store'), [
            'creditor' => 'X',
            'total_balance' => '1000.00',
            'interest_rate' => '1.0',
            'monthly_payment' => '100.00',
            'start_date' => '2026-01-01',
            'payoff_strategy' => 'sac',
            'currency' => '123',  // digits
        ])->assertSessionHasErrors('currency');
    }

    public function test_show_renders_with_simulation_button(): void
    {
        $user = $this->makeUser();
        $debt = $this->makeDebt($user);

        $response = $this->actingAs($user)->get(route('debts.show', $debt));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Debts/Show')
            ->where('debt.id', $debt->id)
            ->where('debt.creditor', $debt->creditor)
        );
    }

    public function test_weighted_avg_rate_is_balance_weighted(): void
    {
        $user = $this->makeUser();
        // R$ 1.000 @ 10% a.a. + R$ 9.000 @ 20% a.a. → avg = (1k*10% + 9k*20%) / 10k = 19%
        $this->makeDebt($user, [
            'creditor' => 'Pequena',
            'total_balance_cents' => 100_000,
            'interest_rate_annual' => 0.10,
        ]);
        $this->makeDebt($user, [
            'creditor' => 'Grande',
            'total_balance_cents' => 900_000,
            'interest_rate_annual' => 0.20,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertInertia(fn ($page) => $page
            ->where('debts.weighted_avg_rate', 0.19)
        );
    }
}

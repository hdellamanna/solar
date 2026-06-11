<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the Investments feature (FASE 5).
 *
 * Investments are the user's portfolio tracker — each row is a
 * single asset (stock, fund, crypto, fixed-income, treasury) with
 * a quantity, an average price, and an optional current price.
 * P&L is derived from these fields.
 */
class InvestmentTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Itausa (ITSA4)',
            'type'          => 'stock',
            'ticker'        => 'ITSA4',
            'quantity'      => '100',
            'average_price' => '10.50',
            'current_price' => '11.20',
            'currency'      => 'BRL',
            'acquired_at'   => '2026-01-15',
            'notes'         => 'Test seed',
        ], $overrides);
    }

    public function test_user_can_create_investment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload())
            ->assertRedirect(route('investments.index'));

        $this->assertDatabaseHas('investments', [
            'user_id'             => $user->id,
            'name'                => 'Itausa (ITSA4)',
            'type'                => 'stock',
            'ticker'              => 'ITSA4',
            'quantity'            => 100.0,
            'average_price_cents' => 1050,
            'current_price_cents' => 1120,
            'currency'            => 'BRL',
        ]);
    }

    public function test_user_cannot_create_with_invalid_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload(['type' => 'unobtanium']))
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('investments', 0);
    }

    public function test_quantity_must_be_positive(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload(['quantity' => '0']))
            ->assertSessionHasErrors('quantity');

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload(['quantity' => '-5']))
            ->assertSessionHasErrors('quantity');
    }

    public function test_index_lists_only_users_investments(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Investment::factory()->for($user)->create(['name' => 'Meu ITSA4']);
        Investment::factory()->for($other)->create(['name' => 'Outro ITSA4']);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Investments/Index')
            ->has('investments', 1)
            ->where('investments.0.name', 'Meu ITSA4')
            ->where('totals.count', 1)
        );
    }

    public function test_index_paginates_results(): void
    {
        $user = User::factory()->create();
        Investment::factory()->for($user)->count(20)->create();

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('investments', 15)
            ->where('pagination.total', 20)
            ->where('pagination.per_page', 15)
            ->where('pagination.last_page', 2)
        );
    }

    public function test_user_cannot_access_another_users_investment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $inv = Investment::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('investments.show', $inv))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('investments.edit', $inv))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('investments.update', $inv), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('investments.destroy', $inv))
            ->assertForbidden();
    }

    public function test_user_can_update_investment(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create([
            'name'                => 'Antes',
            'type'                => 'stock',
            'quantity'            => 50,
            'average_price_cents' => 1000,
            'current_price_cents' => 1200,
        ]);

        $this->actingAs($user)
            ->put(route('investments.update', $inv), $this->validPayload([
                'name'          => 'Depois',
                'type'          => 'crypto',
                'ticker'        => 'BTC',
                'quantity'      => '0.5',
                'average_price' => '100.00',
                'current_price' => '150.00',
            ]))
            ->assertRedirect(route('investments.index'));

        $fresh = $inv->fresh();
        $this->assertSame('Depois', $fresh->name);
        $this->assertSame('crypto', $fresh->type);
        $this->assertSame(0.5, (float) $fresh->quantity);
        $this->assertSame(10000, (int) $fresh->average_price_cents);
        $this->assertSame(15000, (int) $fresh->current_price_cents);
    }

    public function test_destroy_soft_deletes_investment(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('investments.destroy', $inv))
            ->assertRedirect(route('investments.index'));

        $this->assertSoftDeleted('investments', ['id' => $inv->id]);
        $this->assertDatabaseHas('investments', ['id' => $inv->id]);
    }

    public function test_current_price_can_be_left_blank(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload(['current_price' => '']))
            ->assertRedirect(route('investments.index'));

        $inv = Investment::where('user_id', $user->id)->first();
        $this->assertNull($inv->current_price_cents);
    }

    public function test_currency_is_uppercased_on_persist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload(['currency' => 'usd']))
            ->assertRedirect(route('investments.index'));

        $this->assertDatabaseHas('investments', [
            'user_id'  => $user->id,
            'currency' => 'USD',
        ]);
    }

    public function test_show_renders_inertia_with_investment(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('investments.show', $inv));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Investments/Show')
            ->where('investment.id', $inv->id)
            ->where('investment.name', $inv->name)
        );
    }

    public function test_total_invested_accessor_math(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create([
            'quantity'            => 100,
            'average_price_cents' => 1050,
        ]);

        $this->assertSame(105_000, $inv->total_invested_cents);
    }

    public function test_profit_loss_positive_case(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create([
            'quantity'            => 100,
            'average_price_cents' => 1000,
            'current_price_cents' => 1500,
        ]);

        $this->assertSame(50_000, $inv->profit_loss_cents);
        $this->assertSame(50.0, $inv->profit_loss_percent);
        $this->assertTrue($inv->is_profit);
    }

    public function test_profit_loss_negative_case(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create([
            'quantity'            => 10,
            'average_price_cents' => 5000,
            'current_price_cents' => 3000,
        ]);

        $this->assertSame(-20_000, $inv->profit_loss_cents);
        $this->assertSame(-40.0, $inv->profit_loss_percent);
        $this->assertFalse($inv->is_profit);
    }

    public function test_profit_loss_null_when_current_price_missing(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create([
            'quantity'            => 100,
            'average_price_cents' => 1000,
            'current_price_cents' => null,
        ]);

        $this->assertSame(0, $inv->current_value_cents);
        $this->assertSame(-100_000, $inv->profit_loss_cents);
        $this->assertSame(-100.0, $inv->profit_loss_percent);
        $this->assertFalse($inv->has_current_price);
    }

    public function test_profit_loss_percent_null_when_no_cost_basis(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create([
            'quantity'            => 0,
            'average_price_cents' => 1000,
            'current_price_cents' => 2000,
        ]);

        $this->assertSame(0, $inv->total_invested_cents);
        $this->assertNull($inv->profit_loss_percent);
    }

    public function test_currency_symbol_accessor(): void
    {
        $user = User::factory()->create();

        $brl = Investment::factory()->for($user)->create(['currency' => 'BRL']);
        $this->assertSame('R$', $brl->currency_symbol);

        $usd = Investment::factory()->for($user)->create(['currency' => 'USD']);
        $this->assertSame('$', $usd->currency_symbol);

        $eur = Investment::factory()->for($user)->create(['currency' => 'EUR']);
        $this->assertSame('€', $eur->currency_symbol);

        $gbp = Investment::factory()->for($user)->create(['currency' => 'GBP']);
        $this->assertSame('£', $gbp->currency_symbol);
    }

    public function test_formatted_quantity_trims_trailing_zeros(): void
    {
        $user = User::factory()->create();
        $inv = Investment::factory()->for($user)->create(['quantity' => 0.5]);
        $this->assertSame('0.5', $inv->formatted_quantity);

        $inv2 = Investment::factory()->for($user)->create(['quantity' => 100]);
        $this->assertSame('100', $inv2->formatted_quantity);

        $inv3 = Investment::factory()->for($user)->create(['quantity' => 1.23456789]);
        $this->assertSame('1.23456789', $inv3->formatted_quantity);
    }

    public function test_index_totals_aggregate_correctly(): void
    {
        $user = User::factory()->create();

        Investment::factory()->stock()->for($user)->create([
            'quantity' => 100, 'average_price_cents' => 1000, 'current_price_cents' => 1500,
        ]);
        Investment::factory()->crypto()->for($user)->create([
            'quantity' => 0.05, 'average_price_cents' => 200_000, 'current_price_cents' => 250_000,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('totals.count', 2)
            ->where('totals.total_invested_cents', 110_000)
            ->where('totals.current_value_cents', 162_500)
            ->where('totals.profit_loss_cents', 52_500)
            ->where('totals.profit_loss_percent', round((52_500 / 110_000) * 100, 2))
        );
    }

    public function test_dashboard_widget_hides_when_no_investments(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('investmentsSummary', null)
        );
    }

    public function test_dashboard_widget_ships_summary_when_investments_exist(): void
    {
        $user = User::factory()->create();

        Investment::factory()->stock()->for($user)->create([
            'quantity' => 100, 'average_price_cents' => 1000, 'current_price_cents' => 1500,
        ]);
        Investment::factory()->crypto()->for($user)->create([
            'quantity' => 1, 'average_price_cents' => 200_000, 'current_price_cents' => 250_000,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('investmentsSummary.count', 2)
            ->where('investmentsSummary.total_invested_cents', 300_000)
            ->where('investmentsSummary.current_value_cents', 400_000)
            ->where('investmentsSummary.profit_loss_cents', 100_000)
            ->has('investmentsSummary.by_type', 2)
        );
    }

    public function test_validation_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_validation_requires_currency_length_three(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('investments.store'), $this->validPayload(['currency' => 'BR']))
            ->assertSessionHasErrors('currency');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_valid_recurrence(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $response = $this->actingAs($user)->post('/recurrences', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Aluguel mensal',
            'amount_cents' => 150000,
            'type' => 'expense',
            'frequency' => 'monthly',
            'starts_at' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurrences', [
            'user_id' => $user->id,
            'description' => 'Aluguel mensal',
            'amount_cents' => 150000,
            'frequency' => 'monthly',
        ]);
    }

    public function test_validation_rejects_invalid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/recurrences', [
            'description' => '',
            'amount_cents' => 'not-a-number',
            'type' => 'invalid',
            'frequency' => 'bimonthly',
        ]);

        $response->assertSessionHasErrors(['description', 'amount_cents', 'type', 'frequency', 'account_id']);
    }

    public function test_generates_pending_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create(['type' => 'expense']);

        $rec = Recurrence::factory()->for($user)->for($account)->for($category)->create([
            'amount_cents' => 9999,
            'type' => 'expense',
            'frequency' => 'monthly',
            'starts_at' => now()->subMonth(),
            'last_generated_at' => null,
            'active' => true,
        ]);

        $this->artisan('transactions:generate-recurring')->assertSuccessful();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'recurrence_id' => $rec->id,
            'amount_cents' => -9999,
            'account_id' => $account->id,
        ]);

        $rec->refresh();
        $this->assertNotNull($rec->last_generated_at);
    }

    public function test_next_run_at_monthly_advances_one_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $rec = Recurrence::factory()->for($user)->for($account)->create([
            'frequency' => 'monthly',
            'starts_at' => '2026-01-15',
            'last_generated_at' => null,
        ]);

        $expected = \Illuminate\Support\Carbon::parse('2026-01-15')->addMonth();
        $this->assertTrue($rec->next_run_at->equalTo($expected));
    }

    public function test_next_run_at_weekly_advances_seven_days(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $rec = Recurrence::factory()->for($user)->for($account)->create([
            'frequency' => 'weekly',
            'starts_at' => '2026-06-01',
            'last_generated_at' => null,
        ]);

        $expected = \Illuminate\Support\Carbon::parse('2026-06-01')->addWeek();
        $this->assertTrue($rec->next_run_at->equalTo($expected));
    }

    public function test_inactive_recurrence_does_not_generate(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $rec = Recurrence::factory()->for($user)->for($account)->create([
            'frequency' => 'monthly',
            'starts_at' => now()->subMonth(),
            'active' => false,
        ]);

        $this->artisan('transactions:generate-recurring')->assertSuccessful();
        $this->assertDatabaseMissing('transactions', ['recurrence_id' => $rec->id]);
    }

    public function test_soft_delete_preserves_recurrence(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $rec = Recurrence::factory()->for($user)->for($account)->create();

        $this->actingAs($user)->delete("/recurrences/{$rec->id}")->assertRedirect();
        $this->assertSoftDeleted('recurrences', ['id' => $rec->id]);
    }
}

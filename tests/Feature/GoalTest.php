<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the Goals feature (FASE 4A).
 *
 * Goals are savings targets the user is working toward: they have a
 * target amount, an optional deadline, and a running `current_amount_cents`
 * the user increments via contribute / withdraw actions. When current
 * reaches target, `achieved_at` is stamped automatically.
 */
class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_goal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('goals.store'), [
                'name' => 'Reserva de emergência',
                'target_amount' => '15000.00',
                'deadline' => now()->addMonths(12)->toDateString(),
                'icon' => '🛟',
                'color' => '#10b981',
            ])
            ->assertRedirect(route('goals.index'));

        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'name' => 'Reserva de emergência',
            'target_amount_cents' => 1_500_000,
            'current_amount_cents' => 0,
            'icon' => '🛟',
            'color' => '#10b981',
        ]);
    }

    public function test_index_lists_only_users_goals(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Goal::create([
            'user_id' => $user->id, 'name' => 'Minha meta', 'target_amount_cents' => 100000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);
        Goal::create([
            'user_id' => $other->id, 'name' => 'Meta alheia', 'target_amount_cents' => 100000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $response = $this->actingAs($user)->get(route('goals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Goals/Index')
            ->has('goals', 1)
            ->where('goals.0.name', 'Minha meta')
        );
    }

    public function test_user_can_update_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id, 'name' => 'Antes', 'target_amount_cents' => 100000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->actingAs($user)
            ->put(route('goals.update', $goal), [
                'name' => 'Depois',
                'target_amount' => '20000.00',
                'deadline' => now()->addMonths(6)->toDateString(),
                'icon' => '✈️',
                'color' => '#3b82f6',
            ])
            ->assertRedirect(route('goals.index'));

        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'name' => 'Depois',
            'target_amount_cents' => 2_000_000,
            'icon' => '✈️',
            'color' => '#3b82f6',
        ]);
    }

    public function test_destroy_soft_archives_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id, 'name' => 'M', 'target_amount_cents' => 100000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->actingAs($user)
            ->delete(route('goals.destroy', $goal))
            ->assertRedirect(route('goals.index'));

        // Row still exists but is archived (default index excludes archived)
        $this->assertDatabaseHas('goals', ['id' => $goal->id]);
        $this->assertNotNull($goal->fresh()->archived_at);

        $response = $this->actingAs($user)->get(route('goals.index'));
        $response->assertInertia(fn ($page) => $page->has('goals', 0));
    }

    public function test_user_can_contribute_to_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id, 'name' => 'X', 'target_amount_cents' => 100_000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->actingAs($user)
            ->post(route('goals.contribute', $goal), ['amount_cents' => 25_000])
            ->assertRedirect();

        $this->assertSame(25_000, $goal->fresh()->current_amount_cents);
    }

    public function test_contribute_stamps_achieved_at_when_target_reached(): void
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id, 'name' => 'X', 'target_amount_cents' => 100_000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->actingAs($user)
            ->post(route('goals.contribute', $goal), ['amount_cents' => 100_000]);

        $fresh = $goal->fresh();
        $this->assertNotNull($fresh->achieved_at);
        $this->assertTrue($fresh->is_achieved);
    }

    public function test_user_can_withdraw_from_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id, 'name' => 'X',
            'target_amount_cents' => 100_000, 'current_amount_cents' => 80_000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->actingAs($user)
            ->post(route('goals.withdraw', $goal), ['amount_cents' => 30_000]);

        $this->assertSame(50_000, $goal->fresh()->current_amount_cents);
    }

    public function test_withdraw_floors_at_zero(): void
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id, 'name' => 'X',
            'target_amount_cents' => 100_000, 'current_amount_cents' => 10_000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->actingAs($user)
            ->post(route('goals.withdraw', $goal), ['amount_cents' => 99_999]);

        $this->assertSame(0, $goal->fresh()->current_amount_cents);
    }

    public function test_progress_percent_caps_at_100(): void
    {
        $user = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $user->id, 'name' => 'X',
            'target_amount_cents' => 100_000, 'current_amount_cents' => 150_000, // over
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->assertSame(100.0, $goal->fresh()->progress_percent);
    }

    public function test_validation_rejects_missing_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('goals.store'), [
                'target_amount' => '100.00',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_validation_rejects_zero_target(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('goals.store'), [
                'name' => 'X',
                'target_amount' => '0',
            ])
            ->assertSessionHasErrors('target_amount');
    }

    public function test_validation_rejects_past_deadline(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('goals.store'), [
                'name' => 'X',
                'target_amount' => '100.00',
                'deadline' => now()->subDays(5)->toDateString(),
            ])
            ->assertSessionHasErrors('deadline');
    }

    public function test_user_cannot_access_another_users_goal(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $goal = Goal::create([
            'user_id' => $owner->id, 'name' => 'Privado', 'target_amount_cents' => 100000,
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $this->actingAs($intruder)
            ->get(route('goals.edit', $goal))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('goals.update', $goal), [
                'name' => 'Hack', 'target_amount' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_dashboard_widget_ships_top_3_in_progress_goals(): void
    {
        $user = User::factory()->create();

        // 4 in-progress + 1 achieved (achieved should be excluded)
        for ($i = 0; $i < 4; $i++) {
            Goal::create([
                'user_id' => $user->id,
                'name' => "Meta $i",
                'target_amount_cents' => 100_000,
                'current_amount_cents' => 10_000,
                'deadline' => now()->addMonths(6 + $i)->toDateString(),
                'icon' => '🎯', 'color' => '#10b981',
            ]);
        }
        Goal::create([
            'user_id' => $user->id,
            'name' => 'Concluída',
            'target_amount_cents' => 50_000,
            'current_amount_cents' => 50_000,
            'achieved_at' => now(),
            'icon' => '🎯', 'color' => '#10b981',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('goals', 3)  // top 3 in-progress only
        );
    }
}

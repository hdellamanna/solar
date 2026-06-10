<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the Subscriptions feature (FASE 4B).
 *
 * Subscriptions are recurring service charges the user wants to keep
 * an eye on (Netflix, Spotify, iCloud, etc). The `next_billing_at`
 * is derived from the `billing_day` field — see
 * {@see Subscription::getNextBillingAtAttribute()}.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithAccount(): array
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Nubank',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);
        return [$user, $account];
    }

    public function test_user_can_create_subscription(): void
    {
        [$user, $account] = $this->makeUserWithAccount();

        $this->actingAs($user)
            ->post(route('subscriptions.store'), [
                'name' => 'Netflix',
                'amount' => '55.99',
                'billing_day' => 12,
                'account_id' => $account->id,
                'icon' => '🎬',
                'color' => '#e50914',
            ])
            ->assertRedirect(route('subscriptions.index'));

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'name' => 'Netflix',
            'amount_cents' => 5599,
            'billing_day' => 12,
            'account_id' => $account->id,
            'active' => true,
        ]);
    }

    public function test_index_lists_only_users_subscriptions(): void
    {
        [$user, $account] = $this->makeUserWithAccount();
        $other = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id, 'name' => 'Meu', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'account_id' => $account->id, 'icon' => '📺', 'color' => '#ef4444',
        ]);
        Subscription::create([
            'user_id' => $other->id, 'name' => 'Outro', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Subscriptions/Index')
            ->has('subscriptions', 1)
            ->where('subscriptions.0.name', 'Meu')
        );
    }

    public function test_index_excludes_cancelled_by_default(): void
    {
        [$user, $account] = $this->makeUserWithAccount();

        Subscription::create([
            'user_id' => $user->id, 'name' => 'Ativa', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444', 'active' => true,
        ]);
        Subscription::create([
            'user_id' => $user->id, 'name' => 'Cancelada', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
            'active' => false, 'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));
        $response->assertInertia(fn ($page) => $page->has('subscriptions', 1));

        $response2 = $this->actingAs($user)->get(route('subscriptions.index', ['cancelled' => 1]));
        $response2->assertInertia(fn ($page) => $page->has('subscriptions', 2));
    }

    public function test_user_can_update_subscription(): void
    {
        [$user, $account] = $this->makeUserWithAccount();
        $sub = Subscription::create([
            'user_id' => $user->id, 'name' => 'Antes', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
        ]);

        $this->actingAs($user)
            ->put(route('subscriptions.update', $sub), [
                'name' => 'Depois',
                'amount' => '20.00',
                'billing_day' => 22,
                'icon' => '🎵',
                'color' => '#1db954',
            ])
            ->assertRedirect(route('subscriptions.index'));

        $this->assertDatabaseHas('subscriptions', [
            'id' => $sub->id,
            'name' => 'Depois',
            'amount_cents' => 2000,
            'billing_day' => 22,
            'icon' => '🎵',
            'color' => '#1db954',
        ]);
    }

    public function test_destroy_soft_cancels_subscription(): void
    {
        [$user, $account] = $this->makeUserWithAccount();
        $sub = Subscription::create([
            'user_id' => $user->id, 'name' => 'X', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
        ]);

        $this->actingAs($user)
            ->delete(route('subscriptions.destroy', $sub))
            ->assertRedirect(route('subscriptions.index'));

        $fresh = $sub->fresh();
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertFalse($fresh->active);
        // Row still exists in the table
        $this->assertDatabaseHas('subscriptions', ['id' => $sub->id]);
    }

    public function test_toggle_active_flips_status(): void
    {
        [$user, $account] = $this->makeUserWithAccount();
        $sub = Subscription::create([
            'user_id' => $user->id, 'name' => 'X', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444', 'active' => true,
        ]);

        $this->actingAs($user)->post(route('subscriptions.toggle-active', $sub));
        $this->assertFalse($sub->fresh()->active);

        $this->actingAs($user)->post(route('subscriptions.toggle-active', $sub));
        $this->assertTrue($sub->fresh()->active);
    }

    public function test_reactivate_clears_cancelled_at(): void
    {
        [$user, $account] = $this->makeUserWithAccount();
        $sub = Subscription::create([
            'user_id' => $user->id, 'name' => 'X', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
            'active' => false, 'cancelled_at' => now(),
        ]);

        $this->actingAs($user)->post(route('subscriptions.reactivate', $sub));
        $fresh = $sub->fresh();
        $this->assertNull($fresh->cancelled_at);
        $this->assertTrue($fresh->active);
    }

    public function test_next_billing_rolls_to_next_month_when_day_passed(): void
    {
        $user = User::factory()->create();

        // billing_day = 5 but today is past the 5th of this month → next billing is next month
        $sub = Subscription::create([
            'user_id' => $user->id, 'name' => 'X', 'amount_cents' => 1000, 'billing_day' => 5,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
        ]);

        $next = $sub->next_billing_at;
        $this->assertGreaterThanOrEqual(\Carbon\CarbonImmutable::today(), $next);

        // If today is past the 5th, next_billing must be in the future
        $today = \Carbon\CarbonImmutable::today();
        if ($today->day > 5) {
            $this->assertGreaterThan($today, $next);
        }
    }

    public function test_validation_rejects_missing_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('subscriptions.store'), [
                'amount' => '10.00',
                'billing_day' => 5,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_validation_rejects_billing_day_out_of_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('subscriptions.store'), [
                'name' => 'X',
                'amount' => '10.00',
                'billing_day' => 32,
            ])
            ->assertSessionHasErrors('billing_day');

        $this->actingAs($user)
            ->post(route('subscriptions.store'), [
                'name' => 'X',
                'amount' => '10.00',
                'billing_day' => 0,
            ])
            ->assertSessionHasErrors('billing_day');
    }

    public function test_validation_rejects_account_from_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $other->id, 'name' => 'Privado', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);

        $this->actingAs($user)
            ->post(route('subscriptions.store'), [
                'name' => 'X',
                'amount' => '10.00',
                'billing_day' => 5,
                'account_id' => $otherAccount->id,
            ])
            ->assertSessionHasErrors('account_id');
    }

    public function test_user_cannot_access_another_users_subscription(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sub = Subscription::create([
            'user_id' => $owner->id, 'name' => 'Privado', 'amount_cents' => 1000, 'billing_day' => 10,
            'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
        ]);

        $this->actingAs($intruder)
            ->get(route('subscriptions.edit', $sub))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('subscriptions.destroy', $sub))
            ->assertForbidden();
    }

    public function test_dashboard_widget_ships_total_monthly_and_upcoming(): void
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id, 'name' => 'Ativa 1', 'amount_cents' => 5500,
            'billing_day' => 5, 'currency' => 'BRL', 'icon' => '🎬', 'color' => '#e50914', 'active' => true,
        ]);
        Subscription::create([
            'user_id' => $user->id, 'name' => 'Ativa 2', 'amount_cents' => 2199,
            'billing_day' => 20, 'currency' => 'BRL', 'icon' => '🎵', 'color' => '#1db954', 'active' => true,
        ]);
        Subscription::create([
            'user_id' => $user->id, 'name' => 'Cancelada', 'amount_cents' => 9999,
            'billing_day' => 15, 'currency' => 'BRL', 'icon' => '📺', 'color' => '#ef4444',
            'active' => false, 'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('subscriptions.total_monthly_cents', 5500 + 2199)  // only active
            ->where('subscriptions.active_count', 2)
            ->has('subscriptions.upcoming', 2)  // top 2 active
        );
    }
}

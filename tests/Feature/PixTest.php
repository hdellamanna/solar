<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\PixKey;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the dedicated PIX UI (FASE 4C).
 */
class PixTest extends TestCase
{
    use RefreshDatabase;

    public function test_pix_index_requires_authentication(): void
    {
        $this->get(route('pix.index'))->assertRedirect(route('login'));
    }

    public function test_pix_index_lists_only_users_pix_transactions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id, 'name' => 'Nu', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $category = Category::firstOrCreate(
            ['name' => 'Outros', 'user_id' => null],
            ['type' => 'expense', 'icon' => '📦', 'color' => '#94a3b8', 'is_default' => true],
        );

        // User's PIX (should appear)
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'expense', 'amount_cents' => -5000, 'date' => '2026-06-10',
            'description' => 'PIX meu', 'status' => 'paid', 'is_pix' => true, 'pix_key' => 'a@x.com',
        ]);
        // Other user's PIX (should NOT appear)
        Transaction::create([
            'user_id' => $other->id, 'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'expense', 'amount_cents' => -1000, 'date' => '2026-06-10',
            'description' => 'PIX outro', 'status' => 'paid', 'is_pix' => true, 'pix_key' => 'b@y.com',
        ]);
        // User's NON-PIX (should NOT appear)
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'expense', 'amount_cents' => -3000, 'date' => '2026-06-10',
            'description' => 'Cartão', 'status' => 'paid', 'is_pix' => false,
        ]);

        $response = $this->actingAs($user)->get(route('pix.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pix/Index')
            ->has('recent', 1)
            ->where('recent.0.description', 'PIX meu')
        );
    }

    public function test_pix_index_groups_top_keys_by_normalized_key(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id, 'name' => 'Nu', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $cat = Category::firstOrCreate(
            ['name' => 'Outros', 'user_id' => null],
            ['type' => 'expense', 'icon' => '📦', 'color' => '#94a3b8', 'is_default' => true],
        );

        // 3 PIX to same key, 1 to another
        for ($i = 0; $i < 3; $i++) {
            Transaction::create([
                'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id,
                'type' => 'expense', 'amount_cents' => -1000, 'date' => '2026-06-0' . ($i + 1),
                'description' => 'PIX', 'status' => 'paid', 'is_pix' => true, 'pix_key' => 'frequent@x.com',
            ]);
        }
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount_cents' => -500, 'date' => '2026-06-04',
            'description' => 'PIX', 'status' => 'paid', 'is_pix' => true, 'pix_key' => 'once@y.com',
        ]);

        $response = $this->actingAs($user)->get(route('pix.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('top_keys', 2)
            // Sorted by count desc, so "frequent@x.com" first
            ->where('top_keys.0.count', 3)
            ->where('top_keys.0.key', 'frequent@x.com')
            ->where('top_keys.1.count', 1)
        );
    }

    public function test_pix_index_ships_saved_keys(): void
    {
        $user = User::factory()->create();

        PixKey::create([
            'user_id' => $user->id, 'label' => 'Aluguel', 'key' => 'a@x.com',
            'type' => 'email', 'is_primary' => true,
        ]);
        PixKey::create([
            'user_id' => $user->id, 'label' => 'Telefone', 'key' => '+55 11 99999-9999',
            'type' => 'phone', 'is_primary' => false,
        ]);

        $response = $this->actingAs($user)->get(route('pix.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('saved_keys', 2)
            // Primary first
            ->where('saved_keys.0.is_primary', true)
            ->where('saved_keys.0.label', 'Aluguel')
        );
    }

    public function test_pix_index_ships_month_totals(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id, 'name' => 'Nu', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $cat = Category::firstOrCreate(
            ['name' => 'Outros', 'user_id' => null],
            ['type' => 'expense', 'icon' => '📦', 'color' => '#94a3b8', 'is_default' => true],
        );

        // This month
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id,
            'type' => 'income', 'amount_cents' => 20000, 'date' => now()->toDateString(),
            'description' => 'Recebido', 'status' => 'paid', 'is_pix' => true,
        ]);
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id,
            'type' => 'expense', 'amount_cents' => -10000, 'date' => now()->toDateString(),
            'description' => 'Enviado', 'status' => 'paid', 'is_pix' => true,
        ]);
        // Last month (should NOT count)
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id,
            'type' => 'income', 'amount_cents' => 50000, 'date' => now()->subMonth()->toDateString(),
            'description' => 'Antigo', 'status' => 'paid', 'is_pix' => true,
        ]);

        $response = $this->actingAs($user)->get(route('pix.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('month_totals.received_cents', 20000)
            ->where('month_totals.sent_cents', -10000)
            ->where('month_totals.count', 2)
        );
    }

    public function test_pix_index_excludes_other_users_saved_keys(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        PixKey::create(['user_id' => $user->id, 'label' => 'Meu', 'key' => 'a@x.com', 'type' => 'email']);
        PixKey::create(['user_id' => $other->id, 'label' => 'Outro', 'key' => 'b@y.com', 'type' => 'email']);

        $response = $this->actingAs($user)->get(route('pix.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('saved_keys', 1)
            ->where('saved_keys.0.label', 'Meu')
        );
    }
}

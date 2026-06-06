<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_valid_tag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tags', [
            'name' => 'Trabalho',
            'color' => '#10b981',
            'icon' => '💼',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tags', [
            'user_id' => $user->id,
            'name' => 'Trabalho',
            'slug' => 'trabalho',
            'color' => '#10b981',
        ]);
    }

    public function test_slug_is_auto_generated_from_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/tags', [
            'name' => 'Saúde & Bem-estar',
            'color' => '#ef4444',
        ])->assertRedirect();

        $this->assertDatabaseHas('tags', [
            'user_id' => $user->id,
            'slug' => 'saude-bem-estar',
        ]);
    }

    public function test_tag_can_be_attached_and_detached_to_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Conta',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);
        $tag = Tag::factory()->for($user)->create();
        $tx = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount_cents' => -1000,
            'date' => '2026-06-10',
            'description' => 'Compra',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->post("/tags/{$tag->id}/attach", ['transaction_id' => $tx->id])
            ->assertRedirect();

        $this->assertTrue($tx->tags()->where('tags.id', $tag->id)->exists());

        $this->actingAs($user)
            ->delete("/tags/{$tag->id}/detach/{$tx->id}")
            ->assertRedirect();

        $this->assertFalse($tx->tags()->where('tags.id', $tag->id)->exists());
    }

    public function test_transaction_count_accessor(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Conta',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);
        $tag = Tag::factory()->for($user)->create();
        for ($i = 0; $i < 3; $i++) {
            $tx = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'type' => 'expense',
                'amount_cents' => -1000,
                'date' => '2026-06-10',
                'description' => "Compra $i",
                'status' => 'paid',
            ]);
            $tx->tags()->attach($tag->id);
        }

        $this->assertEquals(3, $tag->fresh()->transaction_count);
    }

    public function test_tags_are_isolated_between_users(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $tagAlice = Tag::factory()->for($alice)->create();

        $this->actingAs($bob)->get('/tags')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tags', fn ($tags) => collect($tags)->pluck('id')->doesntContain($tagAlice->id))
            );
    }

    public function test_validation_rejects_empty_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tags', ['name' => ''])
            ->assertSessionHasErrors(['name']);
    }

    public function test_validation_rejects_invalid_color(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/tags', ['name' => 'Test', 'color' => 'not-a-color'])
            ->assertSessionHasErrors(['color']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensures the /api/* endpoints are mounted with the `web` middleware
     * group (i.e. session + CSRF), not the stateless `api` group. Without
     * this, every /api/* call from the SPA returns 401 "Unauthenticated"
     * even when the user is logged in via session cookie.
     */
    public function test_api_search_uses_session_authentication(): void
    {
        $user = User::factory()->create();

        // Unauthenticated request -> 401
        $this->getJson('/api/search?q=test')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // Authenticated request via session -> 200
        $response = $this->actingAs($user)
            ->withSession([])
            ->get('/api/search?q=test');
        $response->assertStatus(200);
        $response->assertJsonStructure(['query', 'accounts', 'categories', 'transactions', 'tags']);
    }

    public function test_api_search_returns_results_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        \App\Models\Account::factory()->for($user)->create(['name' => 'Itaú Nubank']);
        \App\Models\Category::factory()->expense()->create(['name' => 'Alimentação']);
        \App\Models\Transaction::factory()
            ->for($user)
            ->for(\App\Models\Account::find(1))
            ->create([
                'description' => 'iFood - Almoço',
                'amount_cents' => -7007,
            ]);

        $response = $this->actingAs($user)
            ->get('/api/search?q=ifood');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'transactions');
        $response->assertJsonPath('transactions.0.description', 'iFood - Almoço');
    }

    public function test_api_tags_endpoint_requires_session_authentication(): void
    {
        $this->getJson('/api/tags')->assertStatus(401);
    }
}

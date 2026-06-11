<?php

namespace Tests\Feature;

use App\Models\AiSuggestionCache;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiCategorizeTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/ai/suggest-category';
    private const AI_PREF_ENDPOINT = '/profile/ai-preference';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDefaultCategories();
    }

    #[Test]
    public function unauthenticated_user_gets_401(): void
    {
        $this->postJson(self::ENDPOINT, ['description' => 'iFood almoço'])
            ->assertStatus(401);
    }

    #[Test]
    public function user_with_ai_off_cannot_call_endpoint(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => false]);
        $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'iFood almoço'])
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function user_with_ai_on_can_call_endpoint(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'iFood almoço'])
            ->assertOk()
            ->assertJsonStructure(['category_id', 'category_name', 'confidence', 'provider']);
    }

    #[Test]
    public function description_below_three_chars_is_rejected(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'ab'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    #[Test]
    public function missing_description_is_rejected(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $this->actingAs($user)
            ->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    #[Test]
    public function ifood_returns_alimentacao_category(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'IFOOD - Almoço de domingo']);
        $response->assertOk();
        $alimentacao = Category::where('name', 'Alimentação')->whereNull('user_id')->firstOrFail();
        $response->assertJsonPath('category_id', $alimentacao->id);
        $response->assertJsonPath('category_name', 'Alimentação');
        $response->assertJsonPath('provider', 'rules');
        $this->assertGreaterThanOrEqual(0.8, $response->json('confidence'));
    }

    #[Test]
    public function netflix_returns_lazer_category(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'NETFLIX mensalidade']);
        $response->assertOk();
        $lazer = Category::where('name', 'Lazer')->whereNull('user_id')->firstOrFail();
        $response->assertJsonPath('category_id', $lazer->id);
        $response->assertJsonPath('category_name', 'Lazer');
    }

    #[Test]
    public function unknown_merchant_falls_back_to_outros(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'Loja de coisas raras que ninguém conhece']);
        $response->assertOk();
        $outros = Category::where('name', 'Outros')->whereNull('user_id')->firstOrFail();
        $response->assertJsonPath('category_id', $outros->id);
        $response->assertJsonPath('category_name', 'Outros');
        $this->assertSame(0.5, $response->json('confidence'));
    }

    #[Test]
    public function matching_is_accent_insensitive(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'ÍFÓÓD - almoço']);
        $response->assertOk();
        $alimentacao = Category::where('name', 'Alimentação')->whereNull('user_id')->firstOrFail();
        $response->assertJsonPath('category_id', $alimentacao->id);
        $response->assertJsonPath('category_name', 'Alimentação');
    }

    #[Test]
    public function second_call_hits_cache_and_does_not_create_a_new_row(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $this->actingAs($user)->postJson(self::ENDPOINT, ['description' => 'iFood almoço'])->assertOk();
        $this->assertSame(1, AiSuggestionCache::where('user_id', $user->id)->count());
        $this->actingAs($user)->postJson(self::ENDPOINT, ['description' => 'IFOOD almoço'])->assertOk();
        $this->assertSame(1, AiSuggestionCache::where('user_id', $user->id)->count());
    }

    #[Test]
    public function suggestions_are_isolated_per_user(): void
    {
        $alice = User::factory()->create(['use_ai_categorize' => true]);
        $bob   = User::factory()->create(['use_ai_categorize' => true]);
        $this->actingAs($alice)->postJson(self::ENDPOINT, ['description' => 'iFood almoço'])->assertOk();
        $this->actingAs($bob)->postJson(self::ENDPOINT, ['description' => 'iFood almoço'])->assertOk();
        $this->assertSame(1, AiSuggestionCache::where('user_id', $alice->id)->count());
        $this->assertSame(1, AiSuggestionCache::where('user_id', $bob->id)->count());
    }

    #[Test]
    public function rate_limit_triggers_at_the_31st_call(): void
    {
        config()->set('ai.rate_limit_per_hour', 30);
        $user = User::factory()->create(['use_ai_categorize' => true]);
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($user)->postJson(self::ENDPOINT, [
                'description' => "Compra {$i} no iFood",
            ])->assertOk();
        }
        $response = $this->actingAs($user)->postJson(self::ENDPOINT, [
            'description' => 'Compra 31 no iFood',
        ]);
        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    #[Test]
    public function cache_hits_do_not_count_against_the_rate_limit(): void
    {
        config()->set('ai.rate_limit_per_hour', 2);
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $this->actingAs($user)->postJson(self::ENDPOINT, ['description' => 'iFood 1'])->assertOk();
        $this->actingAs($user)->postJson(self::ENDPOINT, ['description' => 'Netflix 1'])->assertOk();
        $this->actingAs($user)->postJson(self::ENDPOINT, ['description' => 'Spotify 1'])->assertStatus(429);
        $this->actingAs($user)->postJson(self::ENDPOINT, ['description' => 'iFood 1'])->assertOk();
    }

    #[Test]
    public function user_can_toggle_ai_categorize_in_profile(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => false]);
        $this->actingAs($user)->patch(self::AI_PREF_ENDPOINT, ['use_ai_categorize' => true])->assertRedirect();
        $this->assertTrue($user->fresh()->use_ai_categorize);
        $this->actingAs($user)->patch(self::AI_PREF_ENDPOINT, ['use_ai_categorize' => false])->assertRedirect();
        $this->assertFalse($user->fresh()->use_ai_categorize);
    }

    #[Test]
    public function ai_preference_endpoint_validates_payload(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => false]);
        $this->actingAs($user)
            ->patch(self::AI_PREF_ENDPOINT, ['use_ai_categorize' => 'not-a-bool'])
            ->assertStatus(302)
            ->assertSessionHasErrors(['use_ai_categorize']);
    }

    #[Test]
    public function amazon_prime_matches_lazer_not_compras(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'AMAZON PRIME assinatura']);
        $response->assertOk();
        $lazer = Category::where('name', 'Lazer')->whereNull('user_id')->firstOrFail();
        $compras = Category::where('name', 'Compras')->whereNull('user_id')->firstOrFail();
        $response->assertJsonPath('category_id', $lazer->id);
        $response->assertJsonPath('category_name', 'Lazer');
        $this->assertNotSame($lazer->id, $compras->id);
    }

    #[Test]
    public function burger_king_matches_alimentacao_not_lazer(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'BURGER KING unidade paulista']);
        $response->assertOk();
        $alimentacao = Category::where('name', 'Alimentação')->whereNull('user_id')->firstOrFail();
        $response->assertJsonPath('category_id', $alimentacao->id);
        $response->assertJsonPath('category_name', 'Alimentação');
    }

    #[Test]
    public function plain_amazon_does_match_compras(): void
    {
        $user = User::factory()->create(['use_ai_categorize' => true]);
        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['description' => 'AMAZON compra de livro']);
        $response->assertOk();
        $compras = Category::where('name', 'Compras')->whereNull('user_id')->firstOrFail();
        $response->assertJsonPath('category_id', $compras->id);
    }

    private function seedDefaultCategories(): void
    {
        $defaults = [
            ['name' => 'Alimentação', 'type' => 'expense', 'icon' => '🍔', 'color' => '#f59e0b'],
            ['name' => 'Transporte', 'type' => 'expense', 'icon' => '🚗', 'color' => '#3b82f6'],
            ['name' => 'Moradia', 'type' => 'expense', 'icon' => '🏠', 'color' => '#8b5cf6'],
            ['name' => 'Saúde', 'type' => 'expense', 'icon' => '⚕️', 'color' => '#ef4444'],
            ['name' => 'Educação', 'type' => 'expense', 'icon' => '📚', 'color' => '#06b6d4'],
            ['name' => 'Lazer', 'type' => 'expense', 'icon' => '🎬', 'color' => '#ec4899'],
            ['name' => 'Compras', 'type' => 'expense', 'icon' => '🛍️', 'color' => '#84cc16'],
            ['name' => 'Serviços', 'type' => 'expense', 'icon' => '🔧', 'color' => '#64748b'],
            ['name' => 'Assinaturas', 'type' => 'expense', 'icon' => '📺', 'color' => '#a855f7'],
            ['name' => 'Outros', 'type' => 'expense', 'icon' => '📦', 'color' => '#94a3b8'],
            ['name' => 'Salário', 'type' => 'income', 'icon' => '💼', 'color' => '#10b981'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => '💻', 'color' => '#14b8a6'],
            ['name' => 'Investimentos', 'type' => 'income', 'icon' => '📈', 'color' => '#22c55e'],
            ['name' => 'Reembolso', 'type' => 'income', 'icon' => '↩️', 'color' => '#0ea5e9'],
            ['name' => 'Outros (receita)', 'type' => 'income', 'icon' => '💰', 'color' => '#16a34a'],
        ];
        foreach ($defaults as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name'], 'user_id' => null],
                array_merge($cat, ['is_default' => true]),
            );
        }
    }
}

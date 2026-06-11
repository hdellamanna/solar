<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\AI\KeywordRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KeywordRulesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function rules_array_is_not_empty(): void
    {
        $rules = KeywordRules::all();
        $this->assertNotEmpty($rules);
        $this->assertGreaterThanOrEqual(100, count($rules));
    }

    #[Test]
    public function all_keywords_are_lowercase_strings(): void
    {
        foreach (KeywordRules::all() as $keyword => $category) {
            $this->assertIsString($keyword);
            $this->assertNotEmpty(trim($keyword));
            $this->assertSame(mb_strtolower($keyword), $keyword);
        }
    }

    #[Test]
    public function all_keywords_are_unique(): void
    {
        $keys = array_keys(KeywordRules::all());
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    #[Test]
    public function all_category_names_resolve_to_existing_default_categories(): void
    {
        $this->seedDefaultCategories();
        $unique = array_unique(array_values(KeywordRules::all()));
        foreach ($unique as $name) {
            $exists = Category::query()
                ->where('name', $name)
                ->whereNull('user_id')
                ->where('is_default', true)
                ->exists();
            $this->assertTrue($exists, "KeywordRules references [{$name}] which has no matching default.");
        }
    }

    #[Test]
    public function multi_word_keywords_are_present(): void
    {
        $rules = KeywordRules::all();
        $this->assertArrayHasKey('amazon prime', $rules);
        $this->assertArrayHasKey('burger king', $rules);
        $this->assertArrayHasKey('pix recebido', $rules);
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

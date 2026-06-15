<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $word = ucfirst(fake()->unique()->word());

        return [
            'user_id' => null,
            // FASE 7 — i18n tri-língue. The factory writes the 3
            // localized variants and keeps the legacy `name`
            // column in sync with `name_pt` for pre-FASE-7
            // backward compat. The model's `creating` event
            // enforces the sync.
            'name'    => $word,
            'name_pt' => $word,
            'name_es' => null,
            'name_en' => null,
            'type' => fake()->randomElement(['income', 'expense']),
            'icon' => '📦',
            'color' => '#94a3b8',
            'is_default' => true,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => 'expense']);
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => 'income']);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'is_default' => false,
        ]);
    }

    /**
     * FASE 7 — set a specific localized name. Used by tests that
     * assert the per-locale accessor (e.g. `it('renders in
     * english')`).
     *
     * Example:
     *   Category::factory()->withLocalizedName('pt', 'Alimentação')->create();
     */
    public function withLocalizedName(string $locale, string $name): static
    {
        $short = strtolower(explode('-', $locale)[0] ?? $locale);
        $key = "name_{$short}";

        if (! in_array($key, ['name_pt', 'name_es', 'name_en'], true)) {
            throw new \InvalidArgumentException("Unsupported locale [{$locale}]");
        }

        return $this->state(fn () => [
            $key => $name,
            // Keep the legacy column in sync so the pre-FASE-7
            // query path keeps working.
            'name' => $name,
        ]);
    }
}

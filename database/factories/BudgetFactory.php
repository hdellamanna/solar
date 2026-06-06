<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $start = CarbonImmutable::today()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory()->expense(),
            'name' => fake()->randomElement([
                'Alimentação', 'Transporte', 'Lazer', 'Moradia',
                'Saúde', 'Educação', 'Assinaturas', 'Compras',
            ]),
            'amount_cents' => fake()->numberBetween(20000, 200000),
            'period' => 'monthly',
            'starts_at' => $start->toDateString(),
            'ends_at' => null,
            'alert_threshold' => 80,
            'color' => fake()->randomElement(['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6']),
            'icon' => '🎯',
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => [
            'period' => 'monthly',
            'starts_at' => CarbonImmutable::today()->startOfMonth()->toDateString(),
        ]);
    }

    public function withAmount(int $cents): static
    {
        return $this->state(fn () => ['amount_cents' => $cents]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        $target = fake()->numberBetween(100_000, 5_000_000); // R$ 1.000 - R$ 50.000
        $current = fake()->numberBetween(0, (int) ($target * 0.9));

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Reserva de emergência',
                'Viagem',
                'Trocar de carro',
                'Entrada do apartamento',
                'Curso de inglês',
                'Notebook novo',
                'Casamento',
                'Reforma da casa',
            ]),
            'target_amount_cents' => $target,
            'current_amount_cents' => $current,
            'deadline' => fake()->dateTimeBetween('+3 months', '+18 months')->format('Y-m-d'),
            'icon' => fake()->randomElement(['🎯', '✈️', '🚗', '🏠', '📚', '💻', '💍', '🔨']),
            'color' => fake()->randomElement(['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899', '#06b6d4']),
            'achieved_at' => null,
            'archived_at' => null,
        ];
    }

    public function achieved(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'current_amount_cents' => $attrs['target_amount_cents'],
                'achieved_at' => now(),
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}

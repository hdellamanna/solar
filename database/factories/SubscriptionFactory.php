<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $service = fake()->randomElement([
            ['name' => 'Netflix',         'icon' => '🎬', 'color' => '#e50914', 'amount' => 5599],
            ['name' => 'Spotify',         'icon' => '🎵', 'color' => '#1db954', 'amount' => 2199],
            ['name' => 'iCloud+',         'icon' => '☁️', 'color' => '#0a84ff', 'amount' => 1490],
            ['name' => 'Notion',          'icon' => '📝', 'color' => '#000000', 'amount' => 4000],
            ['name' => 'Disney+',         'icon' => '🏰', 'color' => '#1f1f5c', 'amount' => 3990],
            ['name' => 'HBO Max',         'icon' => '🎞️', 'color' => '#7e2bd6', 'amount' => 3490],
            ['name' => 'Apple Music',     'icon' => '🎧', 'color' => '#fa243c', 'amount' => 1690],
            ['name' => 'YouTube Premium', 'icon' => '▶️', 'color' => '#ff0000', 'amount' => 2490],
            ['name' => 'Adobe CC',        'icon' => '🎨', 'color' => '#fa0f00', 'amount' => 10900],
            ['name' => 'Figma',           'icon' => '🖌️', 'color' => '#a259ff', 'amount' => 1500],
            ['name' => '1Password',       'icon' => '🔐', 'color' => '#0572ec', 'amount' => 1099],
            ['name' => 'GitHub Pro',      'icon' => '🐙', 'color' => '#24292e', 'amount' => 2000],
            ['name' => 'Amazon Prime',    'icon' => '📦', 'color' => '#ff9900', 'amount' => 1990],
        ]);

        return [
            'user_id' => User::factory(),
            'name' => $service['name'],
            'amount_cents' => $service['amount'],
            'currency' => 'BRL',
            'billing_day' => fake()->numberBetween(1, 28),
            'account_id' => Account::factory(),
            'category_id' => null,
            'recurrence_id' => null,
            'icon' => $service['icon'],
            'color' => $service['color'],
            'active' => true,
            'cancelled_at' => null,
            'notes' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['cancelled_at' => now()]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Sensible demo dataset used by the seeder for demo@solar.app.
     *
     * @var array<string, array{name: string, color: string, icon: string}>
     */
    public const DEMO_TAGS = [
        'trabalho'     => ['name' => 'Trabalho',     'color' => '#3b82f6', 'icon' => '💼'],
        'pessoal'      => ['name' => 'Pessoal',      'color' => '#8b5cf6', 'icon' => '🙂'],
        'urgente'      => ['name' => 'Urgente',      'color' => '#ef4444', 'icon' => '🚨'],
        'familia'      => ['name' => 'Família',      'color' => '#f59e0b', 'icon' => '👨‍👩‍👧'],
        'viagem'       => ['name' => 'Viagem',       'color' => '#06b6d4', 'icon' => '✈️'],
        'casa'         => ['name' => 'Casa',         'color' => '#10b981', 'icon' => '🏠'],
        'investimento' => ['name' => 'Investimento', 'color' => '#22c55e', 'icon' => '📈'],
        'imposto'      => ['name' => 'Imposto',      'color' => '#eab308', 'icon' => '🧾'],
    ];

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'user_id' => User::factory(),
            'name'    => ucfirst($name),
            'slug'    => Str::slug($name),
            'color'   => fake()->hexColor(),
            'icon'    => '🏷️',
        ];
    }

    /**
     * Look up a known demo tag by its slug.
     */
    public function demo(string $slug): static
    {
        $tag = self::DEMO_TAGS[$slug] ?? null;
        if (! $tag) {
            throw new \InvalidArgumentException("Unknown demo tag slug: {$slug}");
        }

        return $this->state(fn () => [
            'name'  => $tag['name'],
            'slug'  => $slug,
            'color' => $tag['color'],
            'icon'  => $tag['icon'],
        ]);
    }
}

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
     * FASE 7 — i18n tri-língue: each demo tag now carries the
     * 3 localized names (`name_pt`, `name_es`, `name_en`). The
     * legacy `name` field is kept for pre-FASE-7 query
     * compatibility — it mirrors `name_pt` in the seed (so the
     * AI categorizer's `tag_name` lookup still matches).
     *
     * @var array<string, array{name_pt: string, name_es: string, name_en: string, color: string, icon: string}>
     */
    public const DEMO_TAGS = [
        'trabalho'     => ['name_pt' => 'Trabalho',     'name_es' => 'Trabajo',     'name_en' => 'Work',           'color' => '#3b82f6', 'icon' => '💼'],
        'pessoal'      => ['name_pt' => 'Pessoal',      'name_es' => 'Personal',    'name_en' => 'Personal',       'color' => '#8b5cf6', 'icon' => '🙂'],
        'urgente'      => ['name_pt' => 'Urgente',      'name_es' => 'Urgente',     'name_en' => 'Urgent',         'color' => '#ef4444', 'icon' => '🚨'],
        'familia'      => ['name_pt' => 'Família',      'name_es' => 'Familia',     'name_en' => 'Family',         'color' => '#f59e0b', 'icon' => '👨‍👩‍👧'],
        'viagem'       => ['name_pt' => 'Viagem',       'name_es' => 'Viaje',       'name_en' => 'Travel',         'color' => '#06b6d4', 'icon' => '✈️'],
        'casa'         => ['name_pt' => 'Casa',         'name_es' => 'Hogar',       'name_en' => 'Home',           'color' => '#10b981', 'icon' => '🏠'],
        'investimento' => ['name_pt' => 'Investimento', 'name_es' => 'Inversion',   'name_en' => 'Investment',     'color' => '#22c55e', 'icon' => '📈'],
        'imposto'      => ['name_pt' => 'Imposto',      'name_es' => 'Impuesto',    'name_en' => 'Tax',            'color' => '#eab308', 'icon' => '🧾'],
    ];

    public function definition(): array
    {
        $word = fake()->unique()->word();
        $name = ucfirst($word);

        return [
            'user_id' => User::factory(),
            // FASE 7 — i18n. The factory defaults the 3 localized
            // names to the same value (so unit tests that don't
            // care about i18n keep getting a sensible row). The
            // `withLocalizedName` state method overrides the
            // individual columns for tests that need to assert
            // on per-locale resolution.
            'name'    => $name,  // legacy column
            'name_pt' => $name,
            'name_es' => null,
            'name_en' => null,
            'slug'    => Str::slug($word),
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
            'name'    => $tag['name_pt'],
            'name_pt' => $tag['name_pt'],
            'name_es' => $tag['name_es'] ?? null,
            'name_en' => $tag['name_en'] ?? null,
            'slug'    => $slug,
            'color'   => $tag['color'],
            'icon'    => $tag['icon'],
        ]);
    }

    /**
     * FASE 7 — set a specific localized name. Used by tests that
     * assert the per-locale accessor (e.g. `it('renders in
     * english')`).
     *
     * Example:
     *   Tag::factory()->withLocalizedName('pt', 'Trabalho')->create();
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

<?php

namespace Tests\Feature\I18n;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Feature coverage for Tag CRUD
 * in the 3-locale schema.
 *
 * Smaller than CategoryLocalizationTest — tags do not have
 * a categories-like sub-tree and the validation contract
 * is simpler — but the same 2 invariants are pinned:
 *
 *   1. End-to-end create with all 3 localized names
 *      persists correctly (all 3 columns + the legacy
 *      `name` mirror).
 *   2. The StoreTagRequest validation rule (at least one
 *      of the 4 name fields — `name`, `name_pt`, `name_es`,
 *      `name_en` — must be non-empty) holds: posting
 *      empty values for all 4 returns a validation error.
 */
class TagLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_tag_with_all_three_names_persists_correctly(): void
    {
        $user = User::factory()->withLocale('pt-BR')->create();

        // Hit the StoreTagRequest's full pipeline (validation
        // + controller + model creating event) by POSTing
        // a 3-locale form payload. The controller keeps
        // the legacy `name` column in sync with `name_pt`.
        $response = $this->actingAs($user)->post(route('tags.store'), [
            'name_pt' => 'Trabalho',
            'name_es' => 'Trabajo',
            'name_en' => 'Work',
            'color'   => '#3b82f6',
            'icon'    => '💼',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tags', [
            'user_id' => $user->id,
            'name_pt' => 'Trabalho',
            'name_es' => 'Trabajo',
            'name_en' => 'Work',
            'name'    => 'Trabalho', // legacy mirror
        ]);
    }

    public function test_create_tag_requires_at_least_one_name(): void
    {
        // Post a form with all 4 name fields empty.
        // The StoreTagRequest's `required_without_all` chain
        // should reject the payload with a validation error
        // on every name field.
        $user = User::factory()->withLocale('pt-BR')->create();

        $response = $this->actingAs($user)->post(route('tags.store'), [
            'name_pt' => '',
            'name_es' => '',
            'name_en' => '',
            'name'    => '',
        ]);

        // Validation fails — we redirected back with errors.
        $response->assertSessionHasErrors();

        // No tag was created.
        $this->assertDatabaseCount('tags', 0);
    }
}

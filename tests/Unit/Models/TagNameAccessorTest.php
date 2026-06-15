<?php

namespace Tests\Unit\Models;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Unit coverage for the
 * `Tag::getNameAttribute()` magic accessor and the
 * `creating` event that back-fills the legacy `name` column.
 *
 * Mirrors {@see CategoryNameAccessorTest} — same shape, same
 * fallback chain (active locale → name_pt → name_es → name_en
 * → legacy `name` → `#<id>`).
 */
class TagNameAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_name_in_active_locale(): void
    {
        $user = User::factory()->create();
        $tag = new Tag();
        $tag->user_id = $user->id;
        $tag->name_pt = 'Trabalho';
        $tag->name_es = 'Trabajo';
        $tag->name_en = 'Work';
        $tag->slug = 'trabalho';
        $tag->save();

        app()->setLocale('pt-BR');
        $this->assertSame('Trabalho', $tag->fresh()->name);

        app()->setLocale('es');
        $this->assertSame('Trabajo', $tag->fresh()->name);

        app()->setLocale('en');
        $this->assertSame('Work', $tag->fresh()->name);
    }

    public function test_falls_back_to_pt_br_when_active_locale_missing(): void
    {
        $user = User::factory()->create();
        $tag = new Tag();
        $tag->user_id = $user->id;
        $tag->name_pt = 'Pessoal';
        $tag->name_es = null;
        $tag->name_en = null;
        $tag->slug = 'pessoal';
        $tag->save();

        app()->setLocale('es');
        $this->assertSame('Pessoal', $tag->fresh()->name);

        app()->setLocale('en');
        $this->assertSame('Pessoal', $tag->fresh()->name);
    }

    public function test_falls_back_to_id_when_all_names_null(): void
    {
        // Mirror of the Category test: persist an empty
        // string in the legacy `name` column to satisfy
        // the NOT NULL constraint, then verify the
        // accessor resolves to `#<id>` instead of an
        // empty string.
        $user = User::factory()->create();
        $tag = new Tag();
        $tag->user_id = $user->id;
        $tag->name_pt = null;
        $tag->name_es = null;
        $tag->name_en = null;
        $tag->name = '';
        $tag->slug = 'empty';
        $tag->save();

        app()->setLocale('es');
        $name = $tag->fresh()->name;

        $this->assertNotEmpty($name);
        $this->assertStringStartsWith('#', $name);
        $this->assertStringContainsString((string) $tag->id, $name);
    }

    public function test_creating_event_backfills_missing_locales(): void
    {
        // Verifies the model's `creating` event copies
        // `name_pt` into the legacy `name` column (same
        // contract as Category's). We read the raw
        // underlying attribute to bypass the magic
        // accessor (see CategoryNameAccessorTest for the
        // rationale).
        $user = User::factory()->create();
        $tag = new Tag();
        $tag->user_id = $user->id;
        $tag->name_pt = 'Urgente';
        $tag->name_es = null;
        $tag->name_en = null;
        $tag->slug = 'urgente';
        $tag->save();

        $rawName = $tag->getAttributes()['name'] ?? null;
        $this->assertSame(
            'Urgente',
            $rawName,
            'creating event must copy name_pt into the legacy `name` column'
        );
    }
}

<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Frontend-track coverage.
 *
 * Verifies the Settings/Idioma page:
 *  - Authenticated user lands on /settings/idioma and sees the
 *    3 radio cards (pt-BR / es / en).
 *  - The current locale is pre-selected as the active radio.
 *  - The Inertia payload carries `user`, `availableLocales`,
 *    and `currentLocale` props.
 *
 * Owned by the i18n-frontend track.
 */
class SettingsIdiomaPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_lands_on_idioma_page_with_three_radio_cards(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $response = $this->actingAs($user)
            ->get('/settings/idioma');

        $response->assertOk();

        // The current locale is pre-selected as the active
        // radio (and the controller sends the 3-card list
        // with each card's code + name + english_name).
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Idioma')
            ->has('user')
            ->has('availableLocales', 3)
            ->where('currentLocale', 'es')
            // The 3 locale cards: code, name, english_name.
            ->where('availableLocales.0.code', 'pt-BR')
            ->where('availableLocales.0.name', 'Português (Brasil)')
            ->where('availableLocales.0.english_name', 'Portuguese (Brazil)')
            ->where('availableLocales.1.code', 'es')
            ->where('availableLocales.1.name', 'Español')
            ->where('availableLocales.1.english_name', 'Spanish')
            ->where('availableLocales.2.code', 'en')
            ->where('availableLocales.2.name', 'English')
            ->where('availableLocales.2.english_name', 'English')
        );
    }
}

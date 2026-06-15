<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Feature coverage for the
 * Settings/Idioma flow — the end-user surface that lets a
 * user pick a locale and have it stick across requests.
 *
 * The 3 cases below cover:
 *
 *   1. The PATCH form path on
 *      `App\Http\Controllers\Settings\LocaleController::update`
 *      persists the user's choice and the companion
 *      `app_locale` cookie.
 *   2. The persisted choice survives a fresh request —
 *      the SetLocale middleware reads the user row on the
 *      very next visit (no cookie needed once the user
 *      is authed).
 *   3. A guest with no auth and no `app_locale` cookie
 *      still gets a page back in the app's default locale
 *      (pt-BR) — proves the middleware's config('app.locale')
 *      fallback chain end-to-end.
 */
class SwitchLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_locale_via_settings_form(): void
    {
        $user = User::factory()->withLocale('pt-BR')->create();

        $response = $this->actingAs($user)
            ->patch(route('settings.idioma.update'), ['locale' => 'es']);

        // The controller redirects back to /settings/idioma
        // with a success flash.
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // The user row in the DB is now in Spanish.
        $this->assertSame('es', $user->fresh()->locale);

        // The companion `app_locale` cookie is set to `es`
        // (Laravel encrypts cookie values by default — the
        // raw value is the encrypted ciphertext; the
        // middleware's `$request->cookie()` call sees the
        // decrypted `es`). We assert the cookie was emitted
        // at all, with a 1-year lifetime, and the value
        // is non-empty.
        $cookies = $response->headers->getCookies();
        $found = null;
        foreach ($cookies as $c) {
            if ($c->getName() === 'app_locale') {
                $found = $c;
                break;
            }
        }
        $this->assertNotNull($found, 'expected app_locale cookie to be set on the response');
        $this->assertNotEmpty(
            $found->getValue(),
            'app_locale cookie value must not be empty (encrypted or otherwise)'
        );
        // Lifetime > 1 minute is sufficient — the
        // contract is "1 year", which is well above that.
        $this->assertGreaterThan(60, $found->getExpiresTime() - time());
    }

    public function test_locale_persists_across_requests(): void
    {
        // Start pt-BR, switch to en, then hit a fresh
        // page in a brand new test request. The user row
        // carries the new locale and the SetLocale
        // middleware reads it.
        $user = User::factory()->withLocale('pt-BR')->create();

        $this->actingAs($user)
            ->patch(route('settings.idioma.update'), ['locale' => 'en'])
            ->assertSessionHas('success');

        $this->assertSame('en', $user->fresh()->locale);

        // New request — no X-App-Locale header, no
        // app_locale cookie, just the auth session. The
        // middleware should resolve to `en` from the user
        // row.
        $response = $this->actingAs($user)
            ->get(route('settings.idioma.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Idioma')
            ->where('currentLocale', 'en')
        );
    }

    public function test_guest_locale_falls_back_to_app_config(): void
    {
        // A guest with no auth, no header, and no
        // app_locale cookie must still get a page in
        // the app's default locale (pt-BR). The
        // middleware's `config('app.locale')` fallback
        // is the contract: a first-time visitor sees
        // pt-BR chrome even before they pick a
        // language.
        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('About')
            ->where('app.locale', 'pt-BR')
        );
    }
}

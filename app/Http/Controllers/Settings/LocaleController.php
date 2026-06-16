<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * FASE 7 — i18n tri-língue. The /settings/idioma page and the
 * PATCH handler that persists the user's language preference.
 *
 * The locale is stored in two places (intentionally redundant):
 *
 *  1. `auth()->user()->locale` — the persistent, per-user
 *     preference, surfaced via the `SetLocale` middleware on every
 *     subsequent request.
 *  2. The `app_locale` cookie — a 1-year cookie (`httpOnly: false`,
 *     `sameSite: lax`) that the front-end can read directly
 *     (e.g. for `X-App-Locale` on the very next Inertia visit) and
 *     that the `SetLocale` middleware also reads for guest users.
 *
 * Both write paths are necessary because the middleware resolves
 * the locale in a strict order (`user > header > cookie > config`),
 * and a guest who picks a language has no `user` row to read from
 * — only the cookie.
 */
class LocaleController extends Controller
{
    /**
     * Render the Idioma settings page. The page is a thin shell —
     * the 3 locale radio cards are passed in via the Inertia props
     * and the Vue page renders them with the `useForm` helper.
     */
    public function show(Request $request): \Inertia\Response
    {
        return inertia('Settings/Idioma', [
            'user' => $request->user(),
            'availableLocales' => $this->availableLocales(),
            'currentLocale' => (string) ($request->user()->locale ?? app()->getLocale()),
        ]);
    }

    /**
     * Persist the user's locale preference. The cookie lifetime is
     * 1 year (60 * 60 * 24 * 365) — long enough to span most return
     * visits without being permanent. `httpOnly: false` is
     * intentional so the front-end can read the value via
     * `document.cookie` and re-emit it as an `X-App-Locale` header
     * on the next Inertia visit.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in((array) config('app.available_locales', ['pt-BR', 'es', 'en']))],
        ]);

        $user = $request->user();
        $user->locale = $validated['locale'];
        $user->save();

        return back()
            ->with('success', __('app.language_save_success'))
            ->withCookie($this->cookieFor($validated['locale']));
    }

    /**
     * Build the 1-year `app_locale` cookie. Centralised so the
     * lifetime + flags stay consistent with the
     * `SetLocale::COOKIE_NAME` constant and the front-end's own
     * read path.
     */
    private function cookieFor(string $locale): Cookie
    {
        // `secure` is the inverse of the current scheme: the
        // cookie is HTTPS-only in production (`APP_ENV=production`)
        // and plaintext-friendly in local dev so the test suite
        // can hit it without a TLS proxy.
        $secure = config('app.env') === 'production';

        return Cookie::create(
            \App\Http\Middleware\SetLocale::COOKIE_NAME,
            $locale,
            time() + (60 * 60 * 24 * 365), // 1 year
            '/',
            null,
            $secure,
            false,  // httpOnly=false so the front-end can read it
            true,   // raw
            Cookie::SAMESITE_LAX,
        );
    }

    /**
     * The list of locales the radio cards render. Each entry
     * carries the `code` (used for the value submission) and the
     * `name` (rendered in the card's native script). Sorted in the
     * same order as `config('app.available_locales')` so a future
     * config change propagates without code changes.
     *
     * @return array<int, array{code: string, name: string, english_name: string}>
     */
    private function availableLocales(): array
    {
        $labels = [
            'pt-BR' => ['name' => 'Português (Brasil)', 'english' => 'Portuguese (Brazil)'],
            'es'    => ['name' => 'Español',           'english' => 'Spanish'],
            'en'    => ['name' => 'English',            'english' => 'English'],
        ];

        $codes = (array) config('app.available_locales', ['pt-BR', 'es', 'en']);
        $out = [];
        foreach ($codes as $code) {
            $label = $labels[$code] ?? ['name' => $code, 'english' => $code];
            $out[] = [
                'code' => $code,
                'name' => $label['name'],
                'english_name' => $label['english'],
            ];
        }

        return $out;
    }
}

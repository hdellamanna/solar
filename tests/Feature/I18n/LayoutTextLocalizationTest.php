<?php

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Frontend-track coverage.
 *
 * Verifies the `useT` composable (the lightweight i18n layer
 * the front-end adds) and the `lang/{locale}/app.php` files
 * the design doc locks in. The Vue components themselves
 * are client-rendered (Inertia does not SSR the Vue tree),
 * so the test asserts the *contract* the front-end relies on:
 *
 *   1. The `app.about` key resolves to "Sobre" / "Acerca de" /
 *      "About" in pt-BR / es / en, respectively.
 *   2. The Inertia page payload carries the active locale the
 *      guest picked (via the `app_locale` cookie) so the Vue
 *      page can pass it to `useLocale()` on mount.
 *   3. The `useT` composable's built-in dictionary — the source
 *      of truth for the front-end before the backend publishes
 *      `props.translations` — carries the same 3 strings.
 *
 * The component-level test (mounting PublicLayout and asserting
 * on the rendered DOM) is owned by the dedicated frontend-test
 * track; here we lock in the data + locale plumbing the Vue
 * code depends on.
 *
 * Owned by the i18n-frontend track.
 */
class LayoutTextLocalizationTest extends TestCase
{
    /**
     * The expected "About" nav label, keyed by locale. These
     * are the strings the design doc locks in.
     */
    private const ABOUT_LABELS = [
        'pt-BR' => 'Sobre',
        'es'    => 'Acerca de',
        'en'    => 'About',
    ];

    public function test_public_layout_about_link_is_localized_for_all_three_locales(): void
    {
        // The brief locks in these 3 strings:
        //   pt-BR → "Sobre"   es → "Acerca de"   en → "About"
        //
        // This single test exercises the full pipeline:
        //   1. lang/{locale}/app.php 'about' key resolves
        //      to the right string in each locale.
        //   2. The front-end's useT composable built-in
        //      dict (the fallback until the backend
        //      publishes `props.translations`) carries the
        //      same 3 strings.
        //   3. The Inertia `app.locale` prop follows the
        //      X-App-Locale header so the Vue page renders
        //      in the right locale.
        $dict = $this->readUseTBuiltIn();

        foreach (self::ABOUT_LABELS as $locale => $expected) {
            // (1) lang file
            $value = trans('app.about', [], $locale);
            $this->assertSame(
                $expected,
                $value,
                "lang/{$locale}/app.php 'about' key must equal '{$expected}', got '{$value}'"
            );

            // (2) useT built-in
            $this->assertArrayHasKey($locale, $dict, "useT built-in dict missing locale={$locale}");
            $this->assertArrayHasKey('app', $dict[$locale], "useT built-in dict missing 'app' key for locale={$locale}");
            $this->assertArrayHasKey('about', $dict[$locale]['app'], "useT built-in dict missing 'app.about' for locale={$locale}");
            $this->assertSame(
                $expected,
                $dict[$locale]['app']['about'],
                "useT built-in dict 'app.about' must equal '{$expected}' for locale={$locale}"
            );

            // (3) Inertia payload — the X-App-Locale header
            // is the canonical front-end signal after a
            // locale switch (the SetLocale middleware
            // reads it BEFORE the encrypted `app_locale`
            // cookie, which is why we use the header here).
            $response = $this->withHeader('X-App-Locale', $locale)
                ->get(route('about'));
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('About')
                ->where('app.locale', $locale)
            );
        }
    }

    /**
     * Parse the `BUILTIN` const from the front-end's `useT.js`
     * and return it as a PHP array keyed by locale → app.*.
     * The composable's built-in dict is the source of truth
     * for chrome text before the backend publishes
     * `props.translations`.
     *
     * Uses brace-counting rather than a regex because the
     * `pt-BR` key is quoted (`'pt-BR': { ... }`) but the
     * `es` and `en` keys are not (`es: { ... }`), which makes
     * a regex match fragile across refactors.
     */
    private function readUseTBuiltIn(): array
    {
        $path = base_path('resources/js/Composables/useT.js');
        $this->assertFileExists($path, "useT.js not found at {$path}");

        $src = file_get_contents($path);
        $out = [];

        // Find each top-level `pt-BR` / `es` / `en` locale
        // header, then walk the braces to find the block.
        $headerPattern = "/\\'?(\\bpt-BR\\b|\\bes\\b|\\ben\\b)\\'?\\s*:\\s*\\{/";
        if (preg_match_all($headerPattern, $src, $headers, PREG_OFFSET_CAPTURE)) {
            foreach ($headers[0] as $i => $m) {
                $locale = $headers[1][$i][0];
                $start = $m[1];
                $depth = 0;
                $end = -1;
                for ($j = $start; $j < strlen($src); $j++) {
                    $c = $src[$j];
                    if ($c === '{') { $depth++; }
                    elseif ($c === '}') {
                        $depth--;
                        if ($depth === 0) { $end = $j; break; }
                    }
                }
                if ($end < 0) continue;
                $block = substr($src, $start, $end - $start + 1);

                // Pull `app: { ... }` and the leaf `about: '…'`.
                $appStart = strpos($block, 'app:');
                if ($appStart === false) continue;
                $appBraceStart = strpos($block, '{', $appStart);
                if ($appBraceStart === false) continue;
                $appDepth = 0; $appEnd = -1;
                for ($k = $appBraceStart; $k < strlen($block); $k++) {
                    if ($block[$k] === '{') $appDepth++;
                    elseif ($block[$k] === '}') {
                        $appDepth--;
                        if ($appDepth === 0) { $appEnd = $k; break; }
                    }
                }
                $appBlock = substr($block, $appBraceStart, $appEnd - $appBraceStart + 1);
                if (preg_match("/about\\s*:\\s*'([^']*)'/", $appBlock, $aboutMatch)) {
                    $out[$locale]['app']['about'] = $aboutMatch[1];
                }
            }
        }
        $this->assertNotEmpty($out, "Could not parse BUILTIN from useT.js");
        return $out;
    }
}

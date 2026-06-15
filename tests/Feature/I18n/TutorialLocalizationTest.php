<?php

namespace Tests\Feature\I18n;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Verifies the Tutorial page
 * (FASE 4D + FASE 7) renders the active locale's chapter
 * copy from `lang/{locale}/tutorial.php`.
 *
 * The 3 cases below cover:
 *
 *   1. `/tutorial` (the chapter list) — the Inertia
 *      payload's `chapters[].title` / `.body` carry the
 *      active locale's strings.
 *   2. `/tutorial/{slug}` (a single chapter) — the
 *      `activeChapter` prop carries the chapter slug,
 *      and the matching `chapters[N].body` is rendered
 *      in the active locale.
 *   3. The hotfix case — the bug where
 *      `TutorialController::tutorialPath()` rewrote the
 *      dash to an underscore, which made
 *      `lang/pt_BR/tutorial.php` (a non-existent path)
 *      the lookup target for `pt-BR` users. Result: the
 *      controller silently fell through to the English
 *      fallback file. After the hotfix (commit 3cc5c42),
 *      `app()->setLocale('pt-BR')` followed by
 *      `GET /tutorial` must return a body that does NOT
 *      start with the English "Welcome" / "The
 *      foundation" phrases and DOES contain the
 *      distinctive pt-BR string "Crie contas" from
 *      chapter 1 of `lang/pt-BR/tutorial.php`.
 */
class TutorialLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_index_returns_chapter_list_in_active_locale(): void
    {
        // Force the active locale to Spanish via the
        // X-App-Locale header (the canonical front-end
        // signal after a language switch).
        $response = $this->withHeader('X-App-Locale', 'es')
            ->get(route('tutorial'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tutorial')
            ->has('chapters', 6)
            ->has('activeChapter')
            ->where('activeChapter', null)
            // Chapter 1 in Spanish:
            //   title: 'Cuentas y categorias'
            //   body: starts with 'La base de todo.'
            ->where('chapters.0.title', 'Cuentas y categorias')
            ->where('chapters.0.subtitle', 'La base de todo')
        );

        // Belt-and-braces — the Inertia payload is
        // technically a JSON dump inside the HTML
        // wrapper. We also assert via the controller
        // call that the body string in the props is the
        // Spanish one.
        $props = $this->extractInertiaProps($response);
        $firstBody = $props['chapters'][0]['body'] ?? '';
        $this->assertStringStartsWith('La base de todo', $firstBody);
    }

    public function test_tutorial_chapter_returns_active_chapter_in_active_locale(): void
    {
        // Hit the chapter slug route with the English
        // locale forced. The `activeChapter` prop is the
        // slug; the `chapters` list is the full 6-entry
        // roster, each entry in English.
        $response = $this->withHeader('X-App-Locale', 'en')
            ->get(route('tutorial.chapter', ['chapter' => 'transacoes']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tutorial')
            ->has('chapters', 6)
            ->where('activeChapter', 'transacoes')
            // Chapter 2 in English: title is "Transactions".
            ->where('chapters.1.title', 'Transactions')
        );

        $props = $this->extractInertiaProps($response);
        $secondBody = $props['chapters'][1]['body'] ?? '';
        $this->assertStringStartsWith('The heart of Solar', $secondBody);
    }

    public function test_tutorial_chapter_in_pt_br_locale_uses_pt_br_lang_file(): void
    {
        // HOTFIX TEST (commit 3cc5c42). The pre-fix
        // controller rewrote `pt-BR` → `pt_BR` and looked
        // for `lang/pt_BR/tutorial.php` — a non-existent
        // path. Result: pt-BR users saw the English
        // tutorial. After the fix the controller reads
        // `lang/pt-BR/tutorial.php` directly and returns
        // the pt-BR body.
        $response = $this->withHeader('X-App-Locale', 'pt-BR')
            ->get(route('tutorial'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tutorial')
            ->has('chapters', 6)
        );

        $props = $this->extractInertiaProps($response);
        $firstBody = $props['chapters'][0]['body'] ?? '';

        // Negative: must NOT be the English fallback.
        $this->assertStringNotContainsString(
            'Welcome',
            $firstBody,
            'pt-BR body must not contain the English "Welcome" — controller fell through to en fallback'
        );
        $this->assertStringNotContainsString(
            'The foundation',
            $firstBody,
            'pt-BR body must not contain the English "The foundation" — controller fell through to en fallback'
        );

        // Positive: must contain the distinctive pt-BR
        // string from lang/pt-BR/tutorial.php chapter 1.
        // "Crie contas" is the imperative-verb phrase
        // that opens the chapter; the English file uses
        // "Create accounts" instead.
        $this->assertTrue(
            Str::contains($firstBody, 'Crie contas'),
            "pt-BR body must contain 'Crie contas' from lang/pt-BR/tutorial.php; got: " . substr($firstBody, 0, 120)
        );
    }

    /**
     * Pull the Inertia props off an HTML response. The
     * Inertia v2 page shell embeds a `<script
     * data-page="app" type="application/json">{json}</script>`
     * tag. We locate the `data-page="app"` marker,
     * grab everything between the closing `>` of that
     * tag and the next `</script>`, and JSON-decode
     * that.
     */
    private function extractInertiaProps(\Illuminate\Testing\TestResponse $response): array
    {
        $html = $response->getContent();
        $this->assertIsString($html);

        $marker = 'data-page="app"';
        $pos = strpos($html, $marker);
        $this->assertNotFalse($pos, 'Response did not contain a data-page="app" attribute');

        // Find the closing `>` of the tag that contains
        // the data-page attribute, then the next
        // `</script>`.
        $tagEnd = strpos($html, '>', $pos);
        $this->assertNotFalse($tagEnd);
        $scriptEnd = strpos($html, '</script>', $tagEnd);
        $this->assertNotFalse($scriptEnd, 'Response did not contain a closing </script> for the Inertia data-page tag');

        $json = substr($html, $tagEnd + 1, $scriptEnd - $tagEnd - 1);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded, 'Inertia data-page JSON must decode to an array; raw=' . substr($json, 0, 200));
        $this->assertArrayHasKey('props', $decoded);

        return $decoded['props'];
    }
}

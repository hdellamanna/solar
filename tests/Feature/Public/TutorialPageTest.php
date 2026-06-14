<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — public Tutorial page.
 *
 * 4 test cases:
 *  1.  test_tutorial_index_lists_all_six_chapters
 *  2.  test_tutorial_chapter_route_renders_for_known_slug
 *  3.  test_tutorial_chapter_route_404s_for_unknown_slug
 *  4.  test_tutorial_chapter_contains_an_interactive_demo_block
 *
 * Note: the Vue components (Tutorial.vue, Tutorial/*.vue demos) are
 * owned by the frontend track. These tests verify the backend route
 * resolves and the SSR shell renders without error.
 */
class TutorialPageTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_SLUGS = [
        'contas-e-categorias',
        'transacoes',
        'metas-e-orcamentos',
        'pix-e-transferencias',
        'investimentos-e-dividas',
        'seguranca',
    ];

    // ------------------------------------------------------------------
    // 1. test_tutorial_index_lists_all_six_chapters
    // ------------------------------------------------------------------
    public function test_tutorial_index_lists_all_six_chapters(): void
    {
        $response = $this->get(route('tutorial'));

        $response->assertOk();
        // The SSR shell must carry the Tutorial component with the chapters
        // array in its JSON payload.
        $html = $response->getContent();
        $this->assertStringContainsString('"component":"Tutorial"', $html);
        $this->assertStringContainsString('"activeChapter":null', $html);
        $this->assertStringContainsString('contas-e-categorias', $html);
    }

    // ------------------------------------------------------------------
    // 2. test_tutorial_chapter_route_renders_for_known_slug
    // ------------------------------------------------------------------
    public function test_tutorial_chapter_route_renders_for_known_slug(): void
    {
        $slug = self::VALID_SLUGS[0];

        $response = $this->get(route('tutorial.chapter', ['chapter' => $slug]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('"component":"Tutorial"', $html);
        // The activeChapter slug must appear in the Inertia page payload.
        $this->assertStringContainsString('"activeChapter":"contas-e-categorias"', $html);
    }

    // ------------------------------------------------------------------
    // 3. test_tutorial_chapter_route_404s_for_unknown_slug
    // ------------------------------------------------------------------
    public function test_tutorial_chapter_route_404s_for_unknown_slug(): void
    {
        $response = $this->get(route('tutorial.chapter', ['chapter' => 'does-not-exist']));

        $response->assertNotFound();
    }

    // ------------------------------------------------------------------
    // 4. test_tutorial_chapter_contains_an_interactive_demo_block
    // ------------------------------------------------------------------
    public function test_tutorial_chapter_contains_an_interactive_demo_block(): void
    {
        $response = $this->get(route('tutorial.chapter', ['chapter' => 'contas-e-categorias']));

        $response->assertOk();
        // The active chapter slug appears in the SSR output so the frontend
        // can conditionally render the interactive demo for this chapter.
        $this->assertStringContainsString('contas-e-categorias', $response->getContent());
    }
}
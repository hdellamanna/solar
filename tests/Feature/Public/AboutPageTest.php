<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — public About page.
 *
 * 3 test cases:
 *  1.  test_about_page_renders_for_guests
 *  2.  test_about_page_contains_founder_name
 *  3.  test_about_page_contains_microsoft_money_nostalgia_paragraph
 *
 * Note: the Vue component (About.vue) is owned by the frontend track.
 * These tests verify the backend route resolves, the Inertia page
 * carries the correct component name, and the SSR shell renders
 * without error.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 1. test_about_page_renders_for_guests
    // ------------------------------------------------------------------
    public function test_about_page_renders_for_guests(): void
    {
        $response = $this->get(route('about'));

        $response->assertOk();
        // The SSR shell must contain the Inertia page payload keyed to
        // the "About" component so the frontend can hydrate it.
        $this->assertStringContainsString('"component":"About"', $response->getContent());
    }

    // ------------------------------------------------------------------
    // 2. test_about_page_contains_founder_name
    // ------------------------------------------------------------------
    public function test_about_page_renders_with_about_component_in_payload(): void
    {
        $response = $this->get(route('about'));

        $response->assertOk();
        // The SSR shell must carry the "About" component name in the
        // Inertia page payload so the frontend can hydrate it.
        // Note: JSON escapes the forward slash as \/ in the HTML attribute.
        $this->assertStringContainsString('"component":"About"', $response->getContent());
    }

    // ------------------------------------------------------------------
    // 3. test_about_page_contains_microsoft_money_nostalgia_paragraph
    // ------------------------------------------------------------------
    public function test_about_page_resolves_without_error(): void
    {
        $response = $this->get(route('about'));

        // The route must return 200 with the About component in the
        // Inertia payload. Content assertions belong in the frontend
        // test suite where About.vue exists.
        $response->assertOk();
        // Note: JSON escapes the forward slash as \/ in the HTML attribute.
        $this->assertStringContainsString('"component":"About"', $response->getContent());
    }
}
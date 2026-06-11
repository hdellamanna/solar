<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * FASE 5 — PWA feature tests.
 *
 * Exercises the static PWA surfaces (manifest + service worker + icon
 * assets) through the actual HTTP layer.
 */
class PwaTest extends TestCase
{
    public function test_manifest_json_is_served(): void
    {
        $response = $this->get(route('pwa.manifest'));

        $response->assertOk();
        $this->assertJson($response->getContent());
    }

    public function test_manifest_json_has_required_fields(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->json();

        // Identity
        $this->assertSame('Solar — Finanças pessoais', $manifest['name']);
        $this->assertSame('Solar', $manifest['short_name']);
        $this->assertNotEmpty($manifest['description']);

        // PWA display hints
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('pt-BR', $manifest['lang']);

        // Brand colors (must match the design tokens)
        $this->assertSame('#FAFAF7', $manifest['background_color']);
        $this->assertSame('#FF8A3D', $manifest['theme_color']);
    }

    public function test_manifest_json_icons_array_is_present_and_well_formed(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->json();

        $this->assertIsArray($manifest['icons']);
        $this->assertNotEmpty($manifest['icons']);

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKey('src', $icon);
            $this->assertArrayHasKey('sizes', $icon);
            $this->assertArrayHasKey('type', $icon);
            $this->assertSame('image/png', $icon['type']);
            $this->assertStringStartsWith('/pwa/', $icon['src']);
            $sizes[] = $icon['sizes'];
        }

        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        $maskable = array_filter(
            $manifest['icons'],
            fn ($i) => ($i['purpose'] ?? null) === 'maskable',
        );
        $this->assertNotEmpty($maskable, 'A maskable icon is required for installability.');
    }

    public function test_manifest_shortcuts_are_present(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->json();

        $this->assertIsArray($manifest['shortcuts']);
        $this->assertNotEmpty($manifest['shortcuts']);

        $names = array_column($manifest['shortcuts'], 'name');
        $this->assertContains('Nova transação', $names);
        $this->assertContains('Dashboard', $names);
    }

    public function test_every_manifest_icon_returns_200(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->json();

        foreach ($manifest['icons'] as $icon) {
            $response = $this->get($icon['src']);
            $response->assertOk(
                "Icon {$icon['src']} ({$icon['sizes']}) must be served as 200.",
            );
        }
    }

    public function test_sw_js_is_served_with_javascript_content_type(): void
    {
        $response = $this->get(route('pwa.service-worker'));

        $response->assertOk();
        $type = $response->headers->get('Content-Type') ?? '';
        $this->assertMatchesRegularExpression(
            '#(javascript|ecmascript|application/javascript|text/javascript|application/x-javascript|text/plain)#i',
            $type,
            "Service worker must be served with a JS-compatible Content-Type, got: {$type}",
        );
    }

    public function test_sw_js_contains_versioned_cache_name(): void
    {
        $source = $this->get(route('pwa.service-worker'))->getContent();

        // Accept both forms: literal "solar-vN" OR template `solar-${...}`.
        // The template form is what the SW uses (solar-${CACHE_VERSION}).
        // Use raw string (#) delimiters so the $ and \ don't get mangled.
        $this->assertMatchesRegularExpression(
            '#CACHE_NAME\s*=\s*(?:[`\'"\\s]+solar-v\d+|`solar-\$\{[^}]+\}`)#',
            $source,
            'Service worker must declare a solar-vN versioned cache name.',
        );
    }

    public function test_sw_js_never_caches_api_or_sanctum_routes(): void
    {
        $source = $this->get(route('pwa.service-worker'))->getContent();

        $this->assertStringContainsString(
            "/api/",
            $source,
            'SW must reference /api/ in its routing rules.',
        );
        $this->assertStringContainsString(
            "/sanctum/",
            $source,
            'SW must reference /sanctum/ in its routing rules.',
        );

        $this->assertMatchesRegularExpression(
            '#isAlwaysNetworkOnly[\s\S]{0,400}fetch\(request\)[\s\S]{0,200}return#',
            $source,
            'SW network-only branch must call fetch() and return its result, not a cached response.',
        );
    }

    public function test_sw_js_handles_skip_waiting_message(): void
    {
        $source = $this->get(route('pwa.service-worker'))->getContent();

        $this->assertStringContainsString('SKIP_WAITING', $source);
        $this->assertStringContainsString('skipWaiting()', $source);
    }

    public function test_app_blade_includes_pwa_meta_tags(): void
    {
        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('href="/manifest.json"', $html);
        $this->assertStringContainsString('theme-color', $html);
        $this->assertStringContainsString('#0b0f1a', $html);
        $this->assertStringContainsString('apple-touch-icon', $html);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $html);
    }
}

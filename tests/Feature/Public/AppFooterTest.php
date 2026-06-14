<?php

namespace Tests\Feature\Public;

use App\Models\AppMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — the AppFooter component renders on every layout
 * (auth, guest, public). The component is a Vue 3 single-file component
 * that hydrates client-side, so its actual <footer> HTML only appears
 * after JS execution. These tests pin the data-side contract that the
 * footer depends on: the Inertia `appMeta` shared prop, the build
 * version, and the routing surface.
 */
class AppFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_shares_app_meta_with_build_version(): void
    {
        // Public layout (no auth) — the footer reads appMeta.build_version
        $response = $this->get('/about');
        $response->assertInertia(fn ($page) => $page
            ->where('appMeta.build_version', '0.11.0')
            ->where('appMeta.locale', 'pt-BR')
        );

        // Guest layout (login page) — same shared prop
        $response = $this->get('/login');
        $response->assertInertia(fn ($page) => $page
            ->where('appMeta.build_version', '0.11.0')
        );
    }

    public function test_about_and_tutorial_routes_are_exposed_via_ziggy_payload(): void
    {
        // The AppFooter uses route('about') and route('tutorial') on the
        // client. The routes must be present in the Ziggy payload that
        // Inertia emits into the data-page <script>.
        $response = $this->get('/about');
        $html = $response->getContent();
        $this->assertStringContainsString('"about":', $html);
        $this->assertStringContainsString('"tutorial":', $html);
        $this->assertStringContainsString('"tutorial.chapter":', $html);
    }

    public function test_app_meta_persists_value_across_requests(): void
    {
        AppMeta::set('build_version', '0.11.0');
        $this->assertSame('0.11.0', AppMeta::get('build_version'));
        $response = $this->get('/about');
        $response->assertInertia(fn ($page) => $page
            ->where('appMeta.build_version', '0.11.0')
        );
    }
}

<?php

namespace Tests\Feature\Setup;

use App\Models\AppMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the RequireSetup middleware behaviour:
 * - when setup_completed_at is null, every request redirects to /setup
 *   (with carve-outs for the setup routes + /up + static assets).
 * - when setup_completed_at is set, the middleware is a no-op.
 */
class SetupMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldAutoCompleteSetup(): bool
    {
        return false;
    }

    public function test_dashboard_redirects_to_setup_when_incomplete(): void
    {
        $this->withoutAutoSetupComplete();
        $this->assertDatabaseMissing('app_meta', ['key' => 'setup_completed_at']);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('setup.show'));
    }

    public function test_login_passes_through_even_when_incomplete(): void
    {
        $this->withoutAutoSetupComplete();

        // /login is in PASS_THROUGH_PREFIXES — operator needs to reach it
        // to sign in once setup is done.
        $response = $this->get(route('login'));
        $response->assertOk();
    }

    public function test_setup_route_passes_through_in_incomplete_state(): void
    {
        $this->withoutAutoSetupComplete();

        $response = $this->get(route('setup.show'));
        $response->assertOk();
    }

    public function test_health_route_passes_through_in_incomplete_state(): void
    {
        $this->withoutAutoSetupComplete();

        $response = $this->get('/up');
        $this->assertContains($response->getStatusCode(), [200, 503]);
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_about_route_passes_through_even_when_incomplete(): void
    {
        $this->withoutAutoSetupComplete();

        $response = $this->get(route('about'));
        $response->assertOk();
    }

    public function test_dashboard_passes_through_after_complete(): void
    {
        AppMeta::create([
            'key'   => 'setup_completed_at',
            'value' => now()->toIso8601String(),
        ]);

        $response = $this->get('/dashboard');
        // Not redirected to /setup (would be a 302 with Location = /setup).
        $this->assertNotEquals(route('setup.show'), $response->headers->get('Location'));
    }
}
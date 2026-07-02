<?php

namespace Tests\Feature\Setup;

use App\Models\AppMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the wizard itself is reachable and behaves correctly when the
 * setup flag is unset (wizard active) or set (wizard skipped).
 */
class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Opt out of the TestCase's auto-setup-complete so the wizard is
     * visible during the test.
     */
    protected function shouldAutoCompleteSetup(): bool
    {
        return false;
    }

    public function test_setup_wizard_is_reachable_when_setup_incomplete(): void
    {
        // The TestCase auto-creates setup_completed_at; clear it first.
        $this->withoutAutoSetupComplete();

        $response = $this->get(route('setup.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Setup/Index')
            ->has('env_vars')
            ->where('setup_completed', false),
        );
    }

    public function test_setup_wizard_does_not_require_authentication(): void
    {
        $this->withoutAutoSetupComplete();
        $this->assertGuest();

        $response = $this->get(route('setup.show'));
        $response->assertOk();
    }

    public function test_post_setup_runs_migration_and_marks_complete(): void
    {
        $this->withoutAutoSetupComplete();
        $this->assertDatabaseMissing('app_meta', ['key' => 'setup_completed_at']);

        $response = $this->post(route('setup.store'));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('app_meta', ['key' => 'setup_completed_at']);
    }

    public function test_post_setup_skip_marks_complete_without_migration(): void
    {
        $this->withoutAutoSetupComplete();
        $this->assertDatabaseMissing('app_meta', ['key' => 'setup_completed_at']);

        $response = $this->post(route('setup.skip'));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('app_meta', ['key' => 'setup_completed_at']);
    }

    public function test_setup_route_passes_through_after_complete(): void
    {
        AppMeta::create([
            'key'   => 'setup_completed_at',
            'value' => now()->toIso8601String(),
        ]);

        $response = $this->get(route('setup.show'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('setup_completed', true),
        );
    }
}
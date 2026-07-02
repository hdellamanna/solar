<?php

namespace Tests;

use App\Models\AppMeta;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // FASE Deploy — auto-mark setup as complete so tests that don't
        // explicitly opt out don't have to fight the RequireSetup middleware.
        // The 3 setup-specific tests in tests/Feature/Setup/ rely on this
        // NOT being set, so they call $this->withoutAutoSetupComplete() before
        // making their request — see those test classes for the pattern.
        if ($this->shouldAutoCompleteSetup()) {
            $this->markSetupComplete();
        }
    }

    /**
     * Marks the setup wizard as complete by inserting the
     * `app_meta.setup_completed_at` row. Idempotent.
     *
     * Safe to call when the `app_meta` table does not exist (e.g. tests
     * that don't run migrations); in that case it's a no-op.
     */
    protected function markSetupComplete(): void
    {
        try {
            if (! Schema::hasTable('app_meta')) {
                return;
            }
            if (! AppMeta::where('key', 'setup_completed_at')->exists()) {
                AppMeta::create([
                    'key'   => 'setup_completed_at',
                    'value' => now()->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            // Swallow — tests that hit the setup wizard explicitly are
            // responsible for clearing the row first.
        }
    }

    /**
     * Removes the `setup_completed_at` row so the wizard becomes active
     * again. Used by the setup-wizard test classes to assert the
     * redirect behaviour.
     */
    protected function withoutAutoSetupComplete(): void
    {
        try {
            if (Schema::hasTable('app_meta')) {
                AppMeta::where('key', 'setup_completed_at')->delete();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Subclasses opt out of auto-complete-setup by overriding this to false.
     */
    protected function shouldAutoCompleteSetup(): bool
    {
        return true;
    }
}
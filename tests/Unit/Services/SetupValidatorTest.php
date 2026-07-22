<?php

namespace Tests\Unit\Services;

use App\Models\AppMeta;
use App\Services\SetupValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupValidatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Opt out of the TestCase's auto-setup-complete behaviour. Setup
     * tests need explicit control over when the row exists.
     */
    protected function shouldAutoCompleteSetup(): bool
    {
        return false;
    }

    private SetupValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutAutoSetupComplete();
        $this->validator = new SetupValidator;
    }

    public function test_required_are_set_returns_false_when_app_key_missing(): void
    {
        // The validator treats empty-string APP_KEY as "missing". This is
        // the realistic case for a misconfigured deploy where
        // `php artisan key:generate` was never run.
        //
        // SetupValidator::resolve() reads config() first and never falls
        // back to env() when the key is mapped — so zeroing out config()
        // is sufficient to simulate "missing".
        $configKeys = [
            'app.key',
            'app.url',
            'database.default',
            'mail.default',
            'mail.from.address',
        ];

        $originals = [];
        foreach ($configKeys as $key) {
            $originals[$key] = config($key);
            config([$key => null]);
        }

        try {
            $this->assertFalse($this->validator->requiredAreSet());
        } finally {
            foreach ($originals as $key => $value) {
                if ($value !== null) {
                    config([$key => $value]);
                }
            }
        }
    }

    public function test_required_are_set_returns_true_when_everything_set(): void
    {
        config([
            'app.key' => 'base64:'.str_repeat('a', 44),
            'app.url' => 'https://solar.example',
            'database.default' => 'sqlite',
            'mail.default' => 'log',
            'mail.from.address' => 'demo@solar.app',
        ]);

        $this->assertTrue($this->validator->requiredAreSet());
    }

    public function test_setup_completed_returns_false_initially(): void
    {
        $this->assertFalse($this->validator->setupCompleted());
    }

    public function test_setup_completed_returns_true_when_app_meta_set(): void
    {
        // Auto-setup-complete was disabled; the row doesn't exist yet.
        $this->assertDatabaseMissing('app_meta', ['key' => 'setup_completed_at']);

        AppMeta::create([
            'key' => 'setup_completed_at',
            'value' => now()->toIso8601String(),
        ]);

        $this->assertTrue($this->validator->setupCompleted());
    }

    public function test_collect_returns_required_and_optional_vars(): void
    {
        $vars = $this->validator->collect();

        $this->assertNotEmpty($vars);

        // Required entries have the `required: true` flag
        $required = array_filter($vars, fn ($v) => $v['required']);
        $this->assertNotEmpty($required);

        // Optional entries have the `required: false` flag
        $optional = array_filter($vars, fn ($v) => ! $v['required']);
        $this->assertNotEmpty($optional);

        // Each entry has the required keys
        foreach ($vars as $v) {
            $this->assertArrayHasKey('key', $v);
            $this->assertArrayHasKey('label', $v);
            $this->assertArrayHasKey('description', $v);
            $this->assertArrayHasKey('secret', $v);
            $this->assertArrayHasKey('required', $v);
            $this->assertArrayHasKey('is_set', $v);
        }
    }

    public function test_collect_masks_secrets(): void
    {
        // SetupValidator::resolve() reads RESEND_API_KEY via config('services.resend.key').
        // Setting config() alone is enough — the validator doesn't fall back to env()
        // for mapped keys.
        config([
            'app.key' => 'base64:'.str_repeat('a', 44),
            'app.url' => 'https://solar.example',
            'database.default' => 'sqlite',
            'mail.default' => 'log',
            'mail.from.address' => 'demo@solar.app',
            'services.resend.key' => 're_supersecretkey123456789',
        ]);

        $vars = $this->validator->collect();
        $resend = collect($vars)->firstWhere('key', 'RESEND_API_KEY');

        $this->assertNotNull($resend);
        $this->assertTrue($resend['is_set']);
        $this->assertTrue($resend['secret']);
        // Mask should keep the first 4 chars and bullet the rest
        $this->assertStringStartsWith('re_s', $resend['current_value']);
        $this->assertStringNotContainsString('supersecretkey', $resend['current_value']);
    }
}

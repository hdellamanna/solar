<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\AppMeta;
use App\Services\SetupValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * First-boot setup wizard.
 *
 * Reachable from any unauthenticated visit when setup_completed_at is null
 * (the RequireSetup middleware redirects every other route to /setup).
 *
 * Two outcomes:
 *  1. The operator pastes the env vars the wizard collects → wizard writes
 *     setup_completed_at and redirects to /login.
 *  2. The operator clicks "Skip" → wizard writes setup_completed_at and
 *     redirects to /login with a flash explaining that env vars must be set
 *     in the Render dashboard directly.
 *
 * The wizard NEVER persists secrets to the DB (those live in the
 * Render-managed env-var store). It only writes non-secret metadata to
 * the app_meta table.
 */
class SetupController extends Controller
{
    public function __construct(private SetupValidator $validator) {}

    /**
     * Render the wizard with the current env-var state.
     */
    public function show(): Response
    {
        return Inertia::render('Setup/Index', [
            'env_vars'        => $this->validator->collect(),
            'required_set'    => $this->validator->requiredAreSet(),
            'setup_completed' => $this->validator->setupCompleted(),
        ]);
    }

    /**
     * Validation pass: run migrate, seed, and a health check. On success,
     * mark setup complete and redirect to /login.
     */
    public function store(Request $request): RedirectResponse
    {
        $checks = [
            'migrate'  => $this->runMigrations(),
            'seed'     => $this->runSeed(),
            'database' => $this->pingDatabase(),
        ];

        $allOk = ! in_array(false, array_column($checks, 'ok'), true);

        if (! $allOk) {
            return back()
                ->with('error', 'Um ou mais checks falharam. Veja o relatório abaixo.')
                ->with('setup_report', $checks);
        }

        $this->markComplete();

        return redirect()
            ->route('login')
            ->with('success', 'Setup completo. Demo: demo@solar.app / solar123.');
    }

    /**
     * Skip the wizard — write setup_completed_at and redirect. Used when the
     * operator already configured everything manually in the Render dashboard.
     */
    public function skip(): RedirectResponse
    {
        $this->markComplete();

        return redirect()
            ->route('login')
            ->with('success', 'Setup pulado. Configure as env vars no painel do Render antes de prosseguir.');
    }

    /**
     * Run the migrations idempotently.
     */
    private function runMigrations(): array
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output   = Artisan::output();

            return [
                'check'    => 'migrate',
                'ok'       => $exitCode === 0,
                'output'   => $output,
            ];
        } catch (Throwable $e) {
            return [
                'check'    => 'migrate',
                'ok'       => false,
                'output'   => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Run the seed idempotently.
     */
    private function runSeed(): array
    {
        try {
            $exitCode = Artisan::call('db:seed', ['--force' => true]);
            $output   = Artisan::output();

            return [
                'check'    => 'seed',
                'ok'       => $exitCode === 0,
                'output'   => $output,
            ];
        } catch (Throwable $e) {
            return [
                'check'    => 'seed',
                'ok'       => false,
                'output'   => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm the database is reachable.
     */
    private function pingDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'check'    => 'database',
                'ok'       => true,
                'output'   => 'Conexão OK. Driver: ' . DB::connection()->getDriverName(),
            ];
        } catch (Throwable $e) {
            return [
                'check'    => 'database',
                'ok'       => false,
                'output'   => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mark setup complete. Idempotent.
     */
    private function markComplete(): void
    {
        AppMeta::updateOrCreate(
            ['key' => 'setup_completed_at'],
            ['value' => now()->toIso8601String()],
        );
    }
}
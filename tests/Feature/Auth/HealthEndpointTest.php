<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\InjectRequestId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coverage for FASE Polish / v0.10.0 — the /up health endpoint.
 *
 * `App\Http\Controllers\HealthController` runs four bounded
 * probes (database, queue, mail, storage) and returns a JSON
 * payload. The endpoint is documented as 200 OK when every
 * probe passes, 503 Service Unavailable when at least one
 * fails. The four cases below pin down:
 *
 *  1. happy path — all subsystems respond → 200 + `status: ok`;
 *  2. database probe failure → 503 + `status: degraded`;
 *  3. mail probe failure → 503 + `status: degraded`;
 *  4. the X-Request-Id header is echoed on the response (the
 *     health endpoint runs through `InjectRequestId` just like
 *     every other route).
 */
class HealthEndpointTest extends TestCase
{
    // ------------------------------------------------------------------
    // 1. test_health_endpoint_returns_ok_when_all_systems_healthy
    // ------------------------------------------------------------------
    public function test_health_endpoint_returns_ok_when_all_systems_healthy(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
        ]);

        // Every probe reports `ok: true` and a `latency_ms` float.
        $payload = $response->json('checks');
        $this->assertIsArray($payload);
        foreach (['database', 'queue', 'mail', 'storage'] as $probe) {
            $this->assertArrayHasKey($probe, $payload, "Probe '{$probe}' missing from /up payload");
            $this->assertTrue($payload[$probe]['ok'], "Probe '{$probe}' should be ok in happy path");
            $this->assertIsNumeric($payload[$probe]['latency_ms']);
        }
    }

    // ------------------------------------------------------------------
    // 2. test_health_endpoint_returns_degraded_when_database_unreachable
    // ------------------------------------------------------------------
    public function test_health_endpoint_returns_degraded_when_database_unreachable(): void
    {
        // Swap the default DB connection to one that points at a
        // port we know is closed on the test host. SQLite can't
        // be "unreachable" in the same way, so we use a MySQL
        // config with a non-listening port. The connect attempt
        // will fail fast and the probe's try/catch will trip.
        config([
            'database.connections.unreachable' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 1, // privileged port with no MySQL listener
                'database' => 'solar',
                'username' => 'solar',
                'password' => 'solar',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'options' => [
                    // Force a fast timeout — the default 30s connect
                    // timeout would make the test 30s slower even on
                    // success.
                    \PDO::ATTR_TIMEOUT => 1,
                ],
            ],
        ]);
        DB::setDefaultConnection('unreachable');

        try {
            $response = $this->get('/up');

            $response->assertStatus(503);
            $response->assertJson([
                'status' => 'degraded',
            ]);

            $database = $response->json('checks.database');
            $this->assertFalse($database['ok']);
            $this->assertSame('database: check failed', $database['error']);
            $this->assertArrayHasKey('exception', $database);
            $this->assertArrayHasKey('latency_ms', $database);

            // The other probes must still report their verdicts
            // (the controller isolates each probe so a single
            // failure does not abort the rest).
            $this->assertArrayHasKey('queue', $response->json('checks'));
            $this->assertArrayHasKey('mail', $response->json('checks'));
            $this->assertArrayHasKey('storage', $response->json('checks'));
        } finally {
            // Always restore the default connection so subsequent
            // tests (and the post-test teardown) work normally.
            DB::setDefaultConnection(config('database.default'));
        }
    }

    // ------------------------------------------------------------------
    // 3. test_health_endpoint_returns_degraded_when_mail_unreachable
    // ------------------------------------------------------------------
    public function test_health_endpoint_returns_degraded_when_mail_unreachable(): void
    {
        // Build a mailer with a transport that throws on
        // construction. We do this by registering a custom
        // mailer named "broken" via the Mail manager.
        Mail::extend('broken', function () {
            throw new \RuntimeException('simulated mail transport explosion');
        });
        config(['mail.default' => 'broken']);
        config([
            'mail.mailers.broken' => [
                'transport' => 'broken',
            ],
        ]);

        // The Mail facade caches the manager's resolved mailer.
        // Forget the cached instance so the new default kicks in
        // on the next resolve.
        Mail::forgetMailers();

        $response = $this->get('/up');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'degraded',
        ]);

        $mail = $response->json('checks.mail');
        $this->assertFalse($mail['ok']);
        $this->assertSame('mail: check failed', $mail['error']);
        $this->assertArrayHasKey('exception', $mail);
    }

    // ------------------------------------------------------------------
    // 4. test_health_endpoint_includes_request_id_in_response
    // ------------------------------------------------------------------
    public function test_health_endpoint_includes_request_id_in_response(): void
    {
        // Send a known id (matching the controller's regex) and
        // assert the same id is echoed on the response — this is
        // what `InjectRequestId` does on every request, and the
        // health route is no exception.
        $known = 'req_'.str_repeat('a', 32);

        $response = $this->withHeaders([
            InjectRequestId::HEADER_NAME => $known,
        ])->get('/up');

        $response->assertOk();
        $this->assertSame(
            $known,
            $response->headers->get(InjectRequestId::HEADER_NAME),
            'X-Request-Id must be echoed on /up responses',
        );

        // When the client does NOT send a request id, the
        // middleware generates one. Just assert the header
        // exists and matches the framework's id format.
        $fresh = $this->get('/up');
        $fresh->assertOk();
        $generated = $fresh->headers->get(InjectRequestId::HEADER_NAME);
        $this->assertNotNull($generated);
        $this->assertMatchesRegularExpression(
            '/^req_[0-9a-f]{32}$/',
            $generated,
            'A generated request id must match the req_<32 hex> shape',
        );
    }
}

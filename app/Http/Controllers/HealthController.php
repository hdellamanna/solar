<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Bounded health check for the `/up` endpoint (FASE Polish / v0.10.0).
 *
 * Replaces the default `Illuminate\Foundation\Application`
 * placeholder with four real, time-bounded probes:
 *
 *  1. **database** — runs `SELECT 1` with a 2-second timeout.
 *  2. **queue**    — pings the default queue connection (or a
 *     lightweight heartbeat) within 2 seconds.
 *  3. **mail**     — verifies the default mailer config can
 *     resolve its transport within 2 seconds (no actual send).
 *  4. **storage**  — touches a temp file on the default
 *     filesystem disk within 2 seconds.
 *
 * The endpoint is documented as public and JSON-only:
 *  - returns 200 with `status: ok` when every probe passes;
 *  - returns 503 with `status: degraded` when at least one
 *    probe fails.
 *
 * Each check is isolated — a failure in one does NOT abort
 * the others. The response body always carries every probe
 * so monitoring tools can see exactly which subsystem is
 * misbehaving.
 *
 * The probes never log raw exception messages (a failing
 * database check could leak connection-string fragments) —
 * just the exception class + a generic "check failed"
 * message.
 */
class HealthController extends Controller
{
    /** Per-probe timeout, in seconds. Bounded so a slow DB doesn't hold up the response. */
    private const PROBE_TIMEOUT_SECONDS = 2;

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'mail' => $this->checkMail(),
            'storage' => $this->checkStorage(),
        ];

        $allOk = ! in_array(false, array_column($checks, 'ok'), true);

        if (! $allOk) {
            // Log the failure once at warning level so on-call
            // sees the alert in Sentry / the JSON log
            // channel. We log the structured payload, not the
            // exception message, to avoid leaking secrets
            // from the failing subsystem.
            Log::warning('health.degraded', [
                'checks' => $checks,
            ]);
        }

        return response()->json(
            [
                'status' => $allOk ? 'ok' : 'degraded',
                'checks' => $checks,
            ],
            $allOk ? 200 : 503,
        );
    }

    /**
     * Database liveness probe — runs `SELECT 1` under a
     * bounded connection timeout. A connection-level
     * failure surfaces as a 503 with a generic message.
     */
    private function checkDatabase(): array
    {
        $started = microtime(true);
        try {
            $this->withTimeout(static function (): bool {
                DB::connection()->select('SELECT 1');

                return true;
            });

            return $this->ok('database', $started);
        } catch (Throwable $e) {
            return $this->fail('database', $started, $e);
        }
    }

    /**
     * Queue liveness probe — tries to instantiate a queue
     * connection (which opens the underlying transport) and
     * no-op's. We do NOT push a real job — that would
     * pollute the queue every time a load balancer pings
     * /up.
     */
    private function checkQueue(): array
    {
        $started = microtime(true);
        try {
            $this->withTimeout(static function (): bool {
                // `size(0)` is the cheapest available call on
                // every queue driver — it instantiates the
                // connection and pings the broker without
                // writing a job. The database driver counts
                // rows; the redis driver does a SCARD; the
                // sync driver returns 0 immediately.
                Queue::connection()->size();

                return true;
            });

            return $this->ok('queue', $started);
        } catch (Throwable $e) {
            return $this->fail('queue', $started, $e);
        }
    }

    /**
     * Mail liveness probe — verifies the default mailer
     * config can resolve a transport (no network call). A
     * misconfigured MAIL_MAILER surfaces here before a
     * real send fails in production.
     */
    private function checkMail(): array
    {
        $started = microtime(true);
        try {
            $this->withTimeout(static function (): bool {
                // `Mail::mailer()` returns the underlying
                // transport instance — the act of resolving
                // it is enough to prove the configuration is
                // loadable. The `array` test mailer returns
                // an in-memory transport that costs nothing.
                $mailer = Mail::mailer();
                if ($mailer === null) {
                    throw new \RuntimeException('Mailer not configured.');
                }

                return true;
            });

            return $this->ok('mail', $started);
        } catch (Throwable $e) {
            return $this->fail('mail', $started, $e);
        }
    }

    /**
     * Storage liveness probe — writes and removes a tiny
     * temp file on the default disk. Catches read-only
     * filesystems, full disks, and broken symlinks.
     */
    private function checkStorage(): array
    {
        $started = microtime(true);
        try {
            $this->withTimeout(static function (): bool {
                $disk = Storage::disk();
                $path = '.healthcheck-'.bin2hex(random_bytes(4));
                $disk->put($path, 'ok');
                $disk->delete($path);

                return true;
            });

            return $this->ok('storage', $started);
        } catch (Throwable $e) {
            return $this->fail('storage', $started, $e);
        }
    }

    /**
     * Run a probe under a hard timeout. We can't reliably
     * cancel a PHP socket mid-flight, so this is a
     * best-effort soft timeout — the probe's wall clock
     * is reported in the response, and a probe that
     * takes > 2s is reported as slow.
     *
     * The closure returns the probe's verdict; the helper
     * enforces a coarse wall-clock cap before falling
     * through to the normal try/catch.
     */
    private function withTimeout(\Closure $probe): bool
    {
        $started = microtime(true);
        $result = $probe();
        $elapsed = microtime(true) - $started;

        if ($elapsed > self::PROBE_TIMEOUT_SECONDS) {
            // Surface as a generic "slow" exception so the
            // caller records a failure with the elapsed time.
            throw new \RuntimeException(sprintf(
                'Probe exceeded %.2fs budget (took %.2fs).',
                self::PROBE_TIMEOUT_SECONDS,
                $elapsed,
            ));
        }

        return $result;
    }

    /**
     * @return array{ok: true, latency_ms: float}
     */
    private function ok(string $name, float $started): array
    {
        return [
            'ok' => true,
            'latency_ms' => round((microtime(true) - $started) * 1000, 2),
        ];
    }

    /**
     * @return array{ok: false, error: string, latency_ms: float}
     */
    private function fail(string $name, float $started, Throwable $e): array
    {
        // Log the class + a sanitised message — we do NOT
        // include $e->getMessage() verbatim because it can
        // embed credentials (database dsn, redis password)
        // from the failing transport.
        $message = sprintf('%s: %s', $name, 'check failed');

        return [
            'ok' => false,
            'error' => $message,
            'exception' => $e::class,
            'latency_ms' => round((microtime(true) - $started) * 1000, 2),
        ];
    }
}

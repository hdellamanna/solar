<?php

namespace App\Http\Middleware;

use App\Logging\RequestIdProcessor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads or generates an `X-Request-Id` for the current request
 * and makes it available to:
 *
 *  - the response headers (the same id comes back so the
 *    caller can correlate logs to a specific click),
 *  - the Monolog channel (via `Log::shareContext` + the
 *    {@see RequestIdProcessor} that lifts it into the `extra`
 *    array of every record),
 *  - any downstream middleware / controller that reads it from
 *    `$request->attributes->get('request_id')`.
 *
 * Runs before {@see \Inertia\Middleware} so the id is present
 * in the Inertia shared props the moment a page is rendered.
 */
class InjectRequestId
{
    /**
     * Name of the HTTP header that carries the request id
     * (both incoming and outgoing). The name is configurable
     * via the `LOG_REQUEST_HEADER` env var so deployments
     * behind proxies that already use a different header
     * (e.g. AWS ALB's `X-Amzn-Trace-Id`) can swap without
     * code changes.
     */
    public const HEADER_NAME = 'X-Request-Id';

    public const ATTRIBUTE_NAME = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $headerName = (string) (env('LOG_REQUEST_HEADER', self::HEADER_NAME) ?: self::HEADER_NAME);

        $incoming = $request->headers->get($headerName);
        $requestId = $this->sanitise($incoming) ?? RequestIdProcessor::generate();

        // Stash on the request for downstream consumers
        // (controllers, services, etc.).
        $request->attributes->set(self::ATTRIBUTE_NAME, $requestId);

        // Bind the id to the Monolog shared context BEFORE
        // the request runs. Any `Log::info()` / `Log::error()`
        // call the rest of the request lifecycle makes will
        // see the id in its context — the
        // {@see RequestIdProcessor} lifts it into the
        // record's `extra` so the JSON channel includes it
        // even if the call site didn't pass it manually.
        Log::shareContext([RequestIdProcessor::EXTRA_KEY => $requestId]);

        /** @var Response $response */
        $response = $next($request);

        // Echo the id back to the caller for log / trace
        // correlation. Only set it when the response didn't
        // already set its own (lets `terminate` callbacks
        // override).
        if (! $response->headers->has($headerName)) {
            $response->headers->set($headerName, $requestId);
        }

        return $response;
    }

    /**
     * Validate an incoming header value. We only accept ids
     * shaped like ours (`req_` prefix + 32 hex chars) to
     * prevent log-injection attacks — a malicious client
     * could otherwise push a fake id with embedded newlines
     * and pollute the JSON log file.
     */
    private function sanitise(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Strip any whitespace (some clients add a trailing
        // newline when proxying) and cap to a sane length.
        $value = trim($value);
        if (strlen($value) > 64) {
            return null;
        }

        if (preg_match('/^req_[0-9a-f]{32}$/i', $value) !== 1) {
            return null;
        }

        return strtolower($value);
    }
}

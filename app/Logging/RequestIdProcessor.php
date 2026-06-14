<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that attaches the current request's
 * `request_id` to every log record.
 *
 * The id itself is generated / read by
 * {@see \App\Http\Middleware\InjectRequestId}, which then
 * calls `Log::shareContext(['request_id' => $id])` so the
 * framework's logger caches it as a process-wide default. The
 * processor just lifts that value into the `extra` array of
 * every record (the `extra` is the only field the JSON
 * formatter serialises by default).
 *
 * Why a processor and not the shared context alone: the shared
 * context only applies to `Log::*()` calls that go through the
 * `Illuminate\Log\Logger`. Any code that talks to Monolog
 * directly (e.g. a third-party SDK) bypasses the shared
 * context. A processor sees every record the channel emits
 * and is the bulletproof attach point.
 */
class RequestIdProcessor implements ProcessorInterface
{
    public const EXTRA_KEY = 'request_id';

    /**
     * @inheritDoc
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // `Log::sharedContext()` returns an `array` in Laravel
        // 13 (see Illuminate\Log\LogManager::sharedContext —
        // declared `@return array`). It is NOT a Collection.
        // Use array access, not `->get()`.
        $context = Log::sharedContext();
        $requestId = is_array($context) ? ($context[self::EXTRA_KEY] ?? null) : null;

        if (! is_string($requestId) || $requestId === '') {
            // Fallback — if for some reason the middleware
            // didn't run (CLI, queue worker without HTTP
            // context, etc.), generate a fresh id so the
            // record is still tagged and we can correlate
            // across process boundaries.
            $requestId = self::generate();
        }

        return $record->with(extra: array_merge(
            $record->extra,
            [self::EXTRA_KEY => $requestId],
        ));
    }

    /**
     * Build a fresh `req_` + 32-hex-char request id. The 32 hex
     * chars are the canonical UUIDv4 with the dashes stripped
     * — 128 bits of entropy, the same shape we use in the
     * `X-Request-Id` response header.
     */
    public static function generate(): string
    {
        return 'req_'.str_replace('-', '', (string) Str::uuid());
    }
}

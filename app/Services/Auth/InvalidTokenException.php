<?php

namespace App\Services\Auth;

/**
 * Thrown by {@see BearerTokenService::consume()} when a token cannot
 * be honoured (unknown, consumed, or expired).
 *
 * Adapters wrap this exception in their own purpose-specific
 * exception class (InvalidVerificationTokenException,
 * InvalidResetTokenException, InvalidTwoFactorTokenException) so
 * the HTTP layer can keep its existing switch-on-type error
 * handling. The message is intentionally generic in English here;
 * the adapters provide the user-facing pt-BR string.
 */
class InvalidTokenException extends \RuntimeException
{
}

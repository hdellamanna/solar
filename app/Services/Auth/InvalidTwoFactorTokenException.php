<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * Thrown by {@see TwoFactorEnrollmentService} when a 2FA
 * enrollment / disable token is missing, expired, already used,
 * or the supplied TOTP code / password does not match.
 *
 * The message is safe to surface to the user — the controllers
 * flash it verbatim.
 */
class InvalidTwoFactorTokenException extends RuntimeException
{
}

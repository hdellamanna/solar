<?php

namespace App\Services\Auth;

/**
 * Thrown when a verification token cannot be honoured (unknown, consumed,
 * or expired). The HTTP layer catches this and bounces the user back to
 * the notice page with an error flash.
 */
class InvalidVerificationTokenException extends \RuntimeException {}

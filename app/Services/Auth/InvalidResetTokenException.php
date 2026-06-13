<?php

namespace App\Services\Auth;

/**
 * Thrown when a password-reset token cannot be honoured (unknown,
 * consumed, or expired). The HTTP layer catches this and bounces the
 * user back to the forgot-password page with an error flash.
 */
class InvalidResetTokenException extends \RuntimeException {}

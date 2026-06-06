<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a transaction split payload is invalid (e.g. the parts
 * do not sum to the parent transaction total).
 */
class InvalidTransactionSplitException extends RuntimeException
{
}

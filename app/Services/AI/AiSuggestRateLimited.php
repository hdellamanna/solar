<?php

namespace App\Services\AI;

use RuntimeException;

class AiSuggestRateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfter = 60)
    {
        parent::__construct('Limite de sugestões por hora atingido.');
    }
}

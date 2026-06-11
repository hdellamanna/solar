<?php

namespace App\Services\AI;

use RuntimeException;

class AiSuggestNotEnabled extends RuntimeException
{
    public function __construct(string $message = 'Sugestão por IA não está ativada para este usuário.')
    {
        parent::__construct($message);
    }
}

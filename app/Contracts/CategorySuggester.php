<?php

namespace App\Contracts;

use App\Models\User;

interface CategorySuggester
{
    public function suggest(string $description, User $user): ?array;
    public function name(): string;
}

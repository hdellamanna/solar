<?php

namespace App\Services\AI;

use App\Contracts\CategorySuggester;
use App\Models\AiSuggestionCache;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RulesProvider implements CategorySuggester
{
    public const NAME = 'rules';
    public const CONFIDENCE_MULTI_WORD = 0.95;
    public const CONFIDENCE_SINGLE_WORD = 0.80;
    public const CONFIDENCE_FALLBACK = 0.50;
    public const FALLBACK_CATEGORY = 'Outros';

    private const ACCENT_MAP = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    public function name(): string
    {
        return self::NAME;
    }

    public function suggest(string $description, User $user): ?array
    {
        $normalized = $this->normalizeDescription($description);
        if ($normalized === '') {
            return null;
        }
        $hash = $this->hashDescription($normalized);

        $cached = $this->readCache($user, $hash);
        if ($cached !== null) {
            return $cached;
        }

        [$matchedName, $confidence] = $this->findRuleMatch($normalized);
        $category = $this->resolveCategory($user, $matchedName);
        if ($category === null) {
            return null;
        }

        $payload = [
            'category_id'   => (int) $category->id,
            'category_name' => (string) $category->name,
            'confidence'    => (float) $confidence,
            'provider'      => self::NAME,
        ];

        $this->writeCache($user, $hash, $payload);
        return $payload;
    }

    public function normalizeDescription(string $description): string
    {
        $lower = mb_strtolower($description);
        $ascii = strtr($lower, self::ACCENT_MAP);
        $cleaned = preg_replace('/[^a-z0-9 ]+/', ' ', $ascii) ?? '';
        $collapsed = preg_replace('/\s+/', ' ', $cleaned) ?? '';
        return trim($collapsed);
    }

    public function hashDescription(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    private function readCache(User $user, string $hash): ?array
    {
        try {
            $row = AiSuggestionCache::query()
                ->where('user_id', $user->id)
                ->where('description_hash', $hash)
                ->where('expires_at', '>', now())
                ->first();
        } catch (\Throwable $e) {
            Log::warning('AiSuggest: cache read failed', ['err' => $e->getMessage()]);
            return null;
        }
        if (!$row) return null;

        $category = Category::query()->find($row->suggested_category_id);
        if (!$category) return null;

        return [
            'category_id'   => (int) $category->id,
            'category_name' => (string) $category->name,
            'confidence'    => (float) $row->confidence,
            'provider'      => (string) $row->provider,
        ];
    }

    private function writeCache(User $user, string $hash, array $payload): void
    {
        try {
            AiSuggestionCache::query()->updateOrCreate(
                ['user_id' => $user->id, 'description_hash' => $hash],
                [
                    'suggested_category_id' => $payload['category_id'],
                    'provider'              => $payload['provider'],
                    'confidence'            => $payload['confidence'],
                    'expires_at'            => now()->addDays((int) config('ai.cache_ttl_days', 30)),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('AiSuggest: cache write failed', ['err' => $e->getMessage()]);
        }
    }

    private function findRuleMatch(string $normalized): array
    {
        $rules = KeywordRules::all();
        $keys  = array_keys($rules);
        usort($keys, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        // Tokenize the description into alphanumeric words (3+ chars) so
        // we match whole words only. This prevents "oi" (a telecom carrier)
        // from matching inside "coisas" or "conhece", "net" from matching
        // inside "internet", and similar false-positives that pure substring
        // matching produces.
        $tokens = preg_split('/[^a-z0-9]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_filter($tokens, fn ($t) => strlen($t) >= 3);

        foreach ($keys as $keyword) {
            if ($keyword === '') continue;
            $matched = $this->isMultiWord($keyword)
                ? str_contains($normalized, $keyword)   // multi-word: substring is fine
                : in_array($keyword, $tokens, true);  // single-word: token equality
            if (!$matched) continue;
            $confidence = $this->isMultiWord($keyword)
                ? self::CONFIDENCE_MULTI_WORD
                : self::CONFIDENCE_SINGLE_WORD;
            return [$rules[$keyword], $confidence];
        }
        return [self::FALLBACK_CATEGORY, self::CONFIDENCE_FALLBACK];
    }

    private function resolveCategory(User $user, string $name): ?Category
    {
        $resolved = $this->findCategory($user, $name);
        if ($resolved !== null) return $resolved;
        if ($name !== self::FALLBACK_CATEGORY) {
            $resolved = $this->findCategory($user, self::FALLBACK_CATEGORY);
            if ($resolved !== null) return $resolved;
        }
        return null;
    }

    private function findCategory(User $user, string $name): ?Category
    {
        return Category::query()
            ->where(function ($q) use ($user, $name) {
                $q->where('user_id', $user->id)->where('name', $name)
                  ->orWhere(function ($q2) use ($name) {
                      $q2->whereNull('user_id')->where('name', $name);
                  });
            })
            ->orderByRaw('user_id IS NOT NULL DESC')
            ->first();
    }

    private function isMultiWord(string $keyword): bool
    {
        return str_contains($keyword, ' ');
    }
}

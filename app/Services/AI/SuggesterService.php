<?php

namespace App\Services\AI;

use App\Contracts\CategorySuggester;
use App\Models\AiSuggestionCache;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class SuggesterService
{
    public function __construct(
        private readonly ?CategorySuggester $driver = null,
    ) {}

    public function driver(): CategorySuggester
    {
        if ($this->driver !== null) return $this->driver;
        $name = (string) config('ai.driver', 'rules');
        $map = $this->driverMap();
        if (!isset($map[$name])) throw new InvalidArgumentException("Unknown AI driver [{$name}]");
        return app($map[$name]);
    }

    public function driverMap(): array
    {
        return ['rules' => RulesProvider::class];
    }

    public function suggest(string $description, User $user): array
    {
        if (!$user->use_ai_categorize) throw new AiSuggestNotEnabled();

        $hit = $this->peekCache($user, $description);
        if ($hit !== null) return $hit;

        $this->assertRateLimit($user);

        $result = $this->driver()->suggest($description, $user);
        if ($result === null) throw new AiSuggestUnavailable('Nenhuma categoria pôde ser sugerida.');

        $this->touchLastUsed($user);
        return $result;
    }

    private function peekCache(User $user, string $description): ?array
    {
        $normalized = $this->normalize($description);
        if ($normalized === '') return null;
        $hash = hash('sha256', $normalized);
        $row = AiSuggestionCache::query()
            ->where('user_id', $user->id)
            ->where('description_hash', $hash)
            ->where('expires_at', '>', now())
            ->first();
        if (!$row) return null;
        $category = $row->category;
        if (!$category) return null;
        return [
            'category_id'   => (int) $category->id,
            'category_name' => (string) $category->name,
            'confidence'    => (float) $row->confidence,
            'provider'      => (string) $row->provider,
        ];
    }

    private function assertRateLimit(User $user): void
    {
        $cap = (int) config('ai.rate_limit_per_hour', 30);
        if ($cap <= 0) return;

        $windowStart = CarbonImmutable::now()->subHour();
        $misses = AiSuggestionCache::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $windowStart)
            ->count();
        if ($misses < $cap) return;

        $oldest = AiSuggestionCache::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at', 'asc')
            ->value('created_at');
        $retryAfter = 60;
        if ($oldest) {
            $retryAfter = max(1, (int) ceil(
                CarbonImmutable::parse($oldest)->addHour()->diffInSeconds(CarbonImmutable::now(), true)
            ));
        }
        throw new AiSuggestRateLimited($retryAfter);
    }

    private function touchLastUsed(User $user): void
    {
        try {
            $user->forceFill(['last_ai_suggestion_at' => now()])->save();
        } catch (\Throwable) {}
    }

    private function normalize(string $description): string
    {
        $lower = mb_strtolower($description);
        $ascii = strtr($lower, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $cleaned = preg_replace('/[^a-z0-9 ]+/', ' ', $ascii) ?? '';
        $collapsed = preg_replace('/\s+/', ' ', $cleaned) ?? '';
        return trim($collapsed);
    }
}

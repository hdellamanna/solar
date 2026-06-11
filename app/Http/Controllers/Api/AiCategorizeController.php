<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AiSuggestNotEnabled;
use App\Services\AI\AiSuggestRateLimited;
use App\Services\AI\AiSuggestUnavailable;
use App\Services\AI\SuggesterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiCategorizeController extends Controller
{
    private const MIN_DESCRIPTION_LENGTH = 3;

    public function __construct(private readonly SuggesterService $service) {}

    public function suggestCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'required|string|min:' . self::MIN_DESCRIPTION_LENGTH,
        ]);

        try {
            $payload = $this->service->suggest($data['description'], $request->user());
        } catch (AiSuggestNotEnabled $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (AiSuggestRateLimited $e) {
            return response()
                ->json(['message' => $e->getMessage(), 'retry_after' => $e->retryAfter], 429)
                ->header('Retry-After', (string) $e->retryAfter);
        } catch (AiSuggestUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($payload);
    }
}

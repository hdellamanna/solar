<?php

namespace App\Http\Middleware;

use App\Services\UserMotionPreference;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inertia middleware that shares auth state, flash messages and app metadata
 * with every page rendered by the front-end.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version for cache busting.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default with every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $motionPrefs = app(UserMotionPreference::class);

        return [
            ...parent::share($request),
            'name' => config('app.name', 'Solar'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'theme' => $user->theme ?? 'system',
                    'use_ai_categorize' => (bool) ($user->use_ai_categorize ?? false),
                    'initials' => $user->initials(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // FASE Polish / v0.10.0 — request id (the
            // `req_` + 32-hex shape) is read off the request
            // attributes stashed by
            // {@see \App\Http\Middleware\InjectRequestId}.
            // Surfaced on the front-end so a user hitting
            // "Enviar log de erro" can paste the id into a
            // support ticket and we can grep the JSON log
            // channel for the exact request lifecycle.
            'requestId' => fn () => $request->attributes->get('request_id'),
            // FASE 4D — motion preferences injected server-side
            // so the first paint has the correct data attributes
            // with no FOUC.
            'motion' => $motionPrefs->toInertiaProps($request),
        ];
    }

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return parent::handle($request, $next);
    }
}

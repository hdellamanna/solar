<?php

namespace App\Http\Middleware;

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

        return [
            ...parent::share($request),
            'name' => config('app.name', 'Solar'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'theme' => $user->theme ?? 'system',
                    'use_ai_categorize' => (bool) ($user->use_ai_categorize ?? false),
                    'initials' => $user->initials(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
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

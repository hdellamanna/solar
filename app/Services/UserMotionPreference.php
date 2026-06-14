<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Computes the effective motion state for a given user at request time.
 *
 * Resolution logic:
 *   - If the user has explicitly chosen 'reduced' or 'full', that wins.
 *   - If the user is on 'auto', honour the OS `prefers-reduced-motion` media query.
 *
 * Usage:
 *   $prefs = app(UserMotionPreference::class);  // injected by Laravel
 *   $prefs->shouldAnimate('backdrop');         // bool
 */
class UserMotionPreference
{
    /** @var array<string, bool> OS-level reduced-motion flags, keyed by request hash. */
    private static array $osCache = [];

    public function __construct(
        public readonly ?User $user,
    ) {}

    /**
     * The resolved motion level for the current user + OS context.
     * Returns one of: 'auto', 'reduced', 'full'.
     */
    public function resolvedMotion(Request $request): string
    {
        if ($this->user === null) {
            return 'auto';
        }

        $userPref = $this->user->motion_preference ?? 'auto';

        if ($userPref !== 'auto') {
            return $userPref;
        }

        // User is on "auto" — check OS preference.
        return $this->osPrefersReduced($request) ? 'reduced' : 'full';
    }

    /**
     * Should animations run for the given category?
     *
     * Categories: 'backdrop', 'spring', 'parallax'.
     *
     * Returns true when:
     *   - resolvedMotion == 'full'
     *   - resolvedMotion == 'auto' AND OS does NOT prefer reduced AND the user's per-category flag is on
     *
     * Returns false when:
     *   - resolvedMotion == 'reduced'
     *   - resolvedMotion == 'auto' AND OS prefers reduced (OS wins, per-category flags are irrelevant)
     *   - resolvedMotion == 'auto' AND OS does NOT prefer reduced AND the user's per-category flag is off
     */
    public function shouldAnimate(string $category, Request $request): bool
    {
        $resolved = $this->resolvedMotion($request);

        if ($resolved === 'reduced') {
            return false;
        }

        if ($resolved === 'full') {
            return true;
        }

        // resolved == 'auto'
        if ($this->osPrefersReduced($request)) {
            return false;
        }

        // OS is full; fall through to the user's per-category flag.
        return match ($category) {
            'backdrop'  => (bool) ($this->user->motion_backdrop ?? true),
            'spring'    => (bool) ($this->user->motion_spring ?? true),
            'parallax'  => (bool) ($this->user->motion_parallax ?? true),
            default     => true,
        };
    }

    /**
     * Props to inject into Inertia shared state so every page knows
     * the motion configuration without an extra request.
     *
     * @return array{preference: string, backdrop: bool, spring: bool, parallax: bool}
     */
    public function toInertiaProps(Request $request): array
    {
        $resolved = $this->resolvedMotion($request);

        if ($resolved !== 'auto') {
            // Explicit user choice overrides everything.
            $fullOff = $resolved === 'reduced';

            return [
                'preference' => $resolved,
                'backdrop'  => ! $fullOff,
                'spring'    => ! $fullOff,
                'parallax'  => ! $fullOff,
            ];
        }

        // Auto mode: surface the OS signal + the user's per-category flags.
        $osReduced = $this->osPrefersReduced($request);

        return [
            'preference' => $osReduced ? 'reduced' : 'full',
            'backdrop'   => ! $osReduced && (bool) ($this->user->motion_backdrop ?? true),
            'spring'     => ! $osReduced && (bool) ($this->user->motion_spring ?? true),
            'parallax'   => ! $osReduced && (bool) ($this->user->motion_parallax ?? true),
        ];
    }

    /**
     * True when the OS prefers reduced motion for the current request.
     * Cached per-request to avoid repeated header inspection.
     */
    private function osPrefersReduced(Request $request): bool
    {
        $cacheKey = spl_object_id($request);

        if (! isset(self::$osCache[$cacheKey])) {
            self::$osCache[$cacheKey] = (bool) $request->header('Sec-CH-Prefers-Reduced-Motion') === true
                || $request->hasHeader('Sec-CH-Prefers-Reduced-Motion')
                    && strtolower($request->header('Sec-CH-Prefers-Reduced-Motion')) === 'reduce'
                    ?: $request->boolean('__test_reduced_motion', false);
        }

        return self::$osCache[$cacheKey];
    }
}
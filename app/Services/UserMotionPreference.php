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
     * Resolution hierarchy:
     *   1. User explicitly chose 'reduced'  → false (user override wins over OS)
     *   2. User explicitly chose 'full'     → per-category flags apply (OS never overrides)
     *   3. User is on 'auto' + OS reduced  → false (OS wins in auto mode)
     *   4. User is on 'auto' + OS full     → per-category flags apply
     *
     * This means a user who sets 'full' can still disable backdrop without
     * needing to also disable OS motion preferences — the granular flags
     * always apply unless the user chose 'auto' and OS is reduced.
     */
    public function shouldAnimate(string $category, Request $request): bool
    {
        // Step 1: an explicit user preference overrides OS signal.
        if ($this->user !== null) {
            $pref = $this->user->motion_preference ?? 'auto';

            if ($pref === 'reduced') {
                return false;  // user chose reduced — OS does not override
            }

            if ($pref === 'full') {
                // User chose full — per-category flags apply, OS does not bypass them.
                return match ($category) {
                    'backdrop'  => (bool) ($this->user->motion_backdrop ?? true),
                    'spring'    => (bool) ($this->user->motion_spring ?? true),
                    'parallax'  => (bool) ($this->user->motion_parallax ?? true),
                    default     => true,
                };
            }
        }

        // Step 2: user is on 'auto' (or is a guest) — OS signal applies.
        if ($this->osPrefersReduced($request)) {
            return false;
        }

        // OS is not reduced; per-category flags apply.
        return match ($category) {
            'backdrop'  => (bool) ($this->user?->motion_backdrop ?? true),
            'spring'    => (bool) ($this->user?->motion_spring ?? true),
            'parallax'  => (bool) ($this->user?->motion_parallax ?? true),
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
        $userPref = $this->user?->motion_preference ?? 'auto';
        $osReduced = $this->osPrefersReduced($request);

        // OS reduced motion always wins — surface 'reduced' so the frontend
        // knows the effective state even if the user explicitly chose 'full'.
        if ($osReduced && $userPref !== 'reduced') {
            return [
                'preference' => 'reduced',
                'backdrop'   => false,
                'spring'     => false,
                'parallax'   => false,
            ];
        }

        // User explicitly chose reduced or OS is not reduced.
        return [
            'preference' => $userPref,
            'backdrop'   => (bool) ($this->user?->motion_backdrop ?? true),
            'spring'     => (bool) ($this->user?->motion_spring ?? true),
            'parallax'   => (bool) ($this->user?->motion_parallax ?? true),
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
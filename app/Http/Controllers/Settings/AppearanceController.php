<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Appearance settings — motion preferences (FASE 4D).
 *
 * Controls the 4 motion fields on the authenticated user:
 *   motion_preference  enum('auto','reduced','full')
 *   motion_backdrop    boolean
 *   motion_spring      boolean
 *   motion_parallax    boolean
 *
 * The service {@see \App\Services\UserMotionPreference} owns the read
 * path (computing the effective state per request). This controller
 * owns the write path only.
 */
class AppearanceController extends Controller
{
    /**
     * Render the Appearance settings page.
     */
    public function __invoke(): \Inertia\Response
    {
        return inertia('Settings/Appearance');
    }

    /**
     * Alias for __invoke — used by the GET route.
     */
    public function show(): \Inertia\Response
    {
        return $this->__invoke();
    }

    /**
     * Persist the user's motion preference choices.
     *
     * All-or-nothing update in a single transaction. Validation rejects
     * unknown enum values and requires all 4 fields to be present so a
     * partial payload cannot accidentally leave the user in an inconsistent
     * state.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'motion_preference' => ['required', Rule::in(['auto', 'reduced', 'full'])],
            'motion_backdrop'   => ['required', 'boolean'],
            'motion_spring'     => ['required', 'boolean'],
            'motion_parallax'   => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'motion_preference' => $validated['motion_preference'],
                'motion_backdrop'   => $validated['motion_backdrop'],
                'motion_spring'     => $validated['motion_spring'],
                'motion_parallax'   => $validated['motion_parallax'],
            ])->save();
        });

        return redirect()
            ->route('settings.appearance.show')
            ->with('success', __('Preferences saved.'));
    }
}
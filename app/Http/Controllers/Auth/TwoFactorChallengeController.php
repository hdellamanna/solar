<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Models\RecoveryCode;
use App\Models\UserTwoFactor;
use App\Services\Auth\TwoFactorService;
use App\Services\Auth\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live 2FA challenge (Auth Phase 3).
 *
 * `create()` — show the form: a 6-digit TOTP input, an optional
 * recovery-code field, and a "Trust this device" checkbox. Only
 * reachable by an authenticated user with 2FA enabled (the
 * middleware ensures the user has been bounced here from a
 * protected route, not from /login by accident).
 *
 * `store()` — try TOTP first; on failure, try a recovery code.
 * On success, stamp the session and optionally issue a
 * trusted-device cookie. Bounce to dashboard with success flash.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private TrustedDeviceService $trustedDevices,
    ) {}

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/TwoFactorChallenge', [
            'hasRecoveryCodes' => $user?->recoveryCodes()
                ->whereNull('consumed_at')
                ->exists() ?? false,
        ]);
    }

    public function store(VerifyTwoFactorRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            // Should be impossible — the route is behind `auth`.
            return redirect()->route('login');
        }

        $code = trim($request->string('code')->toString());

        $matched = $this->tryTotp($user, $code)
            || $this->tryRecoveryCode($user, $code);

        if (! $matched) {
            return back()
                ->withErrors(['code' => 'Codigo invalido. Tente novamente.'])
                ->withInput(['code' => $code]);
        }

        $user->markTwoFactorVerified();

        if ($request->trustDevice()) {
            $this->trustedDevices->issue($user, $request);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Verificacao concluida.');
    }

    /**
     * Try the 6-digit TOTP path. Refreshes `last_counter` on the
     * model on success so the same 30s step cannot be replayed.
     */
    private function tryTotp($user, string $code): bool
    {
        $tf = $user->twoFactor;
        if (! $tf instanceof UserTwoFactor) {
            return false;
        }

        // Recovery codes have dashes; TOTP does not. Disambiguate
        // by character set so we do not call the TOTP library on
        // a recovery-code shape (or vice versa).
        if (! ctype_digit($code) || strlen($code) !== 6) {
            return false;
        }

        $newCounter = null;
        if (! $this->twoFactor->verifyCode($tf->secret_encrypted, $code, $newCounter, (int) $tf->last_counter)) {
            return false;
        }

        if ($newCounter !== null) {
            $tf->last_counter = $newCounter;
            if ($tf->confirmed_at === null) {
                $tf->confirmed_at = now();
            }
            $tf->save();
        }

        return true;
    }

    /**
     * Try a recovery code path. Hash, look up an unconsumed row
     * for this user, mark consumed, return true.
     */
    private function tryRecoveryCode($user, string $code): bool
    {
        $hash = hash('sha256', $code);

        /** @var RecoveryCode|null $row */
        $row = RecoveryCode::where('user_id', $user->id)
            ->where('code_hash', $hash)
            ->whereNull('consumed_at')
            ->first();

        if ($row === null) {
            return false;
        }

        $row->markConsumed();

        // Stamp `confirmed_at` on the 2FA row the same way the
        // TOTP path does, so audit / settings UI stays consistent.
        $tf = $user->twoFactor;
        if ($tf instanceof UserTwoFactor && $tf->confirmed_at === null) {
            $tf->confirmed_at = now();
            $tf->save();
        }

        return true;
    }
}

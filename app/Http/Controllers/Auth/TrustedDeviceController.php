<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Settings-side trusted-device management (Auth Phase 3).
 *
 *  - `destroy()`: revoke a single row by id.
 *  - `destroyAll()`: revoke every row for the user and clear the
 *    cookie.
 *
 * Both endpoints require the user to be authenticated; the
 * `two_factor` middleware on the route group ensures the user
 * has passed the challenge too (defense in depth — a stolen
 * cookie alone cannot list or revoke trusted devices).
 */
class TrustedDeviceController extends Controller
{
    public function __construct(private TrustedDeviceService $service) {}

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $revoked = $this->service->revokeOne($id, $user);

        return back()->with(
            $revoked ? 'success' : 'error',
            $revoked
                ? 'Dispositivo removido.'
                : 'Dispositivo nao encontrado.',
        );
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $count = $this->service->revokeAll($user);
        // After revoking all, the current browser is no longer
        // trusted — clear the session flag so the user is
        // challenged again on the next request.
        $user->clearTwoFactorVerified();

        return back()->with(
            'success',
            $count > 0
                ? "Todos os {$count} dispositivos confiaveis foram removidos."
                : 'Nenhum dispositivo confiavel para remover.',
        );
    }
}

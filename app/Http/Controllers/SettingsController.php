<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings index — Auth Phase 3 (FASE 4D).
 *
 * The home for every user-facing setting. Currently lists the
 * Security subsection (2FA + trusted devices) and acts as the
 * future anchor for additional settings (notifications, theme,
 * data export, etc).
 */
class SettingsController extends Controller
{
    /**
     * Render the settings index page. The page is intentionally
     * light — each subsection owns its own controller for the
     * actions (enable, disable, revoke). This controller is just
     * the "where do I click?" landing page.
     *
     * FASE 7 — the index now also carries the `availableLocales`
     * + `currentLocale` props so the "Idioma" card can show the
     * active language and link to the picker.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'currentLocale' => (string) ($user?->locale ?? app()->getLocale()),
            'availableLocales' => $this->localeLabels(),
        ]);
    }

    /**
     * @return array<int, array{code: string, name: string, english_name: string}>
     */
    private function localeLabels(): array
    {
        $labels = [
            'pt-BR' => ['name' => 'Português (Brasil)', 'english' => 'Portuguese (Brazil)'],
            'es'    => ['name' => 'Español',           'english' => 'Spanish'],
            'en'    => ['name' => 'English',            'english' => 'English'],
        ];
        $out = [];
        foreach ((array) config('app.available_locales', ['pt-BR', 'es', 'en']) as $code) {
            $label = $labels[$code] ?? ['name' => $code, 'english' => $code];
            $out[] = ['code' => $code, 'name' => $label['name'], 'english_name' => $label['english']];
        }
        return $out;
    }

    /**
     * Render the Security subsection (2FA + trusted devices).
     *
     * The actual POST/DELETE actions live in the
     * TwoFactorEnableController, TwoFactorDisableController and
     * TrustedDeviceController; this method is read-only and
     * just feeds the Vue page the rows it needs to render.
     */
    public function security(): Response
    {
        $user = request()->user();

        return Inertia::render('Settings/Security', [
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'enabledAt' => $user->twoFactor?->enabled_at?->toIso8601String(),
            'trustedDevices' => $user->trustedDevices()
                ->orderByDesc('last_seen_at')
                ->get()
                ->map(fn ($device) => [
                    'id' => $device->id,
                    'friendly_name' => $device->friendly_name,
                    'ip' => $device->ip,
                    'user_agent' => $device->user_agent,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'expires_at' => $device->expires_at?->toIso8601String(),
                ]),
        ]);
    }
}

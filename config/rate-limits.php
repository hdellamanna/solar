<?php

/*
|--------------------------------------------------------------------------
| Auth-flow rate limits (FASE Polish / v0.10.0)
|--------------------------------------------------------------------------
|
| These values are read by the named limiters registered in
| App\Providers\AppServiceProvider::boot() and bound to the
| `throttle:NAME` middleware aliases used in routes/web.php.
|
| Every limit is a `Limit::perMinute(...)->by($r->ip())` —
| i.e. the per-IP cap. The application-level throttles
| (1-per-30s / 5-per-hour on resend emails, the per-user
| TOTP / recovery-code counter) live in
| App\Services\Auth\BearerTokenService and TwoFactorService
| respectively, and are independent of these.
|
| Bumping these values up makes the auth flows more forgiving
| (real users fat-finger codes, real mailers retry, real
| users open the same email on a phone and a laptop within
| a few seconds). Lowering them makes brute force /
| enumeration more expensive. The defaults below match the
| design doc.
|
*/

return [

    /*
    | POST /login — bumped down to 10/min from the framework
    | default of 60/min (the api limiter) so a credential-
    | stuffing bot hits a wall fast.
    */
    'login' => [
        'per_min' => (int) env('RATE_LIMIT_LOGIN_PER_MIN', 10),
    ],

    /*
    | POST /email/verify/resend — tight cap; resends are
    | expensive (we send real mail), and the user already
    | has the link in their inbox.
    */
    'verify' => [
        'per_min' => (int) env('RATE_LIMIT_VERIFY_PER_MIN', 10),
    ],

    /*
    | POST /forgot-password — public endpoint, so the cap is
    | also a probing-defense measure. 5/min per IP is enough
    | for a user who fat-fingers their email and tries
    | again, and stops an enumeration script dead.
    */
    'forgot-password' => [
        'per_min' => (int) env('RATE_LIMIT_FORGOT_PER_MIN', 5),
    ],

    /*
    | POST /reset-password — paired with the per-token /
    | per-hour cap that lives on the service layer.
    */
    'reset-password' => [
        'per_min' => (int) env('RATE_LIMIT_RESET_PER_MIN', 5),
    ],

    /*
    | POST /two-factor/challenge (TOTP path) — 10/min per IP.
    | High enough for a user who fat-fingers, low enough
    | to make brute force expensive.
    */
    'two-factor.challenge' => [
        'per_min' => (int) env('RATE_LIMIT_2FA_CHALLENGE_PER_MIN', 10),
    ],

    /*
    | POST /two-factor/challenge (recovery-code path) —
    | tighter (3/min) because recovery codes are the weaker
    | of the two 2FA paths. The 10 recovery codes minted on
    | enrollment are the only thing standing between an
    | attacker and a 2FA-protected account if they steal a
    | session cookie, so the cap is the most aggressive
    | in the auth surface.
    */
    'two-factor.recovery' => [
        'per_min' => (int) env('RATE_LIMIT_2FA_RECOVERY_PER_MIN', 3),
    ],

];

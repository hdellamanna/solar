# test-coverage-validate — Password Reset Test Suite (FASE 4D, Auth Phase 2)

## Summary

Re-ran the 13 password-reset test cases against the fixed backend
(`feature/auth-p2-backend` with the Str import fix from commit `06c4ffd`).
**11 / 13 pass.** The remaining 2 failures are not caused by tests and not
caused by the Str fix — they expose a **NEW design bug in the backend** that
prevents the test from reaching the service. The bug is documented below as
BLOCKING and has been flagged back to the parent session. The test file
itself was not modified for this re-run (the failures are upstream, not in
the tests).

## Results

- **Targeted suite** — `php artisan test --filter=PasswordResetTest`
  - **11 / 13 pass, 2 fail** (the 2 fails are BLOCKING, see below)
  - Test inventory: 13 tests listed, names match the design doc verbatim
- **Full suite** — `php artisan test`
  - **237 / 239 pass, 2 fail, 0 regressions** in the 226 pre-existing tests
  - 2121 assertions (vs 2031 baseline — +90 from the new tests that do pass)

## Changed files

- `database/factories/EmailVerificationTokenFactory.php` — added `forUser()`
  and `passwordReset()` states (small, in-scope per the design). Committed
  in `9eb65e8`.
- `tests/Feature/Auth/PasswordResetTest.php` — new, 528 lines, 13 test cases
  (1–13 from the design doc). Committed in `9eb65e8`.
- No other changes. The tests that pass on the prior run still pass; the
  tests that were failing on the prior run (cases 1, 2, 3, 4, 5, 6, 8, 9, 10,
  11) now pass. Cases 7 and 8 were already failing for an unrelated reason
  (see BLOCKING below); the Str fix did not affect them.

## Branch

- `feature/auth-p2-tests` @ `9eb65e8` — pushed to `origin`
- URL: https://github.com/hdellamanna/solar/tree/feature/auth-p2-tests
- Parent of test commit: `06c4ffd` (Str fix from backend-impl track)
- Worktree path: `/tmp/solar-auth-p2-tests`

## `php artisan test` summary

```
Tests: 239, Assertions: 2121, Passed: 237, Failed: 2.
Duration: ~2.7s.

Failures:
 1) Tests\Feature\Auth\PasswordResetTest::test_password_reset_link_cannot_be_reused
    Expected: 'http://localhost/forgot-password'
    Actual:   'http://localhost/dashboard'
 2) Tests\Feature\Auth\PasswordResetTest::test_password_reset_invalidates_other_active_tokens
    Expected: 'http://localhost/forgot-password'
    Actual:   'http://localhost/dashboard'
```

## BLOCKING — backend bug exposed by tests #7 and #8

**Both failing tests share the same root cause.**

### What the design says

`docs/auth/phase-2/design.md` lines 235–236:

> 7. `test_password_reset_link_cannot_be_reused` — POST twice with same token → second fails
> 8. `test_password_reset_invalidates_other_active_tokens` — request 2 resets, use 1st → 2nd is invalid

The intent is that the second POST reaches `PasswordResetService::resetPassword()`,
which then throws `InvalidResetTokenException` (because the row's
`consumed_at` is non-null), and the controller redirects to
`route('password.request')` with the error flash.

### What the backend actually does

`routes/web.php` lines 99–117:

```php
Route::middleware('guest')->group(function () {
    // ...
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});
```

`NewPasswordController::store()` does `Auth::login($user)` on success and
regenerates the session. From the next request onward the user is
authenticated, and the `guest` middleware
(`Illuminate\Auth\Middleware\RedirectIfAuthenticated`) intercepts the
second POST before it reaches the controller — redirecting to `dashboard`
instead of the `password.request` flash.

### Why this is a backend bug, not a test bug

1. The `InvalidResetTokenException` path in
   `PasswordResetService::resetPassword()` (line 114) and the corresponding
   `forgot-password` redirect in `NewPasswordController::store()` (line 63)
   are **dead code in production** when a logged-in user replays the same
   token, because the `guest` middleware short-circuits first.
2. The design doc explicitly listed tests 7 and 8 as in-scope. The intended
   security contract — "tokens are single-use; reuse throws a friendly
   error" — is therefore not testable as designed.
3. Test 13 (`test_invalid_token_redirects_to_forgot_password_with_error`)
   only exercises the GET path, not the POST path, so this regression
   slipped past the backend track's own coverage.

### Suggested one-line fix (owned by the backend track)

Move `Route::post('/reset-password', [NewPasswordController::class, 'store'])`
**out of** the `guest` middleware group, and place it in a sibling group
with **no auth middleware at all** (the controller's service-layer check
is the real gatekeeper). The `GET` route can stay inside `guest` if
desired, or move with the POST — either way the test expectations will be
satisfied.

A minimal patch would be:

```php
// Inside the existing guest group:
Route::get('/forgot-password', [...])->name('password.request');
Route::post('/forgot-password', [...])->name('password.email');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');  // keep inside 'guest' or pull out

// Outside the guest group:
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->name('password.update');
```

Once this fix lands on `feature/auth-p2-backend`, a re-merge into the test
worktree will make tests 7 and 8 pass without any test-side changes.

## What I did NOT do (per the brief)

- I did **not** modify the test file to add `Auth::logout()`,
  `$this->flushSession()`, `$this->withoutMiddleware()`, or any other
  workaround to bypass the `guest` middleware. All of those would be
  silent workarounds for the backend bug and would mask the security
  contract violation in future regressions.
- I did **not** lower the assertion strength on tests 7 and 8 (e.g.
  accepting "any 302" instead of "302 to forgot-password"). The test
  expectations match the design doc verbatim and should stay that way.
- I did **not** push a follow-up commit to the backend branch — that
  work belongs to the backend track.

## Reproduce locally

```bash
cd /tmp/solar-auth-p2-tests
# (vendor is already a real copy, .env has APP_BASE_PATH=/tmp/solar-auth-p2-tests)

/opt/homebrew/opt/php@8.4/bin/php artisan test --filter=PasswordResetTest
# → 11 pass, 2 fail (the 2 fails are documented above as BLOCKING)

/opt/homebrew/opt/php@8.4/bin/php artisan test
# → 237 pass, 2 fail, 0 regressions
```

## Notes for the verifier

- Both failing tests fail with the *same* assertion error (assertRedirect to
  `forgot-password` got `dashboard`). The rest of the test body in both
  passes — only the second request's redirect target is wrong.
- The 11 passing tests cover the design doc's bullets 1, 2, 3, 4, 5, 6, 9,
  10, 11, 12, and 13. Bullets 7 and 8 are the BLOCKING failures.
- The factory's new `forUser()` and `passwordReset()` states are not used
  by any current test case (the tests build tokens via the service's
  `requestReset` HTTP path), but they are kept because the design doc
  includes them as a hook for future PR3 (2FA + trusted devices) work
  that will need to mint tokens directly.
- The test file was reused as-is from the prior run — no rewrites.
- The board was updated at every meaningful sub-step (see
  `/Users/hdellamanna/.mavis/plans/plan_2d1e88fa/board.md`).

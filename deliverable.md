# FASE Polish / v0.10.0 — Frontend track deliverable

## Summary

Frontend polish + 17 dedicated tests shipped on
`feature/polish-frontend`. The 2FA challenge/enable/disable
pages get a UX refresh (status pills, hold-to-confirm button,
mesh+glass surfaces, "trust this device" checkbox moved
above the TOTP input). The 17 new feature tests cover the
polish-backend's health endpoint, the six named rate
limiters, the new BearerTokenService public surface, and
the 2FA challenge route's stacked throttles. A focused
hotfix commit at the bottom of the branch repairs a
config-key lookup bug in the backend's AppServiceProvider
that the 17 tests surfaced — the two `two-factor.*`
dotted config keys were being collapsed into nested array
access and fell through to the 10/min default, silently
weakening the recovery-code throttle.

## Branch

- URL: https://github.com/hdellamanna/solar/tree/feature/polish-frontend
- Worktree: `/tmp/solar-polish-frontend`
- Base: `feature/polish-backend` @ `600d9c9`
- Tip: 3 logical commits (hotfix → frontend → tests)

## Commits

```
84f0ffd polish(2fa): UX refresh — hold-to-confirm button, status pills, mesh+glass
4bf4bcd fix(rate-limits): preserve dotted config keys (hotfix of polish-backend)
<test commit>
```

`git log --oneline origin/feature/polish-backend..HEAD` after
push will show all three; the test commit is third.

## Test result

```
$ php artisan test
Tests:  278 passed (2349 assertions)
Duration: 5.4s
```

**278 / 278 green.** The 17 new cases:

| File | Cases | Pass | Note |
|---|---|---|---|
| `HealthEndpointTest` | 4 | 4 | — |
| `RateLimitTest` | 6 | 6 | Originally 5/6 — the 6th (recovery 3/min) required the hotfix |
| `BearerTokenServiceTest` | 5 | 5 | — |
| `TwoFactorChallengeTest` (extend +2) | 9 (7 + 2 new) | 9 | New 429 cases for TOTP + recovery cap |

## Screenshots

- `/tmp/solar-polish-frontend/screen-2fa-challenge.png` — the
  post-login 2FA challenge page showing the new "Confiar
  neste dispositivo por 90 dias" checkbox positioned ABOVE
  the 6-digit code input, with the warm mesh canvas
  visible through the `.glass` card.
- `/tmp/solar-polish-frontend/screen-2fa-settings.png` — the
  Settings > Security page showing the new `.status-pill--warn`
  "Desativada" pill, the recovery-codes section card slot
  (gated on `twoFactorEnabled`, hidden when 2FA is off), and
  the trusted-devices empty-state.

## Files created

- `resources/js/Components/ConfirmHoldButton.vue` — reusable
  hold-to-confirm button. `:seconds` prop (default 3),
  `variant` (danger / primary / neutral), `:disabled`,
  `:min-width`. Emits `confirmed` on full hold. Touch + mouse
  + keyboard. rAF-driven GPU progress bar, spring back on
  early release, "Confirmado" checkmark overlay on success.
- `tests/Feature/Auth/HealthEndpointTest.php` (new, 4 cases)
- `tests/Feature/Auth/RateLimitTest.php` (new, 6 cases)
- `tests/Feature/Auth/BearerTokenServiceTest.php` (new, 5 cases)
- `screen-2fa-challenge.png` (screenshot)
- `screen-2fa-settings.png` (screenshot)
- `deliverable.md` (this file)

## Files modified

- `resources/css/app.css` — `.status-pill` component (3
  variants, subtle glow, leading dot).
- `resources/js/Pages/Auth/TwoFactorChallenge.vue` — trust
  checkbox moved above the code input with helper text;
  3-second auto-dismissing success toast.
- `resources/js/Pages/Auth/TwoFactorEnableConfirm.vue` —
  "Etapa 2 de 2: confirme o código" status pill at the top,
  better copy on the heading.
- `resources/js/Pages/Auth/TwoFactorDisableConfirm.vue` —
  regular submit button replaced with `<ConfirmHoldButton>`
  (3s hold, danger variant).
- `resources/js/Pages/Settings/Security.vue` — `.status-pill`
  for the 2FA status (3 states: Ativada / Desativada /
  Desativando...); "Última verificação: há X minutos" line
  using `Intl.RelativeTimeFormat`; new recovery-codes card
  showing "X de 10 códigos restantes" with a "Regenerar"
  placeholder button (out of scope for v0.10.0).
- `tests/Feature/Auth/TwoFactorChallengeTest.php` — extended
  with 2 new throttle-bypass cases.
- `app/Providers/AppServiceProvider.php` — hotfix: dotted
  rate-limit config keys now read via array access instead
  of `config('rate-limits.x.y')` (which collapses dots to
  nested path).

## Notes for the verifier

### 1. The hotfix is on the frontend branch by plan-owner instruction

The plan owner explicitly redirected (mid-task) to land the
`AppServiceProvider` config-key fix on `feature/polish-frontend`
as a focused hotfix of `feature/polish-backend` rather than
filing a defect for the backend track. The hotfix is the
FIRST commit in the branch and is documented in its message.
The merge order in the design doc is `polish-backend → polish-frontend`,
so when the engine fast-forwards `main` past both, the hotfix
travels with the frontend branch (cherry-picking cleanly into
`polish-backend` later is a `git cherry-pick 4bf4bcd` if the
maintainer prefers the order to be inverted).

### 2. The 2FA challenge route stacks two throttles — tighter cap wins

The 2FA challenge POST is registered with BOTH
`throttle:two-factor.challenge` (10/min) AND
`throttle:two-factor.recovery` (3/min). The middleware fires
the 3/min cap on the 4th request regardless of whether the
user submitted a TOTP code or a recovery code. This is the
design-doc behaviour ("the IP-level cap that fires first
wins") and is documented in the test bodies
(`test_challenge_429_when_totp_hits_per_minute_limit` and
`test_challenge_429_when_recovery_code_hits_per_minute_limit`).

The brief originally said "the TOTP test at the 11th request
(10/min)" — the test is now correctly bounded at the 3/min
recovery cap, which is the binding limit in production.

### 3. ConfirmHoldButton design

- 3-second default, configurable via `:seconds`.
- 3 visual variants (`danger` rose / `primary` solar /
  `neutral` ink), all with the same shape.
- Disabled prop turns the whole control inert.
- Touch + mouse + keyboard accessible (Space / Enter to
  start, Esc to cancel).
- rAF-driven progress bar using `transform: scaleX()` so
  the GPU handles the paint.
- Releases early → spring back to 0, no event.
- Holds to the end → 120ms "Confirmado" checkmark overlay,
  then `emit('confirmed')`.
- A11y: `aria-busy` and `aria-label` update with the
  countdown; visible status text remains for screen readers
  via the default slot.

### 4. Settings.vue consumes 4 NEW props from the controller

The Settings.vue page reads `lastVerifiedAt`,
`recoveryCodesRemaining`, `recoveryCodesTotal`, and
`disablePending` from the controller — props the polish-backend
ship did NOT add. All four have safe defaults (`null` /
`0` / `10` / `false`) so the page renders cleanly today, and
the rich data will start showing up once the controller
gets the corresponding `v0.11` work. The recovery-codes
section is gated on `twoFactorEnabled` (already supplied),
so the "X de 10 códigos restantes" card only renders when
2FA is on — the "Desativada" screenshot therefore doesn't
show that card. The screenshot with 2FA enabled would.

### 5. GuestLayout / Settings/Index are no-ops

`GuestLayout.vue` already renders the global mesh canvas in
its left brand panel. `Settings/Index.vue` already has a
"Segurança" card linking to `/settings/security`. Neither
file was touched; the brief said to document the no-op.

### 6. Worktree / vendor setup (per the brief)

- `cp -R /tmp/solar/vendor /tmp/solar-polish-frontend/vendor`
  + `APP_BASE_PATH=$(pwd)` in `.env` + `composer dump-autoload`.
- `cp -R /tmp/solar/public/build /tmp/solar-polish-frontend/public/build`
  for the Vite manifest (gitignored, but the dashboard tests
  need it — same workaround the backend track used).
- `cp -R /tmp/solar/node_modules /tmp/solar-polish-frontend/node_modules`
  for `npm run build` (Vite warm cache, 1.38s build).

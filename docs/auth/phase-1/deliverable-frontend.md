# Frontend — FASE 4D / Auth Phase 1 — Email Verification

## Summary

Shipped the user-facing surfaces for the email verification flow (PR1 of
FASE 4D): a full-page `VerifyEmailNotice` inside the existing `GuestLayout`,
a dismissable amber banner on the authenticated shell that surfaces an
unverified session, a Login error banner that deep-links back into the
flow when the backend flashes a "Verifique seu email" error, and the
plain-HTML transactional email that the new `VerifyEmailMail` will use.
All work is on the `feature/auth-p1-frontend` branch — pushed to origin.

## Changed files

### Created
- `resources/js/Pages/Auth/VerifyEmailNotice.vue` (158 lines) — full
  page inside `GuestLayout`. Shows the user's email in a monospace chip,
  renders success/error flash messages from the session, posts to
  `route('verification.resend')` with a 30s client-side cooldown, and
  exposes a "Sair da conta" escape hatch (POSTs to `route('logout')`) so
  users are never trapped on the page.
- `resources/views/emails/verify-email.blade.php` (60 lines) — plain
  HTML email following the design doc verbatim. Brand mark uses the
  Solar Money orange→yellow→violet gradient with `-webkit-background-clip:
  text` (the spec calls for this exact gradient). Body keeps diacritics
  ("Olá", "você", "começar"); subject line in the mailable is ASCII-only
  per the design doc constraint. No backdrop-filter, no JS frameworks, no
  external CSS. Uses the `$user` and `$verificationUrl` props exposed by
  `App\Mail\VerifyEmailMail`.
- `resources/views/emails/verify-email-text.blade.php` (10 lines) —
  plain-text fallback referenced by the mailable's `text:` option.

### Modified
- `app/Http/Middleware/HandleInertiaRequests.php` (+1 line) — added
  `'email_verified_at' => $user->email_verified_at?->toIso8601String()`
  to the shared `auth.user` array. This is the minimum coordination
  patch needed to make the layout banner know whether to render. ISO
  8601 string keeps the wire payload tiny and trivially consumable.
- `resources/js/Pages/Auth/Login.vue` (existing, +72 lines) — adds a
  rose glass error banner above the form. Reads `form.errors.email` OR
  `page.props.errors?.email` (Inertia 3 sometimes flashes to the shared
  errors prop). When the message matches `/verif/i` the banner also
  surfaces a "Reenviar email de verificação" link to
  `route('verification.notice')`. Existing flow, validation, and demo
  hint untouched.
- `resources/js/Layouts/AuthenticatedLayout.vue` (existing, +140 lines)
  — dismissable amber banner pinned above the main content (not the
  sidebar) that appears when `user.email_verified_at` is `null`. Carries
  the user's email, a 60-minute expiry hint, a "Reenviar" button (POSTs
  to `verification.resend` with a 30s cooldown), a "Sair" button, and an
  X. Dismissal is stored in `sessionStorage`; the watcher clears the
  flag automatically once `isUnverified` flips to `false`.

## Screenshots

Captured at 1440×900 with the existing playwright setup (`/tmp/screenshot.mjs`):

- `/tmp/screen-verify-notice.png` — `VerifyEmailNotice` page rendered
  with the seeded `unverified@solar.app` user. Shows the orange
  envelope icon, the email chip, the primary "Reenviar email" button,
  and the "Sair da conta" escape hatch below the divider.
- `/tmp/screen-login-with-banner.png` — `Login` page after a flashed
  `Verifique seu email para acessar sua conta.` error. Shows the rose
  glass banner with the "Reenviar email de verificação" link above the
  email input, with the rest of the form unchanged.

Both screenshots were taken against the worktree running a freshly seeded
SQLite database with an unverified `unverified@solar.app` user. The
Vite-built bundle in `public/build/assets/` is what the screenshots
render against — no caching tricks involved.

## Deviations from the task brief

1. **`email_verified_at` shared prop patch (1 line, frontend-adjacent
   middleware).** The brief said "do NOT touch migrations, controllers,
   services, or the EmailVerificationTest" — middleware is not in that
   exclusion list, and the banner is impossible to render correctly
   without this prop. The patch is a single line in
   `HandleInertiaRequests::share()` and is the only backend-adjacent
   change in the branch. Flagging it loudly so the integrator knows.

2. **Amber banner uses Tailwind's built-in `amber-*` palette** rather
   than the design tokens `chip-ink` / `surface-ink` mentioned in the
   brief. Reasoning: `chip-ink` is a small-pill utility and `surface-ink`
   is the deep-navy hero gradient — neither fits a warning banner. The
   codebase already uses `bg-rose-50`, `bg-emerald-50`, etc. for similar
   semantic surfaces, so `amber-*` is consistent and introduces zero
   *new* colors. The banner also uses `.shadow-soft` (existing token) and
   the existing amber envelope icon shape.

3. **`useForm` errors + `page.props.errors?.email` fallback in Login.** The
   brief said "show `form.errors.email` as a banner". In Inertia 3,
   `form.errors` is only populated on a *failed form submit*; errors
   flashed via `withErrors()` on a non-Inertia 302 (e.g. coming from the
   LoginController redirect) end up in `page.props.errors`. The banner
   reads both so it works in every flow.

4. **Resend link in Login banner targets `verification.notice` (GET), not
   a direct POST to `verification.resend`.** The banner is on a guest
   page (`/login`) where the user is not yet authenticated. POSTing
   to `verification.resend` from a guest session would 401; routing
   them through `/email/verify` instead (which is auth-protected and
   exempt from the `verified` middleware) lets them hit the actual
   Resend button there. This matches the design doc's "Login with
   unverified email → redirect to /email/verify → click Resend" flow.

5. **Email template kept the design doc's exact body wording** (with
   accents) and the spec's exact gradient for the brand mark. The brief
   said "Use the exact template in the design doc" — that's what this is.

## Test plan

- `npm run build` — green, 1.07s, no errors (only vueuse pure-annotation
  warnings that are external and pre-existing).
- The four interactive flows the brief asked us to validate:
  1. User lands on `/email/verify` (server-rendered from the backend
     track's `VerifyEmailController::notice`) — page renders, email is
     shown, "Reenviar" works, "Sair" works.
  2. User logs in with valid credentials while unverified — the
     backend redirects to `verification.notice` (covered by the
     backend track's tests; frontend just renders the page).
  3. Login form returns a flashed `Verifique seu email...` error —
     the Login banner appears with the resend link (screenshot
     proof).
  4. Unverified user reaches the authenticated layout — amber banner
     appears above the main content (rendered in the layout; in this
     branch's standalone state the verified middleware doesn't exist,
     so the demo user with `email_verified_at = now()` does NOT see
     the banner, and the seeded `unverified@solar.app` user DOES see
     it on every authed page).
- The "logout from verify-notice" requirement is satisfied: the page
  has a Sair da conta button that POSTs to `route('logout')`.

## Coordination notes for the integrator

- This branch is from `main` and adds the `email_verified_at` field to
  the Inertia shared props. The backend track (`feature/auth-p1-backend`,
  4cf8326) does NOT touch `HandleInertiaRequests` — so when both
  branches merge into `main`, this frontend's patch becomes
  authoritative. If you prefer the backend track to add the field
  instead, drop line 48 of the merged `HandleInertiaRequests.php` and
  add the equivalent line in the backend branch.
- The 4 frontend files (`VerifyEmailNotice.vue`, `Login.vue`,
  `AuthenticatedLayout.vue`, `verify-email.blade.php`) are decoupled
  from the backend's controller/middleware implementation. The pages
  only consume the route names `verification.notice` and
  `verification.resend` (which the backend track already defines with
  the same names).

## Build & deploy

- Branch: `feature/auth-p1-frontend` at commit `f36ee63` (pushed to
  origin via `git push -u origin feature/auth-p1-frontend`).
- 6 files changed, 448 insertions(+), 3 deletions(-).
- No PR opened (per the task brief).
- The `feature/auth-p1-frontend` worktree remains at
  `/tmp/solar-auth-p1-frontend` for any follow-up; the main checkout
  at `/tmp/solar` is untouched.

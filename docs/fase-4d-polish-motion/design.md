# FASE 4D-Polish UX Motion — design document

**Target release:** v0.11.0
**Goal:** let users tune every motion effect in the app, with a system-aware default and per-category overrides. Closes a UX gap (current effects can't be disabled) and aligns the app with WCAG 2.3.3 (Animation from Interactions).

## Motivation

The Liquid Crystal UI refresh (v0.6.0+) shipped a lot of motion: mesh canvas background, glass sweep, sun parallax on the dashboard, spring-eased page transitions, hold-to-confirm progress, number counting. We didn't ship a way to turn any of it off. Real users (not just accessibility users) sometimes want less motion — bright rooms, eye strain, low-power devices, or just preference.

WCAG 2.3.3 recommends honouring `prefers-reduced-motion`. We currently don't — every effect runs regardless of OS preference. This FASE fixes that and adds a granular override on top.

## Design

### Storage

- 4 columns on `users` table:
  - `motion_preference` — enum('auto','reduced','full') DEFAULT 'auto'
  - `motion_backdrop` — boolean DEFAULT 1 (mesh canvas + glass sweep)
  - `motion_spring` — boolean DEFAULT 1 (transitions, sheets, hold-to-confirm)
  - `motion_parallax` — boolean DEFAULT 1 (sun parallax)
- Default `auto + all-1` preserves current behaviour for existing users.
- Persisted per-user so the choice syncs across devices. localStorage would
  be device-local and break on phone → desktop transitions.

### Resolved state

The `UserMotionPreference` service computes the effective value at runtime:

```
resolved_motion = user.motion_preference == 'auto'
                    ? (OS prefers-reduced-motion ? 'reduced' : 'full')
                    : user.motion_preference

shouldAnimate(category) =
   resolved_motion == 'reduced'                                -> false
   resolved_motion == 'full'                                  -> true
   resolved_motion == 'auto' AND OS-prefers-reduced          -> false
   resolved_motion == 'auto' AND user.motion_<category> == 0 -> false
   otherwise                                                  -> true
```

The OS preference wins when `motion_preference == 'auto'`. The granular flags
give a third tier: keep micro-interactions but kill the heavy mesh canvas.

### Settings UI

New page `Settings/Appearance` (`/settings/appearance`):

- 3 radio cards at the top: **Sistema** / **Reduzido** / **Completo**.
  - "Sistema" is the default. Subtitle: "Seguindo a preferência do seu
    sistema operacional".
  - Each card shows a tiny illustration hinting at the effect density.
- "Personalizar" expand section (visible always, but the toggles are
  decoration unless the user picks one — actually they're always
  functional, they're the granular overrides). 3 toggles:
  - "Plano de fundo animado" (mesh canvas + glass sweep)
  - "Micro-interações" (page transitions, sheet spring, hold-to-confirm)
  - "Parallax" (sun parallax do dashboard)
- Live preview card shows a 200px demo of all 3 effect categories
  so the user sees what they're toggling. Preview animates/desliga
  in real time as flags change.
- Submit uses Inertia `useForm` and shows a success toast.

### CSS plumbing

A single source of truth: data attributes on `<html>`:

```html
<html data-motion="auto|reduced|full"
      data-motion-backdrop="0|1"
      data-motion-spring="0|1"
      data-motion-parallax="0|1">
```

`app.css` defines variants that read those attributes:

```css
html[data-motion="reduced"] *,
html[data-motion="reduced"] *::before,
html[data-motion="reduced"] *::after {
  animation-duration: 0.001ms !important;
  animation-iteration-count: 1 !important;
  transition-duration: 0.001ms !important;
  scroll-behavior: auto !important;
}

html[data-motion-backdrop="0"] .mesh-canvas,
html[data-motion-backdrop="0"] .glass-sweep {
  display: none !important;
}

html[data-motion-parallax="0"] .sun-parallax {
  transform: none !important;
}
```

The `<html data-motion>` is set server-side in `HandleInertiaRequests`
(so the very first paint already has the right state — no FOUC).
A JS listener on `matchMedia('(prefers-reduced-motion: reduce)')`
re-syncs the attributes when the OS preference changes at runtime
(e.g. user toggles "Reduce motion" in System Settings without reloading).

### New effects (rolled out in this FASE)

Two new liquid-glass effects, both gated by `motion.spring`:

1. **Dashboard balance cards — number counting animation.**
   Numbers count from 0 to final value over 800ms with ease-out,
   formatted via `Intl.NumberFormat('pt-BR', { style: 'currency',
   currency: 'BRL' })`. When `motion.spring=0`, numbers appear
   instantly with no transition.

2. **Transactions list — row-spring expand animation.**
   Click a transaction row to expand details. Uses Vue `<Transition>`
   with `enter-active-class="..."` for height 0 → auto + opacity
   0 → 1, 280ms ease-out. When `motion.spring=0`, the row
   snaps open.

### Composer / npm

No new deps. No npm packages added.

## Files touched (summary)

### Backend (motion-backend track)

- `database/migrations/2026_06_14_010000_add_motion_preferences_to_users.php` (new)
- `app/Models/User.php` (extend $fillable, add casts)
- `app/Services/UserMotionPreference.php` (new)
- `app/Http/Controllers/Settings/AppearanceController.php` (new)
- `app/Http/Middleware/HandleInertiaRequests.php` (inject motion props)
- `app/Providers/AppServiceProvider.php` (register singleton)
- `app/Models/UserFactory.php` (add `withReducedMotion()` state)
- `routes/web.php` (2 new routes inside auth group)
- `tests/Feature/Auth/MotionSettingsTest.php` (6 cases)
- `README.md` (1-paragraph note in Current status)

### Frontend (motion-frontend track)

- `resources/js/composables/useMotionPreference.ts` (new)
- `resources/js/Pages/Settings/Appearance.vue` (new)
- `resources/css/app.css` (add data-attribute variants)
- `resources/js/app.ts` (set initial data attrs + OS listener)
- `resources/js/Components/Settings/NavItem.vue` (or similar) (link to /settings/appearance)
- `resources/js/Pages/Dashboard.vue` (counting numbers on balance cards)
- `resources/js/Pages/Transactions/Index.vue` (row-spring expand)
- `tests/Feature/Auth/MotionRenderingTest.php` (4 cases)
- `tests/Feature/Auth/MotionSettingsPageTest.php` (4 cases)
- `tests/Feature/Auth/ReducedMotionCascadeTest.php` (2 cases)

## Test counts

- Backend track: 6 new tests in `MotionSettingsTest`. Suite: 261 + 6 = 267.
- Frontend track: 10 new tests across 3 files. Suite: 267 + 10 = 277.

## Rollout

- Existing users default to `auto + all-1`. No behaviour change at the
  toggle boundary — `auto + OS=full + all-1` = current behaviour.
- New users see the same default.
- The settings page is discoverable from `Settings > Appearance` in
  the nav. Not promoted via a banner — let users find it when they
  need it.

## Risks

- **Performance**: adding more `transform` + `will-change` rules could
  regress low-end devices. Mitigate by honouring the granular flags —
  power users can opt out of parallax.
- **Hidden regressions**: an effect might escape the data-attribute
  scope. The `ReducedMotionCascadeTest` pins the contract.
- **JS-disabled users**: the CSS data-attribute defaults to the
  Inertia-rendered value, which is set server-side from the user's
  stored preference. So even without JS, the server knows the
  preferred mode. The only loss is the live OS-preference re-sync.

## What is OUT of scope

- A "minimal" mode beyond `reduced` (no).
- Per-page motion overrides (e.g. "no motion on Dashboard only"). Future
  FASE if requested.
- Saving motion preference as part of a "preset" alongside theme and
  density. Future FASE.
- Animating the appearance settings page itself (the form transitions
  in/out). Future polish.

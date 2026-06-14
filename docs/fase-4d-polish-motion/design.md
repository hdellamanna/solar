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

## Copyright + About + Tutorial + AI agent hook (FASE 4D additions)

This FASE was expanded to include four cross-cutting concerns that don't
belong anywhere else. Each ships behind a feature flag so they can be
toggled independently for incremental rollout.

### 1. Copyright footer on every page

A new `<AppFooter>` Vue component renders on every layout (auth, app,
guest, settings). The footer carries:

- Left: `© 2026 HDMA Ltda. Todos os direitos reservados.`
  (the legal entity name; we'll file this as a Brazilian LTDA later)
- Center: 3 inline links — Sobre / Tutorial / Privacidade
- Right: build version `v0.11.0` (read from a constant) + locale code
  (placeholder for FASE 7, defaults to `pt-BR`)

The footer respects the `motion.spring` flag — it slides in once per
session on first scroll, then becomes static. When the flag is off, the
footer is just there from the first paint.

### 2. Sobre (About) page

Public page at `/about`, no auth required. Tells the founding story in
Henrique's voice, but written with the brand voice — confident, plain
language, light wit. Three sections:

**The why** — Henrique was a heavy Microsoft Money user for over a
decade. When Microsoft sunset the product and the modern alternatives
either bloated into Quicken clones or pivoted to crypto-first trading
apps, the personal-finance software landscape lost something. Not just
the software — the *philosophy*. Money should be a quiet, boring ledger,
not a feed.

**The what** — Solar is a personal-finance app built on the Money
philosophy: clear accounts, double-entry transactions, predictable
categories, no upsells, no bank-linking dark patterns, no premium tier
that locks your data. The Liquid Crystal UI is the modern
interpretation — the same calm ledger feel, brought to a 2026
glass-and-light design language.

**The how** — Open source (MIT), self-hostable, single-tenant by
default. Stack: Laravel 13 + Vue 3 + Inertia 3 + SQLite. The architecture
is intentionally boring so anyone who can read a PHP file can audit
their own money. Security: 2FA, structured logging, rate-limited auth,
isolated token storage, Sentry-ready.

**The roadmap** — short forward look: FASE 7 (i18n pt/es/en), FASE 8
(AI insights), FASE 9 (crypto tracker), FASE 10 (Tauri installer for
Mac/Windows/Linux).

**The founder** — small block with Henrique's name, GitHub handle
(@hdellamanna), and a single line in his voice: "Construindo no Rio,
lançando de Porto Real."

Copy lives in `resources/js/Pages/About.vue` (pt-BR, but the strings
get extracted to a lang file in FASE 7). The page is a static Inertia
page — no auth, no data fetch, prerender-friendly.

### 3. Tutorial page (interactive)

Public page at `/tutorial`, no auth required. Six chapters mirroring
the FASE 1–6 surface area:

1. **Contas e categorias** — create accounts, build a category tree
   that matches how you think, attach icons.
2. **Transações** — record income/expense, transfers between accounts,
   split a transaction across categories.
3. **Metas e orçamentos** — set a goal, track it, get nudged when you
   drift.
4. **PIX e transferências** — log PIX in/out, see the running balance.
5. **Investimentos e dívidas** — track asset positions, mark loan
   amortization.
6. **Segurança** — enable 2FA, trusted devices, recovery codes, audit
   recent sessions.

Each chapter has:
- A short prose intro (3-4 paragraphs)
- An interactive demo block (a tiny Vue mini-app that simulates the
  feature with mock data — no backend calls)
- A "Show me in Solar" button that deep-links to the real page (when
  the user is authed) or to the login page (when not)

The interactive demos are *the* thing that earns the "didática porém
interativa" framing. They're not screenshots, they're live components.
The user can poke at them, see the state change, learn the model
before touching their real money.

### 4. AI agent hook (placeholder for FASE 8)

A persistent AI assistant slot that's always available — like a
Spotlight / Raycast / Cmd-K palette but for financial actions. This
FASE ships only the **chrome** (the floating bubble, the keyboard
shortcut, the open/close animation, the input field, the empty state,
the loading state). The actual AI integration (Groq + tool-use to
actually mutate state) is FASE 8.

The slot opens via:
- Click the floating bubble in the bottom-right
- `Cmd+K` (Mac) / `Ctrl+K` (other) from anywhere
- A voice-input button (placeholder, opens browser's `webkitSpeechRecognition`
  if available — FASE 8 will wire to the real model)

Empty state copy: "Pergunte qualquer coisa sobre suas finanças.
'Adicionei R$ 50 de almoço hoje.' 'Quanto gastei em delivery este mês?'
'Estou no caminho de bater minha meta de emergência?'"

This is the **anticipation layer** for FASE 8. By the time FASE 8 lands,
the slot is already familiar. The user already knows where to type. The
shortcut is already muscle memory.

The bubble respects `motion.spring` (slides up, spring bounce) and
`motion.parallax` (no parallax — it would be distracting). When the
user has `motion_reduced`, the bubble is a static button.

### File additions

- `resources/js/Components/AppFooter.vue` (new) — copyright + links
- `resources/js/Pages/About.vue` (new) — public About page
- `resources/js/Pages/Tutorial.vue` (new) — interactive tutorial
- `resources/js/Components/AiAgentSlot.vue` (new) — chrome only,
  behind a `features.ai_agent` config flag (default off until FASE 8)
- `resources/js/composables/useAiAgent.ts` (new) — open/close state,
  shortcut listener, focus management
- `resources/js/Components/Tutorial/*.vue` (6 new mini-app demos, one
  per chapter)
- `routes/web.php` — 3 new public routes (`/about`, `/tutorial`,
  `/tutorial/:chapter`)
- `app/Http/Controllers/AboutController.php` + `TutorialController.php`
- `resources/css/app.css` — extend with `.ai-agent-slot` styles
- `tests/Feature/Public/AboutPageTest.php` (3 cases)
- `tests/Feature/Public/TutorialPageTest.php` (4 cases)
- `tests/Feature/Public/AiAgentSlotTest.php` (3 cases)

### Test counts (revised)

- Backend track: 6 (motion) + 3 (About/Tutorial) = 9 new tests.
  Suite: 261 + 9 = 270.
- Frontend track: 10 (motion) + 10 (public pages + AI chrome) =
  20 new tests. Suite: 270 + 20 = 290.

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

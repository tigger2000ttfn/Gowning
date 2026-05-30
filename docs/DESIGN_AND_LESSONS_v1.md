# GQS — Design Language, Preferences & Hard-Won Lessons (v1)

**Last updated:** May 30, 2026
**Purpose:** So future sessions don't re-run the circles we ran. Read this before touching
the UI. The single most important rule is at the top.

---

## 0. THE GOLDEN RULE — STOP GUESSING, READ THE DOM

Claude cannot see the rendered page. Vendor CSS (Filament/Tailwind) is **not** in the sandbox.
Every time we *guessed* a Filament class name, the fix failed and we burned a deploy. Every time
the user pasted **browser console output**, the fix landed on the first try.

**So: for ANY layout/styling problem, get the real DOM first.** Do not write a selector against a
class you haven't confirmed exists on *this* build. If we've gone around more than twice on the same
visual issue, that's the signal to ask for a console snippet instead of trying a third guess.

### The console snippets that actually work

Walk up the DOM from an element to see every wrapper's padding/margin (this is what cracked the hero
AND the chart widgets):
```javascript
(() => {
  const el = document.querySelector('SELECTOR_FOR_THE_THING');   // e.g. '.dash-hero' or 'canvas'
  let n = el.parentElement, out = [];
  while (n && n.tagName !== 'BODY') {
    const cs = getComputedStyle(n);
    out.push(`${n.tagName}.${[...n.classList].join('.')} | pad:${cs.padding} | margin:${cs.margin} | maxW:${cs.maxWidth}`);
    n = n.parentElement;
  }
  return out.join('\n');
})();
```

Measure vertical alignment (this is what fixed the header logo/brand vs the icons):
```javascript
(() => {
  const r = el => el ? `top:${Math.round(el.getBoundingClientRect().top)} bottom:${Math.round(el.getBoundingClientRect().bottom)} h:${Math.round(el.getBoundingClientRect().height)}` : 'NOT FOUND';
  return [
    'TOPBAR: ' + r(document.querySelector('.fi-topbar')),
    'LOGO:   ' + r(document.querySelector('img[alt="Astellas"]')),
    'BRAND:  ' + r(document.querySelector('.gqs-brand-text')),
    'ICON:   ' + r(document.querySelector('.fi-topbar-database-notifications-btn') || document.querySelector('.fi-user-menu-trigger')),
  ].join('\n');
})();
```

Confirm a computed value / whether our injected CSS even loaded:
```javascript
(() => {
  const sb = document.querySelector('.fi-sidebar');
  const cs = getComputedStyle(sb);
  return [
    'sidebar width: ' + cs.width,
    'classes: ' + sb.className,
    '--sidebar-width: ' + cs.getPropertyValue('--sidebar-width'),
    'head-styles loaded: ' + (document.head.innerHTML.includes('SOME_TOKEN_FROM_OUR_CSS') ? 'YES' : 'NO'),
  ].join('\n');
})();
```

---

## 1. STACK & CONSTRAINTS (the environment that shapes every fix)

- **Filament v5** + Laravel + Tailwind **v4**. PostgreSQL on AWS RDS. Apache subfolder `/gowning/`.
- Claude works in a sandbox, **pushes to GitHub `main`**, user deploys on the E3 server with
  `sudo bash deploy.sh`. Claude **cannot run PHP/composer/npm** (packagist/npm blocked) and does
  **not** have `vendor/` or `node_modules`.
- Therefore: Claude can't compile or preview. The loop is: edit → push → user deploys → user reports
  (ideally with console output) → fix.

### Filament v5 namespace gotchas that caused fatals (memorize these)
- Auth form component overrides return **`Filament\Schemas\Components\Component`**, NOT
  `Filament\Forms\Components\Component`. (Caused a fatal on the custom Login page.)
- `TextInput`, `Checkbox` etc. still live in `Filament\Forms\Components\…`.
- Base auth pages live under **`Filament\Auth\Pages\…`** in v5 (e.g.
  `Filament\Auth\Pages\Login`, `Filament\Auth\Pages\PasswordReset\RequestPasswordReset`),
  NOT the v3 `Filament\Pages\Auth\…`.
- Bare model class names inside `app/Filament/Admin/Pages/*` resolve to the **Pages namespace**,
  not `App\Models`. Always import or fully-qualify (`\App\Models\QualificationRun`). (Caused a 500.)
- Lang-file label overrides (`lang/vendor/filament-panels/...`) were **never read**. The reliable
  way to change auth labels is a **custom page class** overriding `getEmailFormComponent()`,
  `getAuthenticateFormAction()`, `getHeading()`, etc.

---

## 2. THE CASCADE PROBLEM & THE PATTERN THAT BEATS IT

**Root cause of ~half our circles:** Filament v4/v5 ships its base CSS inside `@layer` blocks
(Tailwind v4 cascade layers). Our custom `theme.css` had **no layers**, so raw `!important` rules
won inconsistently — some applied (buttons), some lost (header background, page padding). Chasing
specificity in `theme.css` was unreliable.

**The pattern that works — CONTROL the element, don't fight the cascade:**
1. **Inject critical CSS directly into `<head>`** via a render hook
   (`PanelsRenderHook::HEAD_END` → `resources/views/filament/head-styles.blade.php`). Styles in the
   document head, after Filament's stylesheet, with `!important`, win regardless of layers/compile.
   This is now our go-to for any stubborn override.
2. **Use `:has()`** to scope to a state we control. `.fi-main:has(.dash-hero)` targets "the main
   area, but only on the dashboard" without depending on a page class we weren't sure existed
   (`.fi-page-dashboard` was a guess that may not match). `:has()` cracked both the hero and the
   login starfield.
3. **Add our own backdrop/element** rather than recoloring Filament's. The login stars only worked
   once we added a dedicated full-page charcoal `<div>` (z-index 0) instead of trying to override
   `.fi-simple-layout` background (which lost the cascade fight, leaving a white page where white
   stars were invisible).

**Build reliability:** `deploy.sh` does `rm -rf public/build` then a **loud** `npm run build`
(never `--silent` — a silent build hid failures for many rounds and served stale CSS), restarts
Apache (flushes OPcache), and verifies a theme token case-insensitively (Tailwind lowercases hex).

---

## 3. CONFIRMED DOM FACTS (this Filament v5 build) — use these, don't re-guess

From actual console inspection:
- Topbar height: **64px**. Right-side icons sit ~centered (top:13 bottom:49).
- `MAIN.fi-main.fi-width-7xl` carries the page side padding (`0 32px`) — **this** is the element to
  zero for a full-bleed hero, NOT `.fi-page-content` (which was already `0`).
- `.fi-page-header-main-ctn` carries the top gap above content (`32px`).
- Dashboard page wrapper is `DIV.fi-page` — `.fi-page-dashboard` was NOT a reliable hook; use
  `:has(.dash-hero)`.
- The hero and the footer chart widgets **share `.fi-page-content` as a parent.** Padding that
  parent pads BOTH (this is why fixing the charts un-fixed the hero). Solution: keep
  `.fi-page-content` at `0`, pad **only** `.fi-wi-widget` (the chart wrapper; the hero is plain view
  markup and is NOT a widget, so it stays full-bleed). There is **no `.fi-page-footer-widgets`**
  element — that was a guessed class that did nothing.
- Sidebar: open width controlled by `--sidebar-width` var. **Forcing `width:Xrem !important` on
  `.fi-sidebar` BREAKS collapse** (it pins the width in both states). Set the **variable on the
  open state** (`.fi-sidebar.fi-sidebar-open { --sidebar-width: 14rem }`) and let Filament shrink
  it natively when collapsed.
- Brand text is an `<a>` inside the topbar, so `.fi-topbar a { color: … !important }` overrides
  the brand's inline color. Needed a dedicated `.gqs-brand-text { color:#444 !important }` after it.
- The sidebar collapse button (`.fi-topbar-collapse-sidebar-btn-ctn`) lives in the **topbar** by
  Filament default. Repositioning it with CSS **broke the collapse function** — leave it in the
  default spot where it works. (If it must move into the sidebar later, do it with a sidebar render
  hook, not by relocating the existing button.)

---

## 4. THE USER'S DESIGN PREFERENCES (how they like things)

### Brand / header
- Astellas logo (star+swoosh mark + "astellas" wordmark, PNG 3840×1105) + "Gowning Qualification"
  text beside it.
- Brand text color **#444**, weight 700, ~15px, letter-spacing .03em.
- Alignment recipe (same as the login bar, which is the reference that "looks good"):
  container `display:flex; align-items:center; height:100%`, logo 34px, and a `padding-top` nudge on
  the **text only** (~18–20px) so its baseline aligns with the "astellas" wordmark. The logo/brand
  must **bottom-align with the right-side header icons** (icons bottom ≈ 49 in the 64px bar).
- Header is intentionally the **same dark charcoal (#1C1C21) in BOTH light and dark themes**, with a
  magenta bottom border.

### Login page (the reference for "wonderful")
- Full-page **cosmic charcoal backdrop** (dedicated `.gqs-backdrop` div, #15151A), drifting
  **nebula** clouds (multi-keyframe, ~18s, noticeable movement), and a **starfield**.
- Stars: **small** (≈2–9px, capped — the user repeatedly said the big ones were "too big"), each
  with a **glow** (box-shadow computed in PHP and inlined per star; do NOT use fragile
  `calc(var(--sz))` — it collapses). Mix of white / gold (#E8C24A) / purple (#B98CE0) / occasional
  red. Density a little heavier top-left.
- Twinkle: **mostly slow, smooth fades** (4–9s), with only ~25% flashing faster (1.8–3.2s). Not a
  fast uniform blink.
- Top "login bar": logo + "Gowning Qualification" (#444, padding-top ~20px) on the left, a
  "Back To Site" link on the right. In-card logo ~56px with top breathing room (via card
  `padding-top`, NOT margin on the img — margin didn't apply and padding-top on the img shrank it).
- All auth text **Title Case**: "Sign In", "Email Address", "Password", "Remember Me",
  "Forgot Your Password?", "Reset Your Password", "Send Reset Link", "Back To Login".

### Dashboard ("awesome" — the current bar to maintain)
- **Full-bleed cosmic hero** connected directly to the header, spanning the full width at every
  screen size, no border/gap. Charcoal with glowing twinkling stars (same glow treatment as login,
  but tuned smaller after feedback) and a drifting nebula. "Welcome Back" + name; subtext must sit
  beside/under the name without awkward wrapping (`min-width:0` on the text block, left-aligned).
- Solid-gradient stat cards with watermark icons.
- A personal **"Your Qualification"** status strip below the hero (status badge + due date).
- Action cards with **short titles**: Class Signups, Failed Runs, Run Requests, Upcoming Classes,
  Recent Comments, Overdue.
- Chart widgets (Qual Status doughnut, Runs Trend bar) need **top padding** and must stay **within
  the left/right margins** (padded via `.fi-wi-widget`, since they share a parent with the hero).
- "Class Signups" replaced "Pending Approvals" on the dashboard.

### General
- **Astellas palette:** magenta #A4123F (primary), danger #C8102E, gold #E0B83C/#E8C24A, purple
  #6B2C91, success green #2E7D5B, charcoal #1C1C21/#15151A.
- Buttons: **solid magenta** at all states (no pink), squared-ish radius.
- **Sidebar:** narrower than Filament default (14rem vs the stock ~20rem/320px), smaller nav font
  (~12.5px items / ~10.5px group labels), smaller icons. Must still **collapse to icon-only**.
- **Light-theme contrast:** several elements used `var(--gqs-text)` which resolved too light (grey
  on white). Dropdowns and the Manage menu needed explicit dark text (#1A1A1F) in light theme. If
  you find more grey-on-white, sweep them the same way — `--gqs-text` in light mode is suspect.
- A role-gated **"Manage"** dropdown in the header (left of global search) collects admin items.

---

## 5. MISTAKES MADE (so we don't repeat them)

1. **Guessed Filament class names instead of inspecting.** `.fi-page-dashboard`,
   `.fi-page-footer-widgets`, the wrong topbar wrappers — all guesses that did nothing. The DOM walk
   would have shown the truth in 30 seconds. **Biggest single time-sink.**
2. **Edited the wrong element repeatedly.** Spent several rounds nudging the *admin* brand partial
   when the user was looking at the *login bar* brand (different file/element entirely). Confirm
   *which* element before editing.
3. **Fought the cascade in `theme.css`** with `!important` for things that needed head-injection.
4. **Silent build** (`npm run build --silent`) hid failures → stale CSS served for many rounds.
5. **Forced `width !important` on the sidebar**, which silently disabled the collapse feature.
6. **Repositioned the collapse button with CSS**, which broke the collapse that worked natively.
7. **Overrode `getPasswordFormComponent()` without re-adding the forgot-password hint**, so the
   link vanished.
8. **Used `calc(var(--sz))` in box-shadow** for star glow — it collapsed; compute in PHP and inline.
9. **Trusted lang files for label changes** — not read in v5; use custom page classes.
10. **Used a bare model class name in a Pages-namespace file** → resolved to wrong namespace → 500.
11. **Didn't get the actual error first** on 500s early on. The fast path is always:
    `grep -i "production.ERROR" storage/logs/laravel.log | tail -3` → the message names the exact
    class/line → one-line fix.

---

## 6. WORKING PROCESS (the loop that's efficient)

1. Pull `main` before editing; read the current file before changing it.
2. For a **layout/visual** issue: if not certain of the element, **ask for the console DOM walk**
   first. Don't guess class names.
3. For a **500**: ask for `grep production.ERROR … | tail -3` — fix the exact line.
4. Make the change, push to `main`, tell the user the one deploy command.
5. Prefer **head-injection** (`head-styles.blade.php`) for stubborn overrides; prefer `:has()` for
   state-scoping; prefer **adding a controlled element** over recoloring Filament's.
6. When a method/namespace is uncertain in v5, **verify before pushing** (the type-mismatch and
   namespace fatals were all avoidable with a quick check).
7. Keep auth/label text changes in **custom page classes**, not lang files.
8. After a risky change, suggest the specific **console check** that confirms it landed, so we don't
   round-trip on "still not fixed."

---

## 7. STILL OPEN (features, not polish)

- **Messaging dropdown** in the header next to the bell (default plan: announcements board).
- **Operator self-reschedule** from the My Qualification page (currently admin/QCM only).
- Email delivery for notifications (pending Postfix relay).
- Validation package content (GAMP 5, to be refit to Astellas templates when provided).

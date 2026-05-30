# GQS — Master Plan & Complete Requirements (v1)

**Last updated:** May 30, 2026
**Purpose:** The single source of truth for what the MATC Gowning Qualification System (GQS) is,
everything the owner has asked for across all sessions, the target quality bar (Monday.com-level
tracking, GMP-grade rigor), the open-source/Laravel pieces to lean on, and the build roadmap.
Read this together with `GQS_DESIGN_AND_LESSONS_v1.md` (design language + hard-won UI lessons).

---

## 1. WHAT THIS SYSTEM IS

A 21 CFR Part 11-minded, GAMP 5 custom application that is the authoritative record for the full
cleanroom gowning qualification lifecycle at MATC (Astellas). It centralizes:

- Personnel + their qualification status and due dates (200+ people)
- The gowning **class** (LMS-sourced completion, prerequisite for initial runs)
- Cleanroom **qualification run** scheduling, reservations, and run-result recording
- **Microbiological sampling** (fingers / chest / forearms), **incubation**, **results release**
- **QA review + electronic sign-off** (the authoritative "Completed" gate)
- Notifications, reporting, CSV import, audit trail, and the validation package

**The mental model the owner wants:** a Monday.com / kanban-grade tracking surface where a person's
card flows visibly through every stage, status-flipping is central, and admins can configure almost
everything from Settings without code.

---

## 2. THE FULL GMP WORKFLOW (the spine of the app)

Each person's qualification is a state machine. Stages (enum `WorkflowStage`):

1. **Class Pending** — needs the gowning class
2. **Class Complete** — class done (LMS import or Class Board), ready to schedule
3. **Run Scheduled** — reservation approved for a run slot
4. **Run Performed** — gowned through the cleanroom
5. **Samples Taken** — fingers / chest / forearms sampled
6. **Incubating** — plates in incubation (timer = `incubation_days` setting)
7. **Results Released** — lab/LIMS results in
8. **QA Review** — in the QA approval queue
9. **QA Sign-off (Complete)** — QA electronically signs → **Qualified**, due_date = pass + cycle
- **Failed** (off-pipeline) — failed run → QA determination (retrain vs requalify)

**Qualification rules (confirmed):** initial = 3 successful runs (qualified 12 months); annual = 1
run on/before due; lapsed (past due + grace) = redo initial; class completion prerequisite for
initial runs; class taken once at initial unless `class_repeats_annually`.

---

## 3. COMPLETE REQUIREMENTS LIST (everything asked for, all sessions)

### 3.1 Already built (verify each still works after every deploy)
- Laravel 13 + Filament v5, Apache `/gowning/`, AWS RDS Postgres, GitHub deploy via `deploy.sh`.
- Qualification rules engine reading configurable Settings.
- Personnel, qualifications, runs, run_slots, reservations, class_completions, class sessions,
  enrollments, audit_logs, comments, settings, role_capabilities, notifications, announcements.
- 11-role × 14-capability **editable** permission matrix (DB-driven).
- Public self-service portal (landing, classes, run slots, calendar, register, Sign In) with the
  cosmic starfield design.
- Cosmic login page (backdrop, nebula, stars, Title-Case labels via custom page classes).
- Dark charcoal header (both themes) with brand + star glow; collapsible narrow sidebar.
- Full-bleed cosmic dashboard hero + stat cards + my-status strip + action cards + charts.
- CSV import (personnel) with mapping/preview/dedup.
- Reports (overdue/upcoming/passfail/completions) + LIMS CSV export.
- Notifications wired (run request → scheduling; failed run → QA; new account → admin).
- Operator self-reschedule; announcements/messaging dropdown.
- Status Board (global 10-stage kanban), Run Reservations (grouped-per-day list), Class Board
  (enrollment kanban), QA Sign-off Queue.
- Expanded Settings (incubation, sampling sites, QA/e-sig toggles, notify days, org/site) +
  reference-list resources (Departments, Job Titles, Cleanrooms, Sampling Sites).
- All pages share the page-hero header + shared GQS component library (stat cards, panels, tables).

### 3.2 Explicitly requested, still to finish / polish
- **Modals everywhere a full page is overkill** — the owner repeatedly asked that action buttons
  open **modals**, not navigate to separate pages. Tighter, faster, intuitive. Audit every page
  action and convert page-navigations to modal/slide-over where it fits.
- **Fix "card within a card"** — modals/sections currently double-wrap (a Filament section inside
  our panel inside the modal). Flatten so there's one clean container.
- **Class details need real depth** — class catalog, sessions, rosters, attendance, prerequisites,
  capacity, recurring generation, completion → workflow advance, per-class history.
- **Faster configuration from Settings** — manage all vocab/lists and rules from one place; the
  owner wants to configure things without new code.
- **Standard design on every page** (page-hero + shared components); **Monday.com-level tracking.**
- Audit-trail **review screen** (QA-facing, filterable).
- **Electronic signature capture** modal on QA sign-off (two-component: meaning + manifestation).
- Run-result recording that **feeds the incubation timer** and auto-advances stages.
- Kanban **click-to-edit** (open a card detail/edit modal, not just drag).
- Email delivery once Postfix relay is ready (reminders 60/30/7).
- Attendance sheet refinement when the owner provides the paper form.
- Validation package content (GAMP 5, refit to Astellas templates).

### 3.3 Style/voice constraints (owner preferences)
- "SOP" abbreviation convention; **no em dashes** (use commas/periods); Title Case on UI labels.
- Astellas palette: magenta **#A4123F** (primary buttons — confirmed favorite), danger #C8102E,
  gold #E0B83C/#E8C24A, purple #6B2C91, success green #2E7D5B, charcoal #1C1C21/#15151A.
- Brand text **#444**, baseline-aligned to the logo wordmark, bottom-aligned to header icons.
- Concise subtitles, no verbose descriptions. Solid magenta buttons (no pink shades).

---

## 4. OPEN-SOURCE / LARAVEL PIECES TO LEAN ON

> **Constraint:** Claude's sandbox cannot run composer/npm (packagist + npm blocked). Any package
> below must be `composer require`d **on the E3 server** by the owner, then wired in code Claude
> pushes. Plan each as an explicit server step. Prefer building on these rather than reinventing.

- **spatie/laravel-activitylog** + a Filament viewer (`rmsramos/activitylog` or `z3d0x/filament-logger`)
  — proper audit trail + a ready review screen. Replaces our hand-rolled audit where sensible.
  *Part 11 relevance: computer-generated, attributable, time-stamped, preserves prior values.*
- **spatie/laravel-settings** (+ Filament settings plugin) — typed, validated settings store; a
  cleaner backbone than our key/value `Setting` for the growing config surface.
- **Filament Spatie Media Library** — attach the paper attendance form, SOPs, signed records, plate
  photos to runs/classes.
- **awcodes/filament-tables-export** or native Filament export — robust CSV/XLSX exports for reports.
- **Filament native: Infolists, Relation Managers, Slide-overs, Wizards, Tabs, Repeaters,
  Notifications, Global Search, Widgets** — use these aggressively; they ARE the Monday.com toolkit.
- SortableJS (already used) for kanban drag.

(If the owner cannot add composer packages, everything degrades gracefully to our hand-rolled
audit_logs + key/value settings + manual CSV export, which already exist.)

---

## 5. INFORMATION ARCHITECTURE (navigation)

**Sidebar groups** (keep tight, role-gated, modal-first):
- **Qualifications:** Status Board, QA Sign-off Queue, Qualifications (read-only, engine-driven),
  My Qualification (operator).
- **Scheduling:** Run Day Roster, Run Reservations (per-day list), Class Board, Run Slots, Sessions.
- **Header "Manage" dropdown** (admin/config, modal-first): Personnel, Users & Approvals, Roles &
  Permissions, Settings, Import, Reports, Announcements, Audit Trail, and the reference Lists
  (Departments, Job Titles, Cleanrooms, Sampling Sites, Class Catalog).
- **Header:** Status/announcements (megaphone), notifications (bell), avatar/theme.

---

## 6. DESIGN SYSTEM (every page conforms)

- **page-hero** partial: magenta icon badge + Title-Case title + short subtitle + bottom border.
  Filament's own page heading is hidden on pages that use it (`:has(.pg-head) .fi-header{display:none}`).
- **Shared GQS components** (in head-styles): `.gqs-stats/.gqs-stat` (gradient stat cards w/ watermark
  icons), `.gqs-panel/.gqs-panel-head/.gqs-panel-body`, `.gqs-tbl`, `.gqs-pill-*`, `.gqs-empty`.
- **Modals/slide-overs** for create/edit/quick-actions. **One container only** — never a Filament
  Section inside a .gqs-panel inside a modal. In modals, let Filament's form render plainly.
- Dashboard hero (cosmic) is **dashboard-only**; other pages use the clean page-hero.
- Dark header both themes; collapsible 14rem sidebar; #444 brand; solid magenta buttons.
- All dropdown panels: dark text + visible icons in light theme (topbar light-text rule must NOT
  bleed into `.fi-dropdown-panel`).

---

## 7. DATA MODEL (target)

Core: personnel, users (+approval/role), qualifications (+workflow_stage, stage_changed_at),
qualification_runs (+result, cycle_type, incubation_started_at, results_released_at, qa_signed_at,
qa_signed_by, qa_notes), run_slots, reservations, class catalog (training_classes), class_sessions,
class_enrollments, class_completions, import_batches, audit_logs/activity_log, qualification_comments,
announcements, settings, role_capabilities, notifications.
Reference vocab: departments, job_titles, cleanrooms, sampling_sites, training_class_catalog.

**Sampling detail (to add):** a `run_samples` table — one row per (run, site) with result pass/fail,
plate/LIMS id, read date — so sampling is first-class, not a checkbox.

---

## 8. BUILD ROADMAP (ordered)

**Phase A — Tighten what exists (UI debt):**
1. Convert page-navigation actions to **modals/slide-overs** across resources.
2. Fix **card-within-card** in all modals/panels (flatten containers).
3. **Class details** depth: catalog + sessions + roster + attendance + prerequisites in modals.
4. Kanban **click-to-edit** card detail modal on Status/Class boards.

**Phase B — GMP completeness:**
5. **run_samples** model + sampling capture modal on Run Day Roster (per site pass/fail).
6. Run-result recording → starts incubation timer → auto-advances stage → results release →
   QA queue. Wire the timers/notifications.
7. **E-signature capture** modal on QA sign-off (meaning + manifestation, stamped to record).
8. **Audit Trail review screen** (install spatie/activitylog on server, wire Filament viewer).

**Phase C — Config & polish:**
9. Move remaining config into Settings (spatie/laravel-settings on server) incl. all reference
   lists, notification schedule, cleanroom/sampling vocab, class catalog.
10. Reports expansion + XLSX export + saved views.
11. Media attachments (attendance forms, SOPs, plate photos) via Spatie Media Library.
12. Email delivery (Postfix), then validation-package content.

**Always:** read the DOM before guessing CSS; get the exact error before fixing a 500; one feature
per commit; verify v5 namespaces before pushing; deploy = `sudo bash deploy.sh`.

---

## 9. PART 11 / GAMP 5 CHECKLIST (keep satisfied)

Unique per-person login; RBAC (editable matrix); computer-generated, attributable, time-stamped
audit trail that preserves prior values; no hard deletes (soft deletes); two-component e-signatures
with meaning + manifestation linked to the signed record; ALCOA+; America/New_York timestamps;
validation package (IQ/OQ/PQ) versioned with code. Technical controls are built in; validation
itself is QA-governed.

# GQS, Open Issues & Things We May Not Have Fully Addressed

**Last updated:** 30-MAY-2026
**Purpose:** An honest, working list of what is unresolved, uncertain, or untested, so nothing gets
lost between chats. Pair with `GQS_MASTER_PLAN_v4.md` (the plan) and `DESIGN_AND_LESSONS_v1.md`.
This is deliberately frank: items here are NOT confirmed done.

---

## A. Needs the owner or the server (Claude cannot do these in the sandbox)

1. **Composer packages** (Claude cannot run composer; packagist is blocked). To wire any of these,
   the owner runs `composer require` on the E3 server first, then Claude wires the code:
   medialibrary (attachments), laravel-settings, filament-excel, fullcalendar, laravel-backup,
   horizon + schedule-monitor, reverb. None are installed yet.
2. **Postfix mail relay** is not resolved, so emails are **queued** (`queued_emails`) and not sent.
   `php artisan gqs:flush-emails` sends what is queued once the relay works. Reminder/account/
   password-reset email firing points still need scheduled jobs / auth hooks.
3. **Cron** must be active on the server so the daily automation (auto-scheduler, lifecycle lapse,
   incubation advance, email flush) runs without a page load. Confirm the cron line is installed.
4. **`php artisan gqs:dedupe-personnel`** (dry-run) then `--commit`: the personnel import created
   duplicate rows (e.g. AWEBER, AABRAFI, NESCAMILLA vs MESCAMILLA, NPATHAMMAVONG vs NPATHAMNOYONG,
   FKUMAR "Parveen" vs "Praveen"). The owner needs to run this and review before committing.
5. **Staff capability flags** (`can_sample` / `can_teach` or the equivalent): if these are not set on
   the QCM/trainer users, the analyst/instructor assignment dropdowns will be empty. Confirm seeded.
6. **Real attendance-form metadata** for Settings: the actual Doc # / Revision / Title to print on
   FORM-AST-36513 (currently placeholder-driven). Provide the official values.
7. **Official Astellas favicon (.ico)** for the browser tab.
8. **Manager IDs in Settings** (`qcm_manager_id`, `qa_manager_id`) must be set or the team views and
   approval ownership have no manager context.

## B. Validation package (QA-governed, content not yet written)

9. The `/validation` folder holds the GAMP 5 structure but the **content** (URS, FS, DS, RA, RTM,
   IQ, OQ, PQ, VSR, SOPs) is not written and must be **refit to Astellas templates** when provided.
   This is a QA-led activity; GQS is built to be validatable, but validation itself is not done.
10. **Part 11 procedural controls** (SOPs for access management, backup/restore, audit-trail review,
    e-signatures, change control) are listed but not authored.

## C. Behavior that may need confirmation / could be wrong

11. **Lapsed grace window**: assumed the day after due_date unless QA defines a grace period. The SOP
    (annual requal within 30 days of due) suggests a 30-day grace, currently a Setting; confirm the
    default and that "lapsed" triggers correctly relative to it.
12. **Annual requal excursion path**: SOP allows EITHER one additional requal run OR a full 3-run
    requalification, decided by the NC evaluation. The QA Determination supports 1/2/3, confirm the
    UI guides the reviewer to the right choice and records the rationale.
13. **Class taken once ever** (`class_on_file` persists across cycles) unless QA mandates retraining,
    confirm the retraining flag actually forces a new class when set.
14. **Failed-run handling**: failed runs do not count toward passes and do not reset prior passes,
    confirm this matches QA's intent for both initial and requal.
15. **Negative control growth** initiates an NC, confirm the workspace/NC tracker captures it.
16. **Incubation days default (8)** and **action level (5 CFU/plate)** live in Settings, confirm the
    numbers match the SOP and current practice.
17. **Two-person rule**: the trainer who performs sampling must not be the person being qualified,
    and QCM (not MFG self) reviews, confirm the app prevents self-qualification where it can.

## D. UI / UX still rough or untested

18. **QA Team View** does not exist yet (only QCM Team View). Mirror it.
19. **Instructor assignment from the team view** is not built (only run-day analyst assignment).
20. **Kanban bulk actions** (multi-select -> assign/mark) and **saved board filters/views** not built.
21. **Card-within-card**: mostly flattened, but any modal still wrapping a Filament Section inside a
    `.gqs-panel` needs a sweep.
22. **Timeline** still has the two views (Due Window, Full Cycle); deeper rebuild (day/week/month/year
    granularity toggle, classes-with-signups view, more groupings) is wanted. Width + fill-to-bottom +
    deadspace are fixed as of 30-MAY-2026, but the runtime height-fit relies on a JS measurement;
    verify on the owner's screen.
23. **Runtime board height-fit** (kanbans + timeline) is a JS fill-to-bottom; if a board lands a hair
    short/long on a specific monitor, the 10px bottom gap may need a nudge. Pre-JS CSS fallback exists.
24. **Light-theme contrast**: dropdown panels were swept for grey-on-white, but if any `--gqs-text`
    resolves too light on white, sweep it (the `--gqs-text` light-mode value is the usual suspect).
25. **Mobile / narrow screens**: kanban headers wrap below 820px; the boards are desktop-first.
    Operator self-service on a phone is not deeply tested.

## E. Data integrity / import

26. **Personnel CSV** has trailing-whitespace usernames, duplicate stub rows, and system/role
    pseudo-accounts (e.g. `_ANALYST`, `_SCHEDULER`, `SYSTEM`). Import should skip the underscore
    pseudo-rows; confirm it does, and that the dedupe command handles the whitespace variants.
27. **Idempotent re-import**: confirm re-running an import does not double-create (dedup key).
28. **Historical qualification seeding**: bulk seeding real current status for ~370 people (so the
    board reflects reality on day one) is a planned import path; confirm it exists and is used.

## F. Known compliance gaps now CLOSED (kept here for the audit trail)

- **30-MAY-2026:** Class Board drag into Attended / Pending QA / Completed used to commit that status
  (Completed even advanced the qualification) with no e-signature/QA. Now blocked.
- **30-MAY-2026:** The Training Class > Sessions > Attendance "Present" toggle used to set Completed
  and advance the qualification immediately. Now it only opens the proper Class Scheduler attendance
  sheet (draft until submitted with the trainer e-signature).
- **30-MAY-2026:** Page-header bar was charcoal/white-on-white regression in light theme. Fixed.

## G. QCM Incubation / Plate-Read Workspace (new, see Master Plan Section 10)

29. Entire workspace is a **concept**, not built. Needs: the weekly LIMS export format from the owner,
    the worklist/sample data model, the import mapping, the bench kanban + team assignment, and the
    results-bridge back to `qualification_runs`. High value for QCM, removes duplicate data entry.

---

## How to verify the system end-to-end (suggested smoke test after deploy)

1. Register a new person on the public portal (Employee-ID-first), confirm it links to personnel.
2. Schedule a class session, sign the person up, mark them Present (draft), confirm their status
   stays "Signed Up" on Class Reservations and the Class Board. Submit attendance with the trainer
   e-signature, confirm they move to Pending QA, then QA-approve and confirm Class Complete + a
   ClassCompletion record + run-eligibility.
3. Let the auto-scheduler book a run, Mark Performed, watch Incubating -> Awaiting Results, Enter
   Results (pass), QA Sign-off, confirm Qualified with due = pass + 12 months.
4. Enter a failing result, confirm Failed + auto-NC + QA Determination books the requal runs.
5. Check each board groups correctly (Status Board: Department/Job Title/Cycle Type/Due Window;
   Class Board: Department/Class/Instructor/Session Date) and that the boards fill to the page bottom.
6. Confirm page headers are light/readable in light theme and dark in dark theme.

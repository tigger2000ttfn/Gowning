# GQS, Master Plan & Complete Requirements (v2)

**Last updated:** May 30, 2026
**Supersedes:** v1. Adds the staff resourcing/assignment layer, Veeva linkage, the full
auto-scheduling engine, the yearly lifecycle automation, and reflects everything built since v1.
Read with `GQS_DESIGN_AND_LESSONS_v1.md` (design language + UI lessons).

---

## 1. WHAT THIS SYSTEM IS

A 21 CFR Part 11-minded, GAMP 5 custom application that is the authoritative tracking record for
the full cleanroom gowning qualification lifecycle at MATC (Astellas). It now spans two axes:

- **The people being qualified** (operators/trainees): their class, runs, results, status, due dates.
- **The staff doing the work** (QC Micro analysts, QA reviewers): who is assigned to run days and
  classes, team workload views, and who owns each batch of approvals.

Mental model the owner wants: a Monday.com / kanban-grade tracking surface, status-flipping central,
admins configure almost everything from Settings, and managers plan and assign their own teams.

---

## 2. THE FULL GMP WORKFLOW (the spine)

Per-person qualification state machine (enum `WorkflowStage`):

1. Class Pending, 2. Class Complete, 3. Run Scheduled, 4. Run Performed, 5. Incubating,
6. Awaiting Results, 7. Results Released, 8. QA Review, 9. QA Sign-off (Complete).
Off-pipeline: Failed (QA determination).

Incubation is time-driven off the run-performed date (default 8 days, Settings), auto-advancing
Incubating to Awaiting Results. Incubation itself happens in LIMS; we only track pass/fail + worklist.

**Rules (confirmed):** initial = 3 runs (qualified 12 months); annual = 1 run on/before due; lapsed
(past due + grace) = automatic 3-run requalification; class is taken ONCE EVER (`class_on_file`
persists across cycles) unless QA mandates retraining; QA determination sets the requal run count
(1, 2, or 3) and whether retraining is required.

---

## 3. STAFF RESOURCING & ASSIGNMENT LAYER (NEW in v2)

The new dimension: QCM and QA managers plan and assign their own teams.

- **QC Micro analysts** are assigned to **run days** (who runs the cleanroom session) and to
  **class sessions** (who teaches). A run day / session carries an assigned analyst (or several).
- **QCM manager team view:** see their analysts, each one's assigned run days/classes, and workload
  (how many people/sessions each is responsible for) for resource planning.
- **QA reviewers** are assigned ownership of **batches of approvals** (the QA Sign-off Queue can be
  partitioned/assigned, so a QA lead knows who owns which pending sign-offs).
- **QA lead team view:** who is responsible for which pending approvals, workload balance.
- All assignment lives under the **Manage dropdown** (manager-facing), with team views.
- **Staff roles:** distinguish "analyst" staff users from operators. Assignment uses `users`
  (staff accounts), not `personnel` (the qualified population), though a person may be both.

**Veeva linkage:** a completed run carries a **Veeva document/report number + link** so QA can find
and review the source record in Veeva. Attached at results entry / QA review. Not transcribed, just
linked (same philosophy as the TrackWise NC link).

---

## 4. COMPLETE REQUIREMENTS LIST

### 4.1 Built and live
- Laravel 13 + Filament v5, Apache `/gowning/`, AWS RDS Postgres, GitHub deploy via `deploy.sh`.
- Qualification rules engine reading configurable Settings.
- Full data model: personnel (+detail fields), users (+approval/role), qualifications (+workflow
  stage, class_on_file, qa_recommendation), qualification_runs (+lims_worklist, incubation/results/
  QA timestamps), run_slots, reservations (+lims_worklist), run_samples, class catalog + sessions +
  enrollments + completions, electronic_signatures, non_conformances, queued_emails, import_batches,
  audit/activity log, comments, settings, role_capabilities, notifications, announcements, and
  reference vocab (departments, job_titles, cleanrooms, sampling_sites).
- 11-role x 14-capability editable permission matrix.
- Public self-service portal + cosmic login.
- Dark header both themes, collapsible 14rem sidebar, full-bleed cosmic dashboard.
- Three workflow-ordered sidebar groups: Qualifications, Classroom, Scheduling. Manage dropdown
  sectioned (Records, Lists, Setup & Settings, Compliance) with frequent items standalone on top.
- Status Board (global kanban, per-person cards with run-progress pips), Run Reservations (per-day
  list), Incubation Board (time-driven), QA Sign-off Queue (e-signature), Non-Conformance tracker
  (TrackWise link, mold/bacteria trending), Class Board, Class Reservations, Run Day Roster (Mark
  Performed + Enter Results), Run Not Scheduled watchlist, Audit Trail review.
- GMP automation chain, end to end: class attendance -> Class Complete (sets class_on_file) ->
  auto-book run -> Run Scheduled -> Mark Performed -> Incubating (timer) -> Awaiting Results (auto)
  -> Enter Results -> QA Review or Failed (+auto NC) -> e-sign -> Qualified.
- Auto-scheduling engine: books Class-Complete people into next available run day, per-day capacity
  cap (Settings), bumps when full, Cancel Day cascade-reschedules + notifies. Daily schedule +
  opportunistic on page load. QA determination auto-books requal runs.
- Operator self-service reschedule (next-available or pick) from My Qualification.
- Yearly lifecycle automation: past-due auto-lapse into 3-run requal, respecting class_on_file.
- Part 11 audit trail (spatie/laravel-activitylog) + two-component e-signatures.
- Notifications in-app; emails queued (queued_emails) until Postfix relay, gqs:flush-emails to send.
- Manual first-time seeding: Personnel form with full Qualification Setup section.
- CSV personnel import; reports + LIMS export.

### 4.2 To build (this phase + next)
- **Staff assignment to run days and class sessions** (analyst owner field + assignment UI).
- **QCM manager team view:** analysts, their assignments, workload.
- **QA approval ownership:** assign QA reviewers to pending sign-offs; QA lead team view.
- **Veeva document number + link** on completed runs, visible in QA review.
- Kanban click-to-edit card detail/edit modal (Status/Class boards).
- Convert remaining page-navigation actions to modals/slide-overs.
- Class details depth (rosters, prerequisites, recurring generation polish).
- Media attachments (Spatie Media Library, server composer require) for forms/SOPs/plate photos.
- Reports expansion + XLSX export + trending mini-reports (mold/bacteria by organism/site/time).
- Email delivery once Postfix relay ready; validation package content (GAMP 5).
- Cron line on server so scheduled commands run without page loads.

### 4.3 Style/voice constraints
- "SOP" convention; NO em dashes (commas/periods); Title Case UI labels; concise subtitles.
- Astellas palette: magenta #A4123F (primary), danger #C8102E, gold #E0B83C/#E8C24A,
  purple #6B2C91, success green #2E7D5B, charcoal #1C1C21/#15151A. Solid magenta buttons.
- Brand text #444, baseline-aligned to logo, bottom-aligned to header icons.

---

## 5. INFORMATION ARCHITECTURE (navigation)

- **Qualifications:** Status Board, Run Reservations, Incubation, QA Sign-off Queue,
  Non-Conformances, Qualifications, Qualification Runs, Personnel.
- **Classroom:** Class Board, Class Reservations, Gowning Classes.
- **Scheduling:** Run Slots, Run Day Roster, Run Not Scheduled.
- **My Qualification** (operator, top, ungrouped).
- **Manage dropdown** (manager/admin, sectioned): top = Reports, Import, Announcements; Records;
  Lists; Setup & Settings; Compliance (Audit Trail). NEW: **Team & Assignments** section
  (QCM Analyst Assignments, QCM Team View, QA Approval Assignments, QA Team View).
- Header: Manage dropdown, announcements (megaphone), notifications (bell), avatar/theme.

---

## 6. DESIGN SYSTEM

- page-hero partial on custom pages; shared GQS components (gqs-stat cards, gqs-panel, gqs-tbl,
  gqs-pill, gqs-empty) in head-styles. Modals/slide-overs for create/edit; one container only
  (card-within-card flattened in modals). Full-bleed kanbans via :has(.sb-fullbleed). Dark header
  both themes; #444 brand; solid magenta buttons. Light-theme dropdown contrast handled.

---

## 7. DATA MODEL (target, v2 additions in bold-ish)

Core qualification axis: personnel, users, qualifications, qualification_runs, run_slots,
reservations, run_samples, class catalog/sessions/enrollments/completions, electronic_signatures,
non_conformances, queued_emails, audit/activity log, comments, settings, role_capabilities,
notifications, announcements, reference vocab.

NEW staff/resourcing axis:
- run_slots.assigned_analyst_id (FK users) , class_sessions.assigned_instructor_id (FK users).
- qualifications/qualification_runs.qa_owner_id (FK users) for approval ownership.
- qualification_runs.veeva_doc_number + veeva_url for QA review linkage.
- Optionally a staff_assignments table if many-to-many assignment is needed later.

---

## 8. BUILD ROADMAP (ordered)

**Phase D, Resourcing (current):**
1. Assigned analyst on run days + class sessions; assignment UI under Manage.
2. QCM team view (analysts + assignments + workload).
3. QA approval ownership + QA team view.
4. Veeva doc number + link on completed runs, shown in QA Sign-off Queue.

**Then:**
5. Kanban click-to-edit modals.
6. Convert remaining actions to modals.
7. Media attachments (Spatie Media Library).
8. Reports/XLSX + mold-bacteria trending.
9. Email delivery (Postfix) + validation package.

**Always:** add schema automatically when needed and wire it up. Read DOM before guessing CSS; get
the exact error before fixing a 500; verify v5 namespaces/APIs against vendor source before pushing;
one feature per commit; deploy = `sudo bash deploy.sh`.

---

## 9. PART 11 / GAMP 5 CHECKLIST

Unique per-person login; RBAC (editable matrix); computer-generated, attributable, time-stamped
audit trail preserving prior values (spatie/activitylog); no hard deletes (soft deletes);
two-component e-signatures (meaning + manifestation) linked to the signed record; ALCOA+;
America/New_York timestamps; external record linkage (TrackWise NC, Veeva docs) rather than
transcription; validation package (IQ/OQ/PQ) versioned with code. Technical controls built in;
validation is QA-governed.

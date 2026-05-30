# GQS, Master Plan & Complete Requirements (v4)

**Last updated:** 30-MAY-2026
**Supersedes:** v3. Adds the QCM Incubation / Plate-Read Workspace concept, records the
kanban/timeline/header work and the attendance-draft hardening, and refreshes the roadmap.
Read with `docs/DESIGN_AND_LESSONS_v1.md` (design language + hard-won UI lessons) and
`docs/GQS_OPEN_ISSUES.md` (the honest open-issues list).

---

## 1. THE NORTH STAR

GQS is the authoritative, 21 CFR Part 11-minded, GAMP 5 system of record for the entire cleanroom
gowning qualification lifecycle at MATC (Astellas). The bar is not "a tracker." The bar is: a
manager opens GQS and the system has already done the scheduling, advanced the stages, flagged the
exceptions, and queued the notifications. People manage exceptions; the system runs the routine.
The aim is to be better than Monday.com for this domain, with GMP rigor Monday cannot offer.

Three axes:
- **The qualified population** (operators/trainees): class -> runs -> sampling/incubation -> results
  -> QA sign-off -> qualified -> yearly requal.
- **The staff doing the work** (QC Micro analysts, QA reviewers): teams, managers, assignment of run
  days/classes/approvals, workload views.
- **(NEW, planned) The QCM bench workflow**: a dedicated Incubation / Plate-Read workspace that
  mirrors the LIMS worklist, so QCM enters reads once and GQS picks the results up (Section 10).

---

## 2. THE FULL GMP WORKFLOW (the spine, as built)

Per-person state machine (`WorkflowStage`): Class Pending -> Class Complete -> Run Scheduled ->
Run Performed -> Incubating -> Awaiting Results -> Results Released -> QA Review -> QA Sign-off
(= Qualified). Off-pipeline: Failed -> QA Determination.

- **Class attendance** is recorded on a class session and is **draft until submitted**. Marking a
  trainee Present/No-Show only sets an in-memory intent; nothing on the operator changes. On
  **submit** (with the trainer e-signature) the attendees move to Pending QA. **QA approval**
  (-> Completed) is what sets `class_on_file`, writes the ClassCompletion, and advances the person to
  Class Complete. Attendance/completion can NOT be set by dragging a Class Board card or by any other
  shortcut (both leaks were closed 30-MAY-2026).
- **Auto-scheduler** books Class-Complete people into the next available run day within the per-day
  capacity cap (Settings), bumping to the next day when full, auto-approving.
- **Mark Performed** records the run, moves to Incubating, stamps the incubation timer.
- **IncubationAdvancer** promotes Incubating -> Awaiting Results when `incubation_days` elapse off the
  performed date (incubation itself is in LIMS; we track worklist + pass/fail).
- **Enter Results** routes pass -> QA Review, fail -> Failed (+ auto Non-Conformance).
- **QA Sign-off** (two-component e-signature) completes -> Qualified, due = pass + cycle.
- **QA Determination** on a failure sets requal run count (1/2/3) + retraining flag, then auto-books.
- **LifecycleAdvancer** lapses past-due (+ grace) into an automatic 3-run requalification,
  respecting `class_on_file`.

**Rules:** initial = 3 runs (qualified 12 months); annual = 1 run on/before due; lapsed = redo
initial (3 runs); gowning class taken once ever unless QA mandates retraining.

---

## 3. TEAMS & RESOURCING (as built)

- Two fixed teams: **QC Micro** and **QA**. Users carry `team` (qcm/qa) + `is_team_manager`.
  Managers set in Settings (`qcm_manager_id`, `qa_manager_id`).
- **Run days** carry an assigned QC Micro analyst; **class sessions** carry an assigned instructor.
- **QA approvals** carry an owner (`qa_owner_id`).
- **QCM Team View** (Manage): tabbed Overview / Table / Cards / Calendar; per-analyst workload,
  unassigned-run-day alerts, inline assign-analyst modal.
- **Veeva** document number + link on completed runs, surfaced in the QA Sign-off Queue.
- **TrackWise** NC link + **LIMS** worklist linkage (link, don't transcribe).

**Still to build here:** a mirror **QA Team View** (approvals by owner, workload, sign-off calendar);
assigning analysts to class sessions from the team view; per-analyst "my assignments" digest.

---

## 4. UX / DESIGN SYSTEM (as built + the standard every page must meet)

- **page-hero** on every custom page AND every resource list page (via `GqsListHero`). The bar is a
  **clean theme-aware header** (light surface + dark title in light theme; dark surface + light title
  in dark theme; magenta icon badge; magenta bottom accent). It is NOT charcoal, that was a regression
  fixed 30-MAY-2026. The charcoal is reserved for the Filament **topbar** only.
- **Kanban headers** (`.sb-headrow`, used by Status Board, Class Board, Timeline) have 32px side
  padding so they line up with the lanes; filter dropdowns are 40px tall (no clipped descenders).
- **Kanban + timeline boards fill to the bottom of the page at runtime** (measured height, re-fit on
  morph + resize), with lanes scrolling vertically inside; the horizontal scrollbar pins to the bottom.
- **Swimlanes** on Status Board (No Grouping / Department / Job Title / Cycle Type / Due Window) and
  Class Board (No Grouping / Department / Class / Instructor / Session Date). Due Window is ordered by
  urgency; Session Date chronologically.
- Shared GQS components in head-styles: `.gqs-stats/.gqs-stat`, `.gqs-panel`, `.gqs-tbl`,
  `.gqs-pill-*`, `.gqs-empty`, `.gqs-flbl/.gqs-fld`, `.gqs-tabs`, `.gqs-mini-btn`, swimlane `.sb-g*`.
- **Modals/wizards** for create/edit/quick-actions; one clean container, never card-in-card.
- **Astellas palette**: magenta #A4123F (primary buttons), danger #C8102E, gold #E0B83C/#E8C24A,
  purple #6B2C91, green #2E7D5B, charcoal #1C1C21/#15151A. NO em dashes; Title Case labels;
  concise subtitles; solid magenta buttons.
- **Printable** Astellas-themed PDFs (logo, landscape): roster + compliance report; FORM-AST-36513
  (Class Training Form) and FORM-AST-36749 (Gowning Qualification Approval) fillers.

---

## 5. INFORMATION ARCHITECTURE (current)

- **Qualifications:** Status Board, Run Reservations, Incubation, QA Sign-off Queue,
  Non-Conformances, Qualifications (Active Runs), Qualification Runs (Run Completions), Personnel,
  Timeline.
- **Classroom:** Class Board, Class Reservations, Gowning Classes & Dates.
- **Scheduling:** Run Day Setup, Run Day Roster, Run Not Scheduled.
- **My Qualification** (operator, top).
- **Manage dropdown** (sectioned): Reports, Import, Announcements; Records; Lists; Team &
  Assignments (QCM Team View, + future QA Team View); Setup & Settings; Compliance (Audit Trail).
- **Public portal:** Home, Gowning Classes, Run Days, Calendar, Register, Sign In.
- **(NEW, planned) QCM Incubation Workspace:** its own sidebar group / portal area (Section 10).

---

## 6. DATA MODEL (current)

personnel (+detail), users (+team, is_team_manager, approval, role), qualifications (+workflow_stage,
class_on_file, qa_owner_id, qa_recommendation), qualification_runs (+incubation/results/QA stamps,
lims_worklist_id, veeva_doc_number, veeva_url), run_slots (+assigned_analyst_id), reservations
(+lims_worklist_id), run_samples, training_classes (+detail), class_sessions (+assigned_instructor_id,
attendance_submitted_at/by), class_enrollments (status: signed_up/attended/pending_qa/completed/
no_show/cancelled/historical; attended_at, marked_by, qa_completed_*), class_completions,
electronic_signatures, non_conformances, queued_emails, automation_rules, import_batches,
activity_log, qualification_comments, settings, role_capabilities, notifications, announcements,
reference vocab (departments, job_titles, cleanrooms, sampling_sites, room_locations).

---

## 7. WHAT MAKES IT BETTER THAN MONDAY.COM (and where to push next)

### 7.1 Near-term polish (in flight)
- QA Team View; class-session instructor assignment from team view.
- Kanban bulk actions (multi-select cards -> assign day / mark performed) and saved board filters.
- Card-within-card sweep in any remaining modals.

### 7.2 Higher-value capability
- **Command palette / global quick-add** (jump to any person, quick-book a run).
- **Saved views & per-user board preferences** (persist filter/sort/group/columns per user).
- **Unified scheduling calendar** for run days + class sessions + due dates, drag-to-reschedule.
- **Per-role dashboards** with drill-down widgets (QCM workload, QA queue aging, overdue heatmap).

### 7.3 Laravel/Filament packages to explore (server `composer require`, Claude wires)
- **spatie/laravel-medialibrary** + Filament plugin: attendance sheets, SOPs, plate photos, signed
  PDFs attached to runs/classes/NCs.
- **spatie/laravel-settings** (+ Filament plugin): typed, validated settings.
- **pxlrbt/filament-excel** or native export: robust XLSX exports + saved exports.
- **saade/filament-fullcalendar** or **guava/calendar**: the unified scheduling calendar.
- **spatie/laravel-backup**: scheduled DB + file backups (GMP/Part 11), with an in-app
  restore-verification record for the validation package.
- **laravel/horizon** + queues + **spatie/laravel-schedule-monitor**: prove the daily automation ran
  (a validation artifact) once email/imports/exports move to queues.
- **laravel/reverb** + Echo: real-time board/queue updates.
- **barryvdh/laravel-dompdf** / **spatie/laravel-pdf**: server-rendered PDFs if we outgrow browser print.

### 7.4 GMP/compliance depth (the real moat vs Monday)
- Validation package content (URS/FS/DS/RA/RTM/IQ/OQ/PQ/VSR/SOPs) versioned with code, refit to
  Astellas templates; auto-generate the RTM from code annotations where possible.
- Scheduled-job monitor + backup-verification records as living IQ/OQ evidence.
- Periodic-review workflow (annual system review, e-signed) and change-control log tied to git releases.
- Data-integrity dashboards (ALCOA+): orphaned records, missing signatures, overdue reviews.
- Training-effectiveness analytics: pass/fail by class, by instructor, by cleanroom; mold vs bacteria
  trending by organism/site/time (the NC tracker already seeds this).

---

## 8. BUILD ROADMAP (ordered)

**Phase E, UX uniformity (current, mostly done):**
- Clean theme-aware page headers; kanban/timeline fill-to-bottom; swimlanes (incl. 4 new groupings);
  filter-dropdown + header-padding fixes. DONE this session.
- Remaining: QA Team View; instructor assignment from team view; bulk board actions; saved filters.

**Phase F, capability:**
- Unified scheduling calendar (drag-reschedule) feeding the auto-scheduler.
- Media attachments (medialibrary) for forms/SOPs/plate photos.
- Reports XLSX export + NC trending mini-reports + per-role dashboards.
- Real-time updates (Reverb).

**Phase G, QCM Incubation Workspace (NEW, Section 10):**
- Worklist model + weekly LIMS import + incubation/plate-read kanban + team assignment +
  results-back-to-GQS bridge.

**Phase H, compliance moat:**
- Backups + schedule-monitor as validation evidence; periodic review + change-control.
- Validation package content; auto-RTM; data-integrity dashboards.
- Email delivery (Postfix) + queued-email flush in production.

**Always:** add schema automatically and wire it up; verify Filament v5 namespaces/return-types
against vendor source before pushing; get the exact error before fixing a 500; read the DOM before
guessing CSS; one feature per commit; deploy = `sudo bash deploy.sh`.

---

## 9. PART 11 / GAMP 5 CHECKLIST

Unique per-person login; RBAC (editable matrix); computer-generated, attributable, time-stamped
audit trail preserving prior values (spatie/activitylog); no hard deletes (soft deletes);
two-component e-signatures (meaning + manifestation) linked to the signed record; ALCOA+;
America/New_York timestamps; external record linkage (TrackWise NC, Veeva docs, LIMS worklist)
rather than transcription; validation package (IQ/OQ/PQ) versioned with code; (planned) scheduled
backups + job-run monitoring as IQ/OQ evidence. Technical controls built in; validation QA-governed.

---

## 10. QCM INCUBATION / PLATE-READ WORKSPACE (NEW, for QC Micro)

> QC Micro does hard, meaningful, exacting work, the bench end of the whole qualification. They asked
> for a workspace of their own, separate from the qualification pipeline but with the same kanban /
> team-assignment feel, that tracks the LIMS side of things (worklists and sample statuses) so they
> do less duplicate data entry here. This is a first-class goal, not a nice-to-have.

### 10.1 What it is
A **separate but visually-similar workspace** inside GQS, scoped to the QC Micro team. It is the
analysts' daily board for the incubation + plate-read part of the process, organized around **LIMS
worklists** rather than around people. It runs in **tandem** with the main app: results entered here
flow back to the matching qualification runs in GQS and advance the main pipeline, so QCM enters a
read once instead of re-keying it.

### 10.2 The bench kanban (proposed stages)
Cards are **worklists** (or samples within a worklist). Stages mirror SOP-AST-30419:
1. **Worklist Created** (LIMS worklist exists / imported)
2. **Samples Logged** (QC samples + QC_INC_META present)
3. **Incubating** (timer = `incubation_days`; Day X of N badge)
4. **Reading / Enumeration** (plates being read)
5. **Results Entered** (CFU counts per site; pass/fail vs 5 CFU spec)
6. **Released to GQS** (pushed back to the qualification run; QA queue picks it up)
Off-pipeline: **Excursion / OOS** (>= action level -> NC + microbial ID), mirroring the main NC tracker.

### 10.3 Team assignment (same pattern as the main app)
- Each worklist/board card has an **assigned QCM analyst** (owner), with a QCM team workload view
  (who owns how many worklists, how many plates are due to be read today, unassigned alerts).
- Swimlanes by analyst / incubator / day, like the Status Board groupings.

### 10.4 Weekly LIMS import (less data entry, not real-time)
- QCM uploads a **weekly CSV export from LabWare LIMS** (worklist + sample statuses; e.g. worklist id,
  sample id, sample description Qual1/2/3 or Requal1/2, status, incubation start, read date, CFU).
- The importer (reuse the existing import module: upload -> map -> validate -> preview -> commit,
  idempotent, dedup, rejected-row report) **upserts** worklists/samples into the workspace, so analysts
  do not re-enter sample metadata. They only add what LIMS does not give (e.g. a read they did today).
- Explicitly **not real-time**; acknowledged trade-off. A future LIMS API (LabWare) could make it live;
  design the import boundary so an API can replace the CSV without changing the workspace.

### 10.5 Results bridge back to GQS (the tandem part)
- A workspace worklist links to one or more `qualification_runs` via `lims_worklist_id` (already on
  runs/reservations). When a worklist's samples are read and the run's result is determined
  (pass/fail), **Release to GQS** writes the run result + read date + Veeva/LIMS references onto the
  qualification run and advances the main pipeline (Awaiting Results -> Results Released -> QA Review,
  or -> Failed + NC). One entry point, no double keying.
- Result entry here is still **QCM result review**; QA approval (FORM-AST-36749) remains in GQS as the
  official gate.

### 10.6 Data model (proposed additions)
- **lims_worklists**: worklist_id, description, status, assigned_analyst_id (FK users), incubation_
  started_at, read_due_at, source (import/manual), import_batch_id, notes.
- **lims_worklist_samples** (or extend `run_samples`): worklist_id, sample_uid, description
  (Qual1/2/3, Requal1/2, QC_INC_META, NC), site (LF/RF/LL/RL/CH/NC), status, cfu_count, read_date,
  result (pass/fail), linked qualification_run_id.
- Link table or FK to `qualification_runs.lims_worklist_id` for the bridge.

### 10.7 Why separate (and why it makes QCM happy)
QCM's daily reality is LIMS-centric (worklists, incubators, enumeration, the 5 CFU spec), which is a
different shape from the qualification lifecycle. A focused workspace keeps their tools clean and fast,
mirrors the import they already do for LIMS, and removes duplicate entry, while the bridge keeps GQS the
single source of truth. It is the same kanban + team-assignment + import machinery we already have,
re-pointed at worklists.

### 10.8 Open questions for QCM (confirm before building)
- Exact columns/format of the weekly LIMS export (so the importer maps cleanly).
- Card grain: worklist-level or sample-level on the board (likely worklist with a sample sub-list).
- Which incubators/temperatures to model (30-35 C and 20-25 C per the SOP).
- Whether QCM wants the excursion/NC handling inline here or to keep using the main NC tracker.

# GQS, Master Plan & Complete Requirements (v3)

**Last updated:** May 30, 2026
**Supersedes:** v2. Captures the now-mature understanding of every workflow, the teams/resourcing
layer as built, the auto-scheduling + lifecycle automation as built, the page/UX system, and a
substantial forward-looking enhancements section (Laravel/Filament packages to explore) toward the
explicit goal: **be better than Monday.com for cleanroom gowning qualification.**
Read with `docs/DESIGN_AND_LESSONS_v1.md` (design language + hard-won UI lessons).

---

## 1. THE NORTH STAR

GQS is the authoritative, 21 CFR Part 11-minded, GAMP 5 system of record for the entire cleanroom
gowning qualification lifecycle at MATC (Astellas). The bar is not "a tracker." The bar is: a
manager opens GQS and the system has already done the scheduling, advanced the stages, flagged the
exceptions, and queued the notifications. People manage exceptions; the system runs the routine.

Two axes, fully realized:
- **The qualified population** (operators/trainees): class -> runs -> sampling/incubation -> results
  -> QA sign-off -> qualified -> yearly requal.
- **The staff doing the work** (QC Micro analysts, QA reviewers): teams, managers, assignment of run
  days/classes/approvals, and workload views for resource planning.

---

## 2. THE FULL GMP WORKFLOW (the spine, as built)

Per-person state machine (`WorkflowStage`): Class Pending -> Class Complete -> Run Scheduled ->
Run Performed -> Incubating -> Awaiting Results -> Results Released -> QA Review -> QA Sign-off
(= Qualified). Off-pipeline: Failed -> QA Determination.

- **Class attendance** (marked on a class session) sets `class_on_file` (persists across cycles) and
  advances Class Pending -> Class Complete.
- **Auto-scheduler** books Class-Complete people into the next available run day within the
  per-day capacity cap (Settings), bumping to the next day when full, auto-approving.
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
- **QA approvals** carry an owner (`qa_owner_id`) so a QA lead knows who owns each pending sign-off.
- **QCM Team View** (Manage): tabbed Overview / Table / Cards / Calendar; per-analyst workload,
  unassigned-run-day alerts, inline assign-analyst modal.
- **Veeva** document number + link on completed runs, surfaced in the QA Sign-off Queue.
- **TrackWise** NC link + **LIMS** worklist linkage (link, don't transcribe).

**Still to build here:** a mirror **QA Team View** (approvals by owner, workload, sign-off calendar);
assigning analysts to class sessions from the team view; per-analyst "my assignments" digest.

---

## 4. UX / DESIGN SYSTEM (as built + the standard every page must meet)

- **page-hero** on every custom page AND every resource list page (via the `GqsListHero` trait,
  which suppresses Filament's default header via `getHeader()` and renders hero + header actions +
  table once). Header actions belong in the hero row, not floating above the table.
- **Top padding** on every page header so content breathes (match the kanban/dashboard feel).
- Shared GQS components in head-styles: `.gqs-stats/.gqs-stat`, `.gqs-panel`, `.gqs-tbl`,
  `.gqs-pill-*`, `.gqs-empty`, `.gqs-flbl/.gqs-fld` (form controls), `.gqs-tabs`, `.gqs-mini-btn`.
- **Kanbans** (Status, Class) use white outer column containers holding cards; click-to-detail modal
  with an Edit button; drag-to-move. No stat rows on kanbans (filters instead).
- **Modals/wizards**: create/edit via modal or wizard; one clean container, never card-in-card.
  Wizards live on Personnel (Identity -> Assignment -> Qual Setup), Run Day Setup, Class Session.
- **Filters + table search** on all list resources; per-day grouped lists where it helps.
- **Astellas palette**: magenta #A4123F, danger #C8102E, gold #E0B83C/#E8C24A, purple #6B2C91,
  green #2E7D5B, charcoal #1C1C21/#15151A. NO em dashes; Title Case labels; solid magenta buttons.
- **Printable** Astellas-themed PDFs (logo, landscape), roster + compliance report.

---

## 5. INFORMATION ARCHITECTURE (current)

- **Qualifications:** Status Board, Run Reservations, Incubation, QA Sign-off Queue,
  Non-Conformances, Qualifications, Qualification Runs, Personnel.
- **Classroom:** Class Board, Class Reservations, Gowning Classes & Dates.
- **Scheduling:** Run Day Setup, Run Day Roster, Run Not Scheduled.
- **My Qualification** (operator, top).
- **Manage dropdown** (sectioned): Reports, Import, Announcements; Records; Lists; Team &
  Assignments (QCM Team View, + future QA Team View); Setup & Settings; Compliance (Audit Trail).
- **Public portal:** Home, Gowning Classes, Run Days, Calendar, Register, Sign In.

---

## 6. DATA MODEL (current)

personnel (+detail), users (+team, is_team_manager, approval, role), qualifications (+workflow_stage,
class_on_file, qa_owner_id, qa_recommendation), qualification_runs (+incubation/results/QA stamps,
lims_worklist_id, veeva_doc_number, veeva_url), run_slots (+assigned_analyst_id), reservations
(+lims_worklist_id), run_samples, training_classes (+detail), class_sessions (+assigned_instructor_id),
class_enrollments, class_completions, electronic_signatures, non_conformances, queued_emails,
import_batches, activity_log, qualification_comments, settings, role_capabilities, notifications,
announcements, reference vocab (departments, job_titles, cleanrooms, sampling_sites).

---

## 7. WHAT MAKES IT BETTER THAN MONDAY.COM (and where to push next)

Monday.com is generic boards. GQS wins by being domain-true and self-driving. To widen the gap:

### 7.1 Near-term polish (in flight)
- Uniform page headers (button-in-header, top padding) across every page.
- QA Team View; class-session instructor assignment from team view.
- Kanban swimlanes (group by department/cleanroom/cycle-type) and saved board filters.
- Bulk actions on boards (multi-select cards -> assign day / mark performed).

### 7.2 Higher-value capability
- **Command palette / global quick-add** (jump to any person, quick-book a run): consider
  `filament/spatie-laravel-tags` style search or a custom Cmd-K.
- **Saved views & per-user board preferences** (Monday's "my views"): persist filter/sort/columns.
- **Calendar surface** for run days + class sessions + due dates in one place
  (`guava/calendar` or `saade/filament-fullcalendar`), drag-to-reschedule.
- **Timeline / Gantt** of a cohort's path to qualified (`filament/widgets` + custom, or
  `flowframe/laravel-trend` for the trend math).
- **Dashboards per role** with drill-down widgets (QCM workload, QA queue aging, overdue heatmap).

### 7.3 Laravel/Filament packages to explore (server `composer require`, Claude wires)
- **spatie/laravel-medialibrary** + Filament plugin: attach attendance sheets, SOPs, plate photos,
  signed PDFs to runs/classes/NCs. (Already planned; high value for the GMP record.)
- **spatie/laravel-settings** (+ Filament settings plugin): typed, validated settings replacing the
  key/value store as the config surface grows.
- **pxlrbt/filament-excel** or **filament/filament native export**: robust XLSX exports + saved
  exports for reports (overdue, pass/fail, completions, NC trending).
- **saade/filament-fullcalendar** or **guava/calendar**: the unified scheduling calendar with
  drag-reschedule, feeding the auto-scheduler.
- **bezhansalleh/filament-shield**: if RBAC needs to scale past the current editable matrix to
  fine-grained policy-based permissions (keep our matrix unless it outgrows it).
- **awcodes/filament-curator** or media library: a managed asset library for SOP/form documents.
- **spatie/laravel-backup**: scheduled DB + file backups (GMP/Part 11 expectation), with an
  in-app restore-verification record for the validation package.
- **owen-it/laravel-auditing** (alt to spatie/activitylog) only if we need richer field-level diffs;
  current activitylog is sufficient and Part 11-aligned.
- **laravel/horizon** + queues: once email (Postfix) and heavier jobs (imports, exports, PDF
  generation) move to queues, Horizon gives visibility. Pair with **spatie/laravel-schedule-monitor**
  to prove the daily automation (auto-schedule, lifecycle, incubation) actually ran, which is itself
  a validation artifact.
- **barryvdh/laravel-dompdf** or **spatie/laravel-pdf** (Browsershot): server-rendered PDFs if we
  outgrow the browser-print approach (e.g. emailed signed reports). Browsershot needs headless
  Chrome on the server.
- **filament/notifications + database + broadcast**: real-time push of stage changes / approvals
  (Monday-style live updates) via Laravel Echo + Reverb (`laravel/reverb`).
- **maatwebsite/excel**: heavy-duty import/export engine behind the existing import module.

### 7.4 GMP/compliance depth (the real moat vs Monday)
- **Validation package content** (URS/FS/DS/RA/RTM/IQ/OQ/PQ/VSR/SOPs) versioned with code, refit to
  Astellas templates; auto-generate the RTM from code annotations where possible.
- **Scheduled-job monitor** + **backup-verification** records as living IQ/OQ evidence.
- **Periodic review** workflow (annual system review, e-signed) and **change-control** log tied to
  git commits / releases.
- **Data-integrity dashboards** (ALCOA+): orphaned records, missing signatures, overdue reviews.
- **Training-effectiveness analytics**: pass/fail by class, by instructor, by cleanroom, mold vs
  bacteria trending by organism/site/time (the NC tracker already seeds this).

---

## 8. BUILD ROADMAP (ordered)

**Phase E, UX uniformity (current):**
1. Button-in-header + top padding on every page (resource list + custom).
2. QA Team View (mirror QCM) + instructor assignment from team view.
3. Bulk board actions + swimlanes + saved filters.

**Phase F, capability:**
4. Unified scheduling calendar (drag-reschedule) feeding the auto-scheduler.
5. Media attachments (medialibrary) for forms/SOPs/plate photos.
6. Reports XLSX export + NC trending mini-reports + per-role dashboards.
7. Real-time updates (Reverb) for live board/queue changes.

**Phase G, compliance moat:**
8. Backups + schedule-monitor as validation evidence; periodic review + change-control.
9. Validation package content; auto-RTM; data-integrity dashboards.
10. Email delivery (Postfix) + queued-email flush in production.

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

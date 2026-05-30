# DESIGN / CONFIGURATION SPECIFICATION (DS)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | DS-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT — for QA review |
| System | MATC Gowning Qualification System (GQS) |
| GxP Impact | GxP — Direct |
| References | VP-GQS-001, URS-GQS-001, FS-GQS-001, RA-GQS-001 |
| As-built baseline | git `main` @ commit recorded in the Revision History below |

### Approvals
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author (Developer) | | | | |
| System Owner | | | | |
| Quality Assurance | | | | |

### Revision History
| Version | Date | Baseline (commit) | Author | Description |
|---|---|---|---|---|
| 1.0 | 30-MAY-2026 | main @ c21df91 | | Initial as-built draft (post: kanban fill/groupings, attendance-draft gating, page-header rework, QA Team View Sign-off Forecast) |

> **Maintenance rule (living document):** at the **end of every development session**, this DS and the
> RTM (RTM-GQS-001) are updated to reflect code changes, a new Revision History row is added with the
> deployed commit, and the change is recorded in `validation/CHANGE_CONTROL_LOG.md`.

---

## 1. PURPOSE & SCOPE
This document specifies how GQS is built and configured (architecture, technology, data model, controlled
vocabularies, configuration parameters, security, electronic records/signatures, audit trail, interfaces,
scheduled automation, and deployment) so the system can be installed (IQ), tested (OQ/PQ), maintained,
and changed under control. It is the as-built/as-configured record. Scope matches VP-GQS-001.

## 2. REFERENCES
SOP-AST-30419; VP-GQS-001; URS-GQS-001; FS-GQS-001; RA-GQS-001; the source repository (GitHub
`tigger2000ttfn/Gowning`, branch `main`); `docs/GQS_MASTER_PLAN_v4.md`; `docs/DESIGN_AND_LESSONS_v1.md`.

## 3. SYSTEM ARCHITECTURE
- **Type:** server-rendered web application (Filament admin panel + a public portal).
- **Tiers:** browser (HTTPS) -> Apache web server (reverse-proxied subfolder `/gowning/` on port 8080)
  -> PHP-FPM application (Laravel) -> PostgreSQL database (AWS RDS, SSL).
- **Hosting:** Astellas E3 application server, application path `/var/www/html/gowning/`.
- **Deployment:** controlled GitHub-to-server pipeline (`deploy.sh`, Section 15). Git tags define the
  validated baseline.

## 4. TECHNOLOGY STACK & COMPONENTS
| Component | Version / Detail |
|---|---|
| Language | PHP ^8.3 |
| Framework | Laravel ^13.0 |
| Admin UI framework | Filament ^5.0 |
| Database | PostgreSQL (AWS RDS), SSL required |
| Web server | Apache (mod_headers required for cache-control), PHP-FPM |
| Front-end build | Vite (fingerprinted assets under `public/build`) |
| Dev tooling | laravel/tinker ^3, laravel/pail, laravel/pint |
| Vendored client libraries | SortableJS (kanban drag) and flatpickr (date pickers) served locally from `public/vendor/...` (CDNs are blocked on the Astellas network) |

> Note: optional packages discussed in the roadmap (media library, settings, excel, fullcalendar,
> backup, horizon, reverb) are **not installed** in this baseline. A `media` table exists as groundwork
> only; attachment handling is not yet wired. See `docs/GQS_OPEN_ISSUES.md`.

## 5. ENVIRONMENTS & HOSTING CONFIGURATION
- **Production:** E3 server (Apache + PHP-FPM) + AWS RDS PostgreSQL (`gowning_e3`), SSL enforced;
  application served under `/gowning/` (port 8080, HTTPS).
- **Development:** sandbox clone of the repo; no production data.
- **Web/caching config (`public/.htaccess`):** `mod_headers` rules — Vite-fingerprinted assets under
  `/build/` are served immutable (1-year cache); all other responses (HTML + unhashed assets) are
  `no-cache, must-revalidate`. This prevents browsers serving stale HTML after a deploy. (Requires
  `a2enmod headers`.)
- **Time zone:** America/New_York; GMP display date format `DD-MON-YYYY` (e.g., 11-MAY-2026) via Carbon
  macros registered on both `Carbon` and `CarbonImmutable`.

## 6. DATA MODEL
Database: PostgreSQL. ~30 application tables (plus framework tables `cache`, `jobs`). All GMP records use
soft deletes (no hard delete). Key entities:

| Table | Purpose / key columns |
|---|---|
| personnel | Person of record: employee_id, first/last name, email, department, job_title, is_active, lims_username, user_id (link). |
| users | Login: role, team, is_team_manager, approval_status, approved_at/by, must_change_password, personnel link. |
| qualifications | One per person: type, status, workflow_stage, runs_required, runs_completed, qualified_date, due_date, class_on_file (+date), cycle_started_at, qa_recommendation, qa_owner_id, stage_changed_at. |
| qualification_runs | type/cycle, run_date, result, lims_worklist_id, veeva_doc_number, veeva_url, incubation + results + QA stamps, qa_determination. |
| qualification_comments | Running comment feed: qualification_id, user_id, author_name, body. |
| run_slots | Run day: cleanroom, slot_date, start/end time, capacity, status, assigned_analyst_id. |
| reservations | Booking: status, lims_worklist_id, links person ↔ run slot. |
| run_samples | Per-run sampling sites/plates. |
| training_classes | Class catalog. |
| class_sessions | Session: training_class_id, session_date, start/end time, location, capacity, status, assigned_instructor_id, attendance_submitted_at/by. |
| class_enrollments | Enrollment: personnel_id, name, email, employee_id, status, attended_at, marked_by, qa_completed_by/at. |
| class_completions | QA-approved class record (prerequisite for runs). |
| non_conformances | NC from failed runs / control growth; TrackWise reference. |
| electronic_signatures | Part 11 signatures bound to a signable record (type+id), signer, meaning, timestamp. |
| audit_logs | Computer-generated audit trail (who/when/old/new). |
| workflow_statuses | Configurable label/color per workflow value. |
| settings | Generic key/value configuration store (Section 8). |
| role_capabilities | RBAC: capability ↔ role assignments (configurable). |
| automation_rules | Trigger ↔ action rules (notify/queue email), enable flag, config, run_count, last_fired_at. |
| email_templates | key, name, subject, body_html, is_enabled. |
| queued_emails | Outbound email queue (sent by a flush command). |
| announcements | Posted announcements. |
| messages | In-app messaging. |
| notifications / notification_preferences | In-app notifications + per-user preferences. |
| import_batches | Controlled import runs (preview/commit, rejected rows). |
| media | (Groundwork only; attachments not yet wired.) |

Reference vocabularies (departments, job titles, cleanrooms, sampling sites, room locations) are
maintained as auditable list records.

## 7. CONTROLLED VOCABULARIES / ENUMERATIONS
Defined as application enums (single source of truth for states and labels):
- **WorkflowStage:** class_pending, class_complete, run_scheduled, run_performed, incubating,
  awaiting_results, results_released, qa_review, qa_signoff, archived, failed.
- **QualificationStatus:** pending, in_progress, qualified, lapsed.
- **QualificationType:** initial (3 runs), annual (1 run).
- **RunResult:** pass, fail, pending.
- **RunSlotStatus:** open, closed, cancelled.
- **ReservationStatus:** requested, approved, rejected, completed, no_show.
- **Capability (RBAC, 14):** ManageScheduling, RecordRuns, ManageClasses, ManageAttendance,
  ManagePersonnel, ViewQualifications, QaReview, QaApprove (sign determinations / override due dates),
  ViewReports, ImportData, ManageUsers, ManageRoles, SystemSettings, ViewOnly.
- **Role:** super_user (+ the configurable capability matrix).
- **Team:** QC Micro, QA.
- **AutomationTrigger:** stage_changed, run_failed, run_passed, qualified, lapsed, due_soon,
  class_completed, nc_opened.
- **AutomationAction:** notify_capability, notify_person, post_announcement, queue_email.
- **NotificationEvent:** in-app notification event types.
- Class enrollment status values: signed_up, attended, pending_qa, completed, no_show, cancelled,
  historical.

## 8. CONFIGURATION & SETTINGS
Operational parameters are held in the `settings` key/value store and read with safe defaults. Key
configurable parameters (confirm values with QA against SOP-AST-30419):
| Setting | Meaning | Default |
|---|---|---|
| initial_runs_required | Passing runs for initial qualification | 3 |
| cycle_months | Qualification validity period | 12 |
| incubation_days | Incubation period before Awaiting Results | (config) |
| action level (CFU/plate) | Pass/fail threshold | 5 |
| lapse grace period | Days past due before auto-requalification | (config) |
| board_show_failed | Show the Failed lane on the Status Board | true |
| qcm_manager_id / qa_manager_id | Team managers | (set in Settings) |
| attendance-form metadata | Doc # / Revision / Title printed on FORM-AST-36513 | (to confirm) |
| feature/auth toggles | Optional login/registration behaviors | default off |

## 9. SECURITY CONFIGURATION
- **Authentication:** unique per-user accounts via a custom Login page; no shared accounts. Optional
  password controls (e.g., forced change on first login) gated by settings.
- **Authorization (RBAC):** capability-based; `users.role` maps to capabilities via `role_capabilities`
  (administrator-configurable). Actions/resources check `User::hasCapability(...)`. `super_user` is the
  top role. GMP-critical actions (QA sign-off, determinations, due-date override) gate on `QaApprove`.
- **Account provisioning:** new accounts carry `approval_status` (pending/approved/rejected); access is
  granted only on approval and is revocable; access lists are reviewable in the admin panel.
- **Transport security:** HTTPS for all traffic; SSL required to RDS.
- **Panel:** Filament panel id `admin`, path `admin`, SPA navigation, brand "MATC Gowning
  Qualification", dark/light toggle, Astellas color palette.

## 10. ELECTRONIC RECORDS & ELECTRONIC SIGNATURES
- **Records:** the database is the originating GMP record; external data (LIMS, Veeva, TrackWise) is
  referenced, not transcribed. Human-readable copies and Astellas-formatted PDF forms are produced.
- **Signatures (`electronic_signatures`):** two-component (identity + meaning) signatures bound to the
  specific signable record (polymorphic type+id), capturing signer, meaning, and timestamp; signer
  re-authenticates at the point of signing. Used for trainer attendance submission and QA sign-off.
- **No destruction:** GMP records use soft deletes; there are no hard-delete code paths for GMP records.

## 11. AUDIT TRAIL
A computer-generated, time-stamped audit trail (`audit_logs`) records creation, modification, and
deletion of GMP records, including the prior value on change (who, when, old, new). It is not editable
by users and is reviewable/exportable by authorized users.

## 12. INTERFACES (by reference)
- **LIMS (LabWare):** `lims_worklist_id` on runs/reservations; `personnel.lims_username`. (Future:
  periodic worklist/sample-status import — see Master Plan Section 10.)
- **Veeva Vault:** `veeva_doc_number` + `veeva_url` on completed runs; SOP header link to the
  SOP-AST-30419 permalink.
- **TrackWise:** NC reference on `non_conformances`.

## 13. SCHEDULED AUTOMATION
A single system cron entry runs Laravel's scheduler every minute
(`* * * * * php artisan schedule:run`). Scheduled work includes auto-scheduling Class-Complete people
into run days, lifecycle lapse handling, incubation advancement, due-soon notifications, and the
outbound email flush. Automation rules (`automation_rules`) map triggers to actions (notify / queue
email) and are individually enable-able. Outbound email is queued (`queued_emails`) pending a production
relay. *(A scheduled-job run monitor is planned as validation evidence — see RA-GQS-001 R-19.)*

## 14. APPLICATION STRUCTURE
- **Filament admin panel** (`App\Filament\Admin`): auto-discovered Resources, Pages, and Widgets.
- **Navigation groups:** Qualifications; Classroom; Scheduling; Review; (Manage dropdown: Reports,
  Import, Records, Lists, Team & Assignments, Setup & Settings, Compliance/Audit Trail); My
  Qualification; plus the public portal.
- **Render hooks** (panel provider): inject the shared head styles (loads flatpickr theme), the Manage
  dropdown, the topbar Messages link, and the topbar SOP link.
- **Services** (`app/Services`): QualificationEngine, RunCycleAdvancer, LifecycleAdvancer, AutoScheduler,
  IncubationAdvancer, Notifier (in-app + queued email), AutomationEngine, AttendanceFormFiller
  (FORM-AST-36513), ApprovalFormFiller (FORM-AST-36749), MailConfig.
- **Shared views:** head-styles (all GQS CSS, incl. theme-aware page header, kanban/swimlane styles,
  flatpickr theme), page-hero (page header partial), resource-list (list-page header), kanban column
  partials, flatpickr date partial.

## 15. DEPLOYMENT & CONFIGURATION MANAGEMENT
`sudo bash deploy.sh` on the E3 server performs, in order: git fetch + `reset --hard origin/main`
(the approved baseline); `composer install --no-dev` (falling back to `composer update` if the lock is
out of date); `php artisan migrate --force`; `storage:link`; a Vite build that **backs up the current
`public/build` and restores it if the build fails or yields no manifest** (so a failed build never
500s the site); `php artisan filament:assets`; a theme-token sanity check; `chown www-data`;
`optimize:clear` + `view:clear`; restart of PHP-FPM and Apache; install of the scheduler cron if
absent; and a verification step printing the deployed commit. Vendored client assets carry a
`?v=filemtime` cache-buster.

## 16. BACKUP & RECOVERY (planned)
RDS provides platform-level backups (Astellas IT). Application-level scheduled backups + a documented
restore-verification record are planned and required before VSR approval (RA-GQS-001 R-20).

## 17. CHANGE HISTORY
Authoritative change history is the git commit log on `main`; each release is tagged and referenced by
change control. This DS's Revision History records the as-built baseline commit per version.

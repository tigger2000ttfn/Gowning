# MATC Gowning Qualification System (GQS)

> A 21 CFR Part 11-minded, GAMP 5 tracking system for the complete cleanroom gowning
> qualification lifecycle at the Manufacturing Technology Center (Astellas).
> Built to be **better than Monday.com for this job**: a kanban-grade tracking surface
> with the GMP rigor a regulated environment demands, and automation that runs the
> lifecycle so the team manages exceptions, not spreadsheets.

---

## Why this exists

Gowning qualification at a sterile manufacturing site is a recurring, auditable lifecycle:
people take a gowning class, perform cleanroom qualification runs, get microbiologically
sampled, wait on LIMS results, and pass a QA electronic sign-off, then requalify on a yearly
cycle. GQS makes that whole flow **visible, automated, and Part 11-defensible** in one place.

## What makes it strong

- **The full GMP workflow as a live pipeline.** Every person flows through a 9-stage state
  machine (Class to QA Sign-off) on a global Status Board, with per-person cards showing run
  progress. Status-flipping is the heart of the system.
- **Automation end to end.** Class attendance advances people automatically; runs auto-book
  into the next available day under a capacity cap; incubation advances on a time trigger off
  the run date; due dates auto-lapse into requalification. The team handles exceptions.
- **Part 11 / GAMP 5 built in.** Computer-generated audit trail (Spatie activity log),
  two-component electronic signatures, no hard deletes, attributable + timestamped records,
  external-record linkage (LIMS worklist, TrackWise NC, Veeva docs) instead of transcription.
- **Resourcing layer.** QC Micro managers assign analysts to run days and classes with team
  workload views; QA leads own batches of approvals.
- **Self-service.** Operators book and reschedule their own runs to the next open capacity.
- **Printable, branded records.** Run-day rosters and compliance reports render as Astellas
  themed PDFs (print or save from the browser) with sign-off lines.

## Stack

- PHP 8.3 + Laravel 13, Filament 5 admin panel
- PostgreSQL on AWS RDS (`gowning_e3`), Apache at `https://matcastellas.com:8080/gowning/`
- Spatie Activity Log (audit trail). Deploy via `sudo bash deploy.sh`.

## The lifecycle (the spine)

```
Class Pending → Class Complete → Run Scheduled → Run Performed
   → Incubating → Awaiting Results → Results Released → QA Review → QA Sign-off = Qualified
                                                          ↘ Failed → QA Determination (requal)
```

Rules: initial = 3 runs (qualified 12 months); annual = 1 run on/before due; lapsed
(past due + grace) = automatic 3-run requalification; gowning class is taken once, ever
(persists across cycles) unless QA mandates retraining; QA determination sets the requal
run count and whether retraining is required.

## Feature map

**Qualifications:** Status Board (global kanban), Run Reservations (per-day), Incubation
(time-driven), QA Sign-off Queue (e-signature + Veeva link + approval ownership),
Non-Conformances (TrackWise link, mold/bacteria trending), Qualifications, Qualification Runs,
Personnel (with manual first-time setup).

**Classroom:** Class Board, Class Reservations, Gowning Classes (catalog + sessions +
attendance that drives the pipeline).

**Scheduling:** Run Slots (with analyst assignment), Run Day Roster (mark performed, enter
results, print), Run Not Scheduled watchlist, auto-scheduler + cancel-day cascade.

**Manage:** Reports, Import, Announcements, reference Lists, Users & Roles, Settings (most of
the system is configurable here), Team & Assignments, Audit Trail.

## Roadmap

See **docs/GQS_MASTER_PLAN_v2.md** for the complete plan and **docs/GQS_DESIGN_AND_LESSONS_v1.md**
for the design language and hard-won UI lessons.

- [x] Foundation, RBAC, public portal, cosmic UI, dashboard
- [x] Full GMP workflow + Status/Class/Incubation boards
- [x] Auto-scheduling engine, capacity, cancel-day cascade, self-service reschedule
- [x] Yearly lifecycle automation, class-on-file persistence
- [x] Part 11 audit trail + two-component e-signatures
- [x] Non-Conformance tracker + Veeva/TrackWise/LIMS linkage
- [x] Staff resourcing/assignment layer + team views
- [x] Printable Astellas-themed rosters and reports
- [ ] Kanban click-to-edit card modals; convert remaining actions to modals/wizards
- [ ] QA team view; class-session instructor assignment UI
- [ ] Media attachments (attendance forms, SOPs, plate photos)
- [ ] Reports XLSX export + mold/bacteria trending mini-reports
- [ ] Email delivery (Postfix relay) + validation package content (IQ/OQ/PQ)

## Deploy

The app lives here; the E3 server pulls and sets up the environment. See **DEPLOY.md**.
Standard: `cd /var/www/html/gowning && sudo bash deploy.sh`.

## Validation

A GAMP 5 validation package lives in `/validation`, versioned with the code, to be refit to
Astellas templates. Technical controls are built in; validation (IQ/OQ/PQ) is QA-governed.

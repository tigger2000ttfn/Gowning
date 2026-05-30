# FUNCTIONAL SPECIFICATION (FS)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | FS-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT — for QA review |
| System | MATC Gowning Qualification System (GQS) |
| GxP Impact | GxP — Direct |
| References | URS-GQS-001, DS-GQS-001, RA-GQS-001, RTM-GQS-001 |
| As-built baseline | git `main` @ ccd7905 |

### Approvals
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| System Owner | | | | |
| Quality Assurance | | | | |

### Revision History
| Version | Date | Baseline | Author | Description |
|---|---|---|---|---|
| 1.0 | 30-MAY-2026 | main @ ccd7905 | | Initial draft; one functional item per URS requirement |

> **Maintenance rule (living document):** updated at the **end of every behavior-changing session**
> with DS-GQS-001 and RTM-GQS-001; new/changed functions add/update a numbered item here and the
> matching RTM row, and the change is recorded in `validation/CHANGE_CONTROL_LOG.md`.

---

## 1. PURPOSE & SCOPE
This Functional Specification describes **what GQS does** to satisfy each requirement in URS-GQS-001.
Each item is numbered `FS-<AREA>-<n>` and implements the same-numbered URS requirement; the design that
realizes it is in DS-GQS-001 and the verifying test is in the RTM. Scope matches VP-GQS-001.

## 2. CONVENTIONS
Each item lists its URS reference and the functional behavior. "The system" = GQS. Where one function
satisfies several requirements, the additional URS references are noted in parentheses.

---

## 3. PROCESS (GEN)
- **FS-GEN-010** (URS-GEN-010) The system stores one authoritative qualification record per person and
  displays current gowning-qualification status across boards, lists, and reports.
- **FS-GEN-020** (URS-GEN-020) Each qualification holds a workflow stage (Class Pending → Class Complete
  → Run Scheduled → Run Performed → Incubating → Awaiting Results → Results Released → QA Review →
  QA Sign-off; plus Failed/Archived) and the system drives the record through these stages.
- **FS-GEN-030** (URS-GEN-030) Stage transitions are produced only by the corresponding action/service;
  a record cannot reach a later stage without its prerequisites (e.g., no runs before Class Complete;
  no Qualified without QA sign-off).
- **FS-GEN-040** (URS-GEN-040) The system distinguishes Initial (runs_required = 3) from Annual
  (runs_required = 1) qualifications and applies the matching rules.
- **FS-GEN-050** (URS-GEN-050) A completed gowning class is required before runs; class-on-file is set
  once and persists across cycles unless QA mandates retraining.
- **FS-GEN-060** (URS-GEN-060) The system records the performer/witness of sampling separately from the
  person being qualified and does not allow a person to qualify themselves.
- **FS-GEN-070** (URS-GEN-070) The system presents role-appropriate surfaces (operator "My
  Qualification"; QC Micro and QA work areas; manager team views; admin configuration).

## 4. PERSONNEL (PER)
- **FS-PER-010** (URS-PER-010) The system maintains a personnel record (employee_id, name, email,
  department, job_title, is_active, lims_username) and displays/edits it.
- **FS-PER-020** (URS-PER-020) A personnel record can be linked to a user login for system access.
- **FS-PER-030** (URS-PER-030) The system detects duplicate personnel (by employee_id / LIMS username)
  and provides a controlled merge that re-points related records and removes the duplicate.
- **FS-PER-040** (URS-PER-040) The system imports personnel from a file via upload → field map →
  validation → preview → commit, producing a rejected-row report; re-import is idempotent.
- **FS-PER-050** (URS-PER-050) System/pseudo accounts are excluded from the qualified population.

## 5. CLASSROOM (CLS)
- **FS-CLS-010** (URS-CLS-010) The system manages class sessions (date, time, location, capacity,
  assigned instructor) and class catalog entries.
- **FS-CLS-020** (URS-CLS-020) Personnel can be enrolled in a session; the system blocks enrollment
  beyond capacity.
- **FS-CLS-030** (URS-CLS-030) Marking a trainee Present or No-Show records an **in-memory draft intent
  only**; the enrollee's status remains "Signed Up" on all surfaces until attendance is submitted.
- **FS-CLS-040** (URS-CLS-040) Submitting attendance requires the trainer's electronic signature and
  moves the marked attendees to Pending QA; the signature is bound to the session record.
- **FS-CLS-050** (URS-CLS-050) QA approval of the attendance/training record sets class-on-file, writes
  a ClassCompletion record, and advances the person to Class Complete (run-eligible). Only QA approval
  does this.
- **FS-CLS-060** (URS-CLS-060) The system rejects any attempt to set Attended/Pending QA/Completed
  outside the attendance-submission/QA flow (e.g., a Class Board drag into those lanes is blocked with
  a message; the only forward path is the attendance sheet + QA).
- **FS-CLS-070** (URS-CLS-070) The system generates FORM-AST-36513 (class/training attendance form)
  populated from the session and attendees, recording the trainer of record.
- **FS-CLS-080** (URS-CLS-080) Submitted attendance is locked; an authorized user can reopen it, which
  is recorded.

## 6. SCHEDULING (SCH)
- **FS-SCH-010** (URS-SCH-010) The system manages run days (cleanroom, date, start/end time, capacity,
  assigned analyst).
- **FS-SCH-020** (URS-SCH-020) A scheduler books Class-Complete people into the next available run day
  within capacity, advancing to the next day when full, and runs unattended on a schedule.
- **FS-SCH-030** (URS-SCH-030) The system enforces the run-day capacity limit.
- **FS-SCH-040** (URS-SCH-040) Authorized users can reserve, reschedule, or cancel run bookings; each
  change is audited.
- **FS-SCH-050** (URS-SCH-050) The system lists personnel who require a run but are not yet scheduled.
- **FS-SCH-060** (URS-SCH-060) Where enabled, operators can self-select an open run day on the public
  portal, creating a reservation.

## 7. RUNS & INCUBATION (RUN)
- **FS-RUN-010** (URS-RUN-010) Recording a performed run captures run date, cleanroom, sampling sites,
  and the LIMS worklist reference.
- **FS-RUN-020** (URS-RUN-020) On run performance the record advances to Incubating and the incubation
  period starts from the performed date.
- **FS-RUN-030** (URS-RUN-030) After the configured incubation period elapses, the record advances from
  Incubating to Awaiting Results (run unattended on a schedule).
- **FS-RUN-040** (URS-RUN-040) The system stores a reference to the LIMS worklist rather than copying
  LIMS data.
- **FS-RUN-050** (URS-RUN-050) The system records per-run sampling sites/plates sufficient to support
  results entry and the qualification record.

## 8. RESULTS & DISPOSITION (RES)
- **FS-RES-010** (URS-RES-010) Results entry records each run as pass or fail against the configurable
  action level (default 5 CFU/plate).
- **FS-RES-020** (URS-RES-020) A passing run routes the record to QA Review.
- **FS-RES-030** (URS-RES-030) A failing run moves the record to Failed and automatically opens a
  Non-Conformance linked to the record.
- **FS-RES-040** (URS-RES-040) Negative-control growth can initiate a Non-Conformance.
- **FS-RES-050** (URS-RES-050) Failing runs do not increment the pass count and do not reset prior
  passes.
- **FS-RES-060** (URS-RES-060) The system tracks runs_completed vs runs_required for the current cycle
  and reflects progress on the boards.

## 9. QA REVIEW & SIGN-OFF (QA)
- **FS-QA-010** (URS-QA-010) A person is recorded Qualified only after QA electronic sign-off.
- **FS-QA-020** (URS-QA-020) QA sign-off captures a two-component electronic signature (identity +
  meaning) and re-authenticates the signer; the signature is bound to the specific record.
- **FS-QA-030** (URS-QA-030) On sign-off the system sets status Qualified, qualified_date, and computes
  due_date = qualified_date + cycle (default 12 months).
- **FS-QA-040** (URS-QA-040) The system generates FORM-AST-36749 (qualification approval) as the
  official QA-approved record.
- **FS-QA-050** (URS-QA-050) For a failed run, QA records a determination (requalification run count
  1/2/3 and any retraining mandate) with rationale; the system then books the requalification runs.
- **FS-QA-060** (URS-QA-060) The QA review surface shows the supporting runs, results, LIMS worklist,
  and Veeva document reference needed for the determination.

## 10. REQUALIFICATION & LIFECYCLE (LIF)
- **FS-LIF-010** (URS-LIF-010) The system tracks each qualification's due_date and flags records due
  soon and past due on boards/lists/timeline.
- **FS-LIF-020** (URS-LIF-020) Annual requalification is satisfied by one passing run within the due
  window.
- **FS-LIF-030** (URS-LIF-030) Past-due qualifications (after any configured grace period) are
  automatically transitioned into a requalification, respecting class-on-file; runs unattended.
- **FS-LIF-040** (URS-LIF-040) A lapsed qualification reverts to Initial (3 passing runs) per the SOP.
- **FS-LIF-050** (URS-LIF-050) A QA-mandated retraining requires a new gowning class before further
  runs.

## 11. NON-CONFORMANCE (NC)
- **FS-NC-010** (URS-NC-010) The system creates and tracks Non-Conformances from failed runs / control
  growth and links them to the affected record.
- **FS-NC-020** (URS-NC-020) A Non-Conformance stores a reference to its TrackWise record rather than
  duplicating the investigation.
- **FS-NC-030** (URS-NC-030) The system supports capture of microbial-identification/organism
  information sufficient for trending.

## 12. TEAMS & ASSIGNMENT (TEAM)
- **FS-TEAM-010** (URS-TEAM-010) The system represents the QC Micro and QA teams and team managers.
- **FS-TEAM-020** (URS-TEAM-020) An analyst can be assigned to a run day and an instructor to a class
  session.
- **FS-TEAM-030** (URS-TEAM-030) QA approvals carry an owner.
- **FS-TEAM-040** (URS-TEAM-040) Team views show per-member workload and unassigned-work alerts; the QA
  team view includes a sign-off forecast of qualifications coming due.

## 13. REPORTING & RECORDS (REP)
- **FS-REP-010** (URS-REP-010) The system produces a run-day roster and a compliance/status report
  (Astellas-formatted, printable).
- **FS-REP-020** (URS-REP-020) The system produces FORM-AST-36513 and FORM-AST-36749 populated from
  system data.
- **FS-REP-030** (URS-REP-030) Records and accurate human-readable copies are retrievable for review
  and inspection.
- **FS-REP-040** (URS-REP-040) Management dashboards/boards show population status, overdue items, and
  queue aging, with click-through to records.

## 14. PUBLIC SELF-SERVICE (PUB)
- **FS-PUB-010** (URS-PUB-010) The public portal provides class sign-up, run-day sign-up, schedule
  viewing, and registration.
- **FS-PUB-020** (URS-PUB-020) Registration identifies a person by employee ID (with autofill) and links
  to the personnel record.
- **FS-PUB-030** (URS-PUB-030) Self-service account creation routes through an approval workflow; access
  is granted only after approval.

## 15. SECURITY & ACCESS (SEC)
- **FS-SEC-010** (URS-SEC-010) Each user authenticates with a unique individual account; shared accounts
  are not used.
- **FS-SEC-020** (URS-SEC-020) Actions and resources enforce role-based capability checks; unauthorized
  users are denied.
- **FS-SEC-030** (URS-SEC-030) An administrator can configure the capability-to-role assignment; changes
  are audited.
- **FS-SEC-040** (URS-SEC-040) New accounts require approval; access is revocable; access lists are
  reviewable.
- **FS-SEC-050** (URS-SEC-050) The system enforces password controls (e.g., forced first-login change)
  and protects credentials.
- **FS-SEC-060** (URS-SEC-060) All traffic uses HTTPS and database connections use SSL.

## 16. ELECTRONIC RECORDS & SIGNATURES (ER)
- **FS-ER-010** (URS-ER-010) Electronic signatures capture signer identity, date/time, and meaning.
- **FS-ER-020** (URS-ER-020) Signatures are linked to their records so they cannot be excised, copied,
  or transferred to falsify a record.
- **FS-ER-030** (URS-ER-030) The system requires re-authentication at the point of signing.
- **FS-ER-040** (URS-ER-040) Electronic records are protected for accurate, ready retrieval throughout
  retention.
- **FS-ER-050** (URS-ER-050) The system has no hard-delete path for GMP records; removal is by
  controlled soft delete that retains the record.

## 17. DATA INTEGRITY / ALCOA+ (DI)
- **FS-DI-010** (URS-DI-010) Every create/change is attributed to the acting user.
- **FS-DI-020** (URS-DI-020) Event timestamps are system-applied (not user-entered).
- **FS-DI-030** (URS-DI-030) Timestamps use America/New_York and the DD-MON-YYYY display format.
- **FS-DI-040** (URS-DI-040) The system links to source systems (LIMS/Veeva/TrackWise) rather than
  transcribing.
- **FS-DI-050** (URS-DI-050) Required fields are enforced so records are complete.

## 18. AUDIT TRAIL (AUD)
- **FS-AUD-010** (URS-AUD-010) The system maintains a secure, computer-generated, time-stamped audit
  trail of create/modify/delete on GMP records.
- **FS-AUD-020** (URS-AUD-020) The audit trail records the prior value on change (who/when/old/new) and
  is not user-alterable.
- **FS-AUD-030** (URS-AUD-030) Authorized users can review and export the audit trail.

## 19. INTERFACES (INT)
- **FS-INT-010** (URS-INT-010) The system stores a LIMS worklist reference on runs/reservations.
- **FS-INT-020** (URS-INT-020) The system stores a Veeva document number/link on completed runs.
- **FS-INT-030** (URS-INT-030) The system stores a TrackWise reference on Non-Conformances.
- **FS-INT-040** (URS-INT-040) *(Future)* The system will accept a periodic LIMS export of
  worklist/sample statuses to reduce manual entry (Master Plan Section 10).

## 20. NON-FUNCTIONAL (NFR)
- **FS-NFR-010** (URS-NFR-010) The system supports the full population (~370) and concurrent team use
  with acceptable responsiveness.
- **FS-NFR-020** (URS-NFR-020) Scheduled automation runs unattended via the scheduler (cron), not
  dependent on a logged-in user.
- **FS-NFR-030** (URS-NFR-030) *(Planned)* The system is backed up on a schedule with a documented
  restore-verification.
- **FS-NFR-040** (URS-NFR-040) *(Planned)* Successful execution of scheduled automation is
  monitorable/evidenced.
- **FS-NFR-050** (URS-NFR-050) The interface is consistent and adheres to the Astellas visual identity.

---

## 21. TRACEABILITY
Each FS item above maps 1:1 to its URS requirement and is carried into RTM-GQS-001 with its DS section
and verifying test. Items marked *(Planned)*/*(Future)* correspond to RA-GQS-001 gaps/future scope and
are not yet verified.

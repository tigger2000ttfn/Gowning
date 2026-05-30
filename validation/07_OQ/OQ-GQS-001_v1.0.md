# OPERATIONAL QUALIFICATION (OQ)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | OQ-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT protocol — for QA approval prior to execution |
| System | MATC Gowning Qualification System (GQS) |
| GxP Impact | GxP — Direct |
| References | VP-GQS-001, URS-GQS-001, FS-GQS-001, DS-GQS-001, RA-GQS-001, RTM-GQS-001, IQ-GQS-001 |
| Baseline under test | git `main` @ tag/commit: ____________ (record at execution) |

### Approvals (protocol)
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| Quality Assurance | | | | |

### Execution & Review
| Role | Name | Signature | Date |
|---|---|---|---|
| Executed by | | | |
| Witnessed by (for e-signature cases) | | | |
| Reviewed by (QA) | | | |

### Revision History
| Version | Date | Author | Description |
|---|---|---|---|
| 1.0 | 30-MAY-2026 | | Initial protocol; GMP-critical cases first |

> **Maintenance rule (living document):** updated with DS/FS/RTM at the end of any behavior-changing
> session that affects a tested function; new/changed behavior adds/updates a test case and the RTM row,
> logged in `validation/CHANGE_CONTROL_LOG.md`.

---

## 1. PURPOSE & SCOPE
The OQ verifies, by scripted test, that each GQS function operates per FS-GQS-001 in a controlled
environment. It is executed after IQ-GQS-001 passes and the protocol is QA-approved. Scope matches
VP-GQS-001.

## 2. CONVENTIONS
- Tests are organized as **Test Cases (TC-nn)**; each lists the requirements it verifies, prerequisites,
  steps, and expected results. Record **Actual result**, **Pass/Fail**, and **Evidence** (screenshot/
  export reference) for every step.
- **Witnessed** cases (electronic signatures) are executed by the signer and observed by a witness;
  both sign the execution record.
- **Negative** steps confirm the system *prevents* a disallowed action.
- A failed step raises a deviation (Section 5) and does not, by itself, fail the whole protocol until
  dispositioned by QA.

## 3. PREREQUISITES
- VP, URS, FS, DS, RA approved; IQ-GQS-001 executed and passed; this protocol approved by QA.
- Test environment loaded with the test data in Section 4. Test accounts exist for each role
  (administrator, QC Micro analyst/trainer, QA reviewer, manager, operator).
- The deployed commit/tag is recorded above.

## 4. TEST DATA SETUP
Create (or confirm) representative data:
- **TD-1** Test personnel: at least 4 (e.g., OP-A operator/trainee; OP-B trainee; TR-1 trainer/QC Micro;
  QA-1 QA reviewer). None initially qualified.
- **TD-2** A gowning class session (future date, capacity small enough to test the cap).
- **TD-3** A run day (cleanroom, date, capacity 1–2 to test the bump).
- **TD-4** A personnel import file with at least one valid row, one duplicate, one invalid row, and one
  system/pseudo account.
- **TD-5** Settings confirmed: initial_runs_required=3, cycle_months=12, incubation_days set,
  action level=5 CFU/plate, grace period set, managers set.

---

## 5. TEST CASES

### TC-01 — Access control & RBAC  *(URS-SEC-010/020/030/040/050/060, ER-030)*
**Objective:** unique login, capability enforcement, account approval, password control, HTTPS.
1. Log in as each role with unique credentials. **Expected:** login succeeds; no shared account used.
2. As an operator, attempt a QA-only action (e.g., open QA sign-off). **Expected (negative):** action is
   not available / denied.
3. As administrator, change a capability's role assignment; re-test the affected action. **Expected:**
   access changes accordingly; the change appears in the audit trail.
4. Create a new account; confirm it is "pending" and cannot access the panel until approved; approve it.
   **Expected (negative→positive):** access denied while pending, granted after approval.
5. For an account flagged must-change-password, confirm a password change is forced at first login.
6. Confirm the app is served over HTTPS and the DB connection uses SSL (cross-ref IQ-011/IQ-005).

### TC-02 — Personnel & import  *(URS-PER-010/020/030/040/050)*
1. Create a personnel record; edit a field. **Expected:** saved; change attributed in the audit trail.
2. Link the personnel record to a user login. **Expected:** link established.
3. Import TD-4. **Expected:** valid row imported; invalid row rejected with a reason in the rejected-row
   report; system/pseudo account excluded; duplicate not double-created (idempotent).
4. Run the dedupe on a known duplicate (dry-run then commit). **Expected:** related records re-pointed,
   duplicate removed, canonical retained.

### TC-03 — Class session & enrollment  *(URS-CLS-010/020)*
1. Create class session TD-2; enroll TD-1 people up to capacity. **Expected:** enrollments created.
2. Attempt to enroll beyond capacity. **Expected (negative):** blocked.

### TC-04 — Attendance: draft → submit (trainer e-sign) → QA approve  *(URS-CLS-030/040/050/070/080, GEN-050, ER-010/020/030)* — **WITNESSED**
**Objective:** prove status stays draft until the trainer e-signs the submission, and only QA approval makes the class official.
1. As trainer, mark OP-A **Present** and OP-B **No-Show** on the session. **Expected:** marks recorded as
   intent only.
2. Without submitting, view OP-A on **Class Reservations** and the **Class Board**. **Expected (critical):**
   OP-A status is still **Signed Up** (no change to the operator).
3. Submit attendance. **Expected:** the system requires the trainer's electronic signature
   (re-authentication + meaning); on signing, OP-A moves to **Pending QA**; the signature is bound to the
   session; FORM-AST-36513 reflects the trainer of record.
4. As QA, approve the attendance/training record. **Expected:** OP-A becomes **Completed**; class-on-file
   is set with a date; a **ClassCompletion** record is created; OP-A advances to **Class Complete**
   (run-eligible).
5. Reopen the submitted session (authorized). **Expected:** reopen is permitted and recorded.

### TC-05 — No status change by shortcut  *(URS-CLS-060, GEN-030)* — **NEGATIVE**
1. On the **Class Board**, drag an enrollee card into **Attended**, then **Pending QA**, then
   **Completed**. **Expected (negative):** each is blocked with a message; status does not change; no
   ClassCompletion is created; the qualification is not advanced.
2. On **Training Class → Sessions**, use the **Attendance** action. **Expected:** it opens the Class
   Scheduler attendance sheet (draft flow); it does **not** set Completed or advance the qualification.

### TC-06 — Auto-scheduling & run-day capacity  *(URS-SCH-010/020/030/040/050/060)*
1. Create run day TD-3 (capacity 1–2). Ensure ≥2 Class-Complete people are eligible.
2. Run the auto-scheduler (or trigger the scheduled run). **Expected:** eligible people are booked into
   the next available day within capacity; the (over-capacity) person bumps to the next available day.
3. Manually reschedule and then cancel a booking. **Expected:** changes succeed and are audited.
4. View the "Run Not Scheduled" list. **Expected:** people needing a run but unscheduled are listed.
5. As an operator on the public portal (if enabled), self-select an open run day. **Expected:**
   reservation created.

### TC-07 — Run performed → incubation → awaiting results  *(URS-RUN-010/020/030/040/050, GEN-060)*
1. As QC Micro, mark OP-A's run **Performed**, recording the performer (TR-1), cleanroom, sites, and the
   LIMS worklist reference. **Expected:** run recorded; OP-A → **Incubating**; incubation timer starts.
2. Attempt to record the person being qualified as their own performer/witness. **Expected (negative):**
   self-qualification is not allowed (two-person rule).
3. Advance time past `incubation_days` (or run the scheduled incubation advance). **Expected:** OP-A →
   **Awaiting Results**.
4. Confirm the LIMS worklist is referenced, not transcribed.

### TC-08 — Results pass → QA sign-off → Qualified  *(URS-RES-010/020/060, QA-010/020/030/040/060, ER-010/020/030)* — **WITNESSED**
1. Enter a **passing** result for OP-A's run against the action level. **Expected:** OP-A routes to
   **QA Review**; pass count increments.
2. As QA, open OP-A and review the supporting runs/results/LIMS worklist/Veeva reference. Execute the
   **two-component electronic sign-off**. **Expected:** re-authentication required; on signing, OP-A
   becomes **Qualified**, qualified_date is set, and due_date = qualified_date + 12 months.
3. Confirm **FORM-AST-36749** is generated as the official approved record and the signature is bound to
   the record.

### TC-09 — Results fail → auto-NC → QA determination → requal booking  *(URS-RES-010/030/040/050, NC-010/020/030, QA-050)*
1. For OP-B, enter a **failing** result. **Expected:** OP-B → **Failed**; a **Non-Conformance** is
   created automatically and linked.
2. Confirm a negative-control growth scenario can initiate an NC.
3. Confirm the failing run did **not** increment the pass count and did **not** reset any prior passes.
4. As QA, record a **determination** (e.g., 1 requal run; or retraining required) **with rationale**.
   **Expected:** determination saved; the requalification run(s) are booked; if retraining is mandated, a
   new class is required before runs.
5. Confirm the NC stores a TrackWise reference (not a duplicated investigation).

### TC-10 — Lifecycle: due tracking & auto-lapse  *(URS-LIF-010/020/030/040/050)*
1. Set a qualification's due_date to the near future. **Expected:** flagged **due soon** on board/list/
   timeline.
2. Set a due_date in the past beyond the grace period and run the scheduled lapse. **Expected:** the
   qualification automatically transitions into a **requalification** (Initial, 3 runs), respecting
   class-on-file.
3. Satisfy an **annual** requalification with one passing run within the window. **Expected:** requal
   completes via the single run + QA sign-off.
4. Confirm a QA-mandated retraining forces a new gowning class before further runs.
*(Time-span behavior is also confirmed in PQ-GQS-001.)*

### TC-11 — Electronic records, audit trail & data integrity  *(URS-ER-040/050, AUD-010/020/030, DI-010/020/030/040/050)*
1. Retrieve a qualification record and its history; confirm a human-readable copy is available.
2. Attempt to delete a GMP record. **Expected (negative):** no hard delete; the record is retained
   (soft-deleted/deactivated) and remains in the audit history.
3. Change a field on a record; open the **audit trail**. **Expected:** entry shows who, when, old value,
   and new value; the audit trail is **not editable** by users.
4. Export the audit trail (authorized). **Expected:** export succeeds.
5. Confirm timestamps are system-applied, in **America/New_York**, displayed as **DD-MON-YYYY**.
6. Attempt to save a record missing a required field. **Expected (negative):** blocked until complete.

### TC-12 — Teams & assignment  *(URS-TEAM-010/020/030/040)*
1. Assign an analyst to a run day and an instructor to a class session. **Expected:** assignments saved.
2. Assign/confirm a QA approval owner. **Expected:** ownership recorded.
3. Open the QC Micro and QA team views. **Expected:** per-member workload and unassigned-work alerts
   display; the QA view shows the sign-off forecast of qualifications coming due.

### TC-13 — Reporting & records  *(URS-REP-010/020/030/040)*
1. Generate the run-day roster and the compliance/status report. **Expected:** Astellas-formatted PDFs
   render with correct data.
2. Generate FORM-AST-36513 and FORM-AST-36749. **Expected:** correct populated forms.
3. Open a management dashboard/board. **Expected:** population status, overdue items, and queue aging
   display with click-through to records.

### TC-14 — Public self-service  *(URS-PUB-010/020/030)*
1. From the public portal, sign up for a class and a run day; register a new person by employee ID.
   **Expected:** sign-ups/registration created; registration links to the personnel record by employee
   ID.
2. Confirm a self-service account cannot access the panel until approved. **Expected (negative→positive):**
   denied while pending; granted after approval.

### TC-15 — Interfaces (by reference)  *(URS-INT-010/020/030)*
1. Confirm a LIMS worklist reference on a run/reservation, a Veeva document number/link on a completed
   run, and a TrackWise reference on an NC are stored and displayed (links open externally). **Expected:**
   references present; no source data transcribed.

### TC-16 — Scheduled automation & visual identity  *(URS-NFR-020/050)*
1. Confirm the scheduler runs unattended (cron evidence; cross-ref IQ-012) and that an automated action
   (e.g., due-soon flag / queued notification) occurs without a user logged in. **Expected:** automation
   runs on schedule.
2. Confirm the interface adheres to the Astellas visual identity (palette, headers, Title Case labels).

---

## 6. DEVIATIONS
| # | Test Case / Step | Description | Resolution | Closed by / date |
|---|---|---|---|---|
| | | | | |

## 7. ACCEPTANCE
The OQ is accepted when all test cases pass (or open deviations are resolved/risk-accepted by QA), with
the witnessed e-signature cases (TC-04, TC-08) and the negative cases (TC-05, TC-07.2, TC-11.2/.3/.6,
TC-14.2) executed as specified. Functions dependent on open technical prerequisites (production email
for some notifications; backups/monitor) are covered where testable now and re-confirmed after those are
implemented (RA-GQS-001 R-19/R-20/R-21).

## 8. TRACEABILITY
Each test case lists the URS/FS requirements it verifies; collectively the test cases cover the
OQ-tagged rows of RTM-GQS-001. Mapping summary:
- TC-01→SEC/ER-030; TC-02→PER; TC-03→CLS-010/020; TC-04→CLS-030/040/050/070/080+ER+GEN-050;
  TC-05→CLS-060/GEN-030; TC-06→SCH; TC-07→RUN/GEN-060; TC-08→RES-010/020/060+QA+ER;
  TC-09→RES-030/040/050+NC+QA-050; TC-10→LIF; TC-11→ER-040/050+AUD+DI; TC-12→TEAM;
  TC-13→REP; TC-14→PUB; TC-15→INT; TC-16→NFR-020/050.
- GEN-010/020/040/070 are confirmed across TC-04/07/08/10 (the lifecycle is exercised end to end).
- Performance/scale (NFR-010), backups (NFR-030), and the job-run monitor (NFR-040) are addressed in
  PQ-GQS-001 / closed as RA gaps.

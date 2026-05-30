# PERFORMANCE QUALIFICATION (PQ)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | PQ-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT protocol — for QA approval prior to execution |
| System | MATC Gowning Qualification System (GQS) |
| GxP Impact | GxP — Direct |
| References | VP-GQS-001, URS-GQS-001, FS-GQS-001, DS-GQS-001, RA-GQS-001, RTM-GQS-001, IQ-GQS-001, OQ-GQS-001 |
| Baseline under test | git `main` @ tag/commit: ____________ (record at execution) |

### Approvals (protocol)
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| System Owner | | | | |
| Quality Assurance | | | | |

### Execution & Review
| Role | Name | Signature | Date |
|---|---|---|---|
| Executed by | | | |
| System Owner (UAT) | | | |
| Reviewed by (QA) | | | |

### Revision History
| Version | Date | Author | Description |
|---|---|---|---|
| 1.0 | 30-MAY-2026 | | Initial protocol |

> **Maintenance rule (living document):** updated with DS/FS/RTM/OQ at the end of any behavior-changing
> session that affects a performance scenario, logged in `validation/CHANGE_CONTROL_LOG.md`.

---

## 1. PURPOSE & SCOPE
The PQ confirms that GQS performs reliably in real use: at representative population scale, under
concurrent multi-role use, with the daily automation running unattended, and across the full
qualification / requalification / lapse cycle over simulated time. It demonstrates fitness for intended
use per SOP-AST-30419. The PQ is executed after IQ and OQ pass and the protocol is QA-approved, in a
production-representative environment.

## 2. REFERENCES
VP/URS/FS/DS/RA/RTM/IQ/OQ as listed above; SOP-AST-30419; the deployment (DS-GQS-001 §3–§5, §13).

## 3. PREREQUISITES
- IQ-GQS-001 and OQ-GQS-001 executed and passed; this protocol QA-approved.
- A production-representative environment with a representative dataset (Section 4).
- Real user roles available for concurrent-use and acceptance scenarios (QC Micro, QA, manager,
  operators).
- A documented method to simulate the passage of time (e.g., controlled date advancement in the test
  environment) for the lifecycle scenarios, agreed with QA.

## 4. TEST DATA & CONDITIONS
- **PD-1** Representative population (~370 personnel) across the in-scope departments/cleanrooms, with a
  realistic spread of states (not yet class-complete, in-progress runs, incubating, awaiting results,
  qualified at various due dates, some near-due, some overdue).
- **PD-2** Multiple run days and class sessions across cleanrooms with realistic capacities.
- **PD-3** Defined performance expectations agreed with QA (e.g., key boards/lists, the timeline, and
  reports return within an acceptable response time under representative load; the daily scheduled run
  completes within its window).
- **PD-4** Concurrent test users for the multi-user scenario.

---

## 5. PERFORMANCE SCENARIOS

### PQ-01 — Full initial qualification in real use  *(URS-GEN-020/030/040/050/060, CLS, RUN, RES, QA)*
Take a trainee from class through qualification using the real roles and forms:
class enrollment → attendance submitted (trainer e-sign) → QA approval (Class Complete) → run booked →
run performed → incubation → awaiting results → passing result → QA sign-off (Qualified, due +12 mo) →
FORM-AST-36749 produced. **Expected:** the end-to-end flow completes correctly with correct records,
stages, dates, and forms, using only the intended in-role actions.

### PQ-02 — Annual requalification cycle  *(URS-GEN-040, LIF-020, RUN, RES, QA)*
For a qualified person approaching due, perform the annual requalification (single passing run within the
window) through QA sign-off. **Expected:** requalification completes via one run + sign-off; new due date
set.

### PQ-03 — Auto-lapse over simulated time → requalification  *(URS-LIF-010/030/040)* — **time-dependent**
Advance simulated time past a qualification's due date + grace; let the scheduled lifecycle run.
**Expected:** the qualification is automatically flagged and transitioned into a requalification (Initial,
3 runs), respecting class-on-file; the operator's board status reflects the change without manual action.

### PQ-04 — Daily automation runs unattended  *(URS-NFR-020, SCH-020, RUN-030; RA R-19/R-21)*
Over a representative period (with no user logged in), confirm the scheduled run (cron) performs:
auto-scheduling of Class-Complete people into run days within capacity; incubation advancement; lapse
handling; due-soon notifications; and the outbound email flush (once the production relay is available).
**Expected:** each scheduled action occurs on schedule and completes within the agreed window; evidence
of execution is available (cross-ref the scheduled-job monitor when implemented).

### PQ-05 — Population-scale load & responsiveness  *(URS-NFR-010)*
With the ~370-person dataset (PD-1), exercise the Status Board, Class Board, Timeline, key lists, and the
roster/compliance reports. **Expected:** these load and respond within the agreed expectations (PD-3);
boards page/scroll smoothly; reports generate successfully at the full data volume.

### PQ-06 — Concurrent multi-user operation  *(URS-GEN-070, DI, AUD)*
With concurrent users (QC Micro entering runs/results, QA signing off, a manager assigning, operators
self-serving), perform simultaneous actions. **Expected:** no data collisions or lost updates; each
action is correctly attributed in the audit trail; statuses remain consistent.

### PQ-07 — Failure / non-conformance path in real use  *(URS-RES-030/040/050, NC, QA-050, LIF-050)*
Drive a failing run end to end: fail recorded → Failed → auto-NC (with TrackWise reference) → QA
determination (requal count / retraining + rationale) → requalification booked (and a new class required
if retraining mandated). **Expected:** the path completes correctly; failed run does not count toward
passes; records and links are correct.

### PQ-08 — Reporting & records at scale  *(URS-REP-010/020/030/040, ER-040)*
Generate the roster, the compliance/status report, and the two forms against the full dataset; retrieve
historical records. **Expected:** outputs render correctly with accurate data at volume; records are
retrievable for review/inspection.

### PQ-09 — Backup & restore verification  *(URS-NFR-030, ER-040; RA R-20)* — **when implemented**
Perform a scheduled backup and a test restore to a controlled point; verify record and audit-trail
integrity post-restore. **Expected:** backup completes; restore reproduces the data and audit trail
intact; a restore-verification record is produced. *(Deferred until the backup capability is
implemented; required before VSR approval.)*

### PQ-10 — User acceptance / business fit  *(URS-GEN-010, overall)*
The System Owner and representative users confirm GQS supports the gowning qualification process per
SOP-AST-30419 in day-to-day operation (the routine is run by the system; people manage exceptions).
**Expected:** the System Owner accepts the system as fit for intended use (UAT sign-off).

---

## 6. ACCEPTANCE CRITERIA
The PQ is accepted when all scenarios pass against the agreed performance expectations (PD-3), the
lifecycle behaviors are demonstrated over simulated time, concurrent use shows no integrity issues, the
System Owner provides UAT acceptance (PQ-10), and open deviations are resolved or risk-accepted by QA.
PQ-09 (backup/restore) and the email-dependent portion of PQ-04 are completed once those technical
prerequisites are implemented (RA-GQS-001 R-20/R-21) and are required before VSR approval.

## 7. DEVIATIONS
| # | Scenario / Step | Description | Resolution | Closed by / date |
|---|---|---|---|---|
| | | | | |

## 8. TRACEABILITY
PQ scenarios satisfy the PQ-tagged rows of RTM-GQS-001 and confirm real-use performance of functions
verified in OQ-GQS-001:
- PQ-03 → URS-LIF-030 (auto-lapse over time); PQ-04 → URS-NFR-020 / SCH-020 / RUN-030 (unattended
  scheduled automation); PQ-05 → URS-NFR-010 (scale/responsiveness); PQ-06 → data-integrity/audit under
  concurrency; PQ-01/02/07 → the end-to-end qualify/requal/fail lifecycles; PQ-08 → reporting/records at
  scale; PQ-09 → URS-NFR-030 (backup/restore, when implemented); PQ-10 → overall business fit
  (URS-GEN-010).

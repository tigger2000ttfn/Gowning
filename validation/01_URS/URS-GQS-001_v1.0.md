# USER REQUIREMENTS SPECIFICATION (URS)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | URS-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT — for QA review |
| System | MATC Gowning Qualification System (GQS) |
| GxP Impact | GxP — Direct |
| References | SOP-AST-30419, FORM-AST-36513, FORM-AST-36749, 21 CFR Part 11, GAMP 5, VP-GQS-001 |

### Approvals
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| System Owner | | | | |
| Quality Assurance | | | | |

### Revision History
| Version | Date | Author | Description |
|---|---|---|---|
| 1.0 | 30-MAY-2026 | | Initial draft |

---

## 1. PURPOSE & SCOPE
This URS defines the business requirements the GQS must satisfy to manage gowning qualification per
SOP-AST-30419 under 21 CFR Part 11. Each requirement is uniquely identified, rated for criticality, and
cross-referenced to the governing SOP/form where applicable. Requirements are testable; the Functional
Specification (FS-GQS-001) describes how the system meets each, and the RTM (RTM-GQS-001) links each to
its verifying test.

## 2. HOW TO READ THIS DOCUMENT
- **ID scheme:** `URS-<AREA>-<n>`. Areas: GEN, PER, CLS, SCH, RUN, RES, QA, LIF, NC, TEAM, REP, PUB,
  SEC, ER (electronic records/signatures), DI (data integrity), AUD (audit trail), INT (interfaces),
  NFR (non-functional).
- **Criticality:** **C** (Critical — direct GMP/data-integrity/patient-safety impact), **M** (Major —
  significant business/process impact), **m** (Minor — convenience/cosmetic).

---

## 3. GENERAL / PROCESS (GEN)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-GEN-010 | The system shall be the system of record for gowning qualification status of all in-scope personnel. | C | 30419 |
| URS-GEN-020 | The system shall represent each person's qualification as a defined lifecycle of stages (class prerequisite, runs, incubation, results, QA sign-off, qualified, requalification, lapse). | C | 30419 |
| URS-GEN-030 | The system shall enforce the sequence of stages so a person cannot reach a later stage without satisfying the prerequisite stages. | C | 30419 |
| URS-GEN-040 | The system shall distinguish initial qualification (3 passing runs) from annual requalification (1 passing run within the due window). | C | 30419 |
| URS-GEN-050 | The system shall require a completed gowning class as a prerequisite to qualification runs, recorded once and persisting across future cycles unless QA mandates retraining. | C | 30419 |
| URS-GEN-060 | The system shall enforce a two-person rule: the individual performing/witnessing sampling shall not be the individual being qualified. | C | 30419 |
| URS-GEN-070 | The system shall present role-appropriate work surfaces (operator, QC Micro analyst, QA reviewer, manager, administrator). | M | |

## 4. PERSONNEL & POPULATION (PER)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-PER-010 | The system shall maintain a personnel record per person (employee ID, name, email, department, job title, active flag, LIMS username). | C | |
| URS-PER-020 | The system shall link a personnel record to a user login where the person needs system access. | M | |
| URS-PER-030 | The system shall prevent duplicate personnel records and provide a controlled means to merge duplicates. | M | |
| URS-PER-040 | The system shall allow import of personnel from a controlled file with validation, preview, and a rejected-row report. | M | |
| URS-PER-050 | The system shall exclude non-person/system pseudo-accounts from the qualified population. | M | |

## 5. CLASSROOM / TRAINING (CLS)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-CLS-010 | The system shall manage gowning class sessions (date, time, location, capacity, assigned instructor). | M | 36513 |
| URS-CLS-020 | The system shall allow personnel to be enrolled in a class session and shall not exceed session capacity. | M | |
| URS-CLS-030 | Attendance shall be recorded against a session and shall remain a draft (no change to a trainee's status) until the attendance is formally submitted. | C | 36513 |
| URS-CLS-040 | Submission of attendance shall require the trainer's electronic signature and shall route attendees to QA review (Pending QA). | C | 36513 |
| URS-CLS-050 | Only QA approval of the attendance/training record shall make the class official: set class-on-file, create a class-completion record, and advance the person to Class Complete (run-eligible). | C | 36513 |
| URS-CLS-060 | Attendance/completion status shall not be settable by any shortcut (e.g., dragging a board card) outside the attendance-submission and QA-approval flow. | C | 11.10(f) |
| URS-CLS-070 | The system shall produce a class attendance/training form (FORM-AST-36513) populated from the session and attendees, recording the trainer of record. | M | 36513 |
| URS-CLS-080 | Submitted attendance shall be locked from further editing unless an authorized user reopens it under audit. | M | |

## 6. RUN SCHEDULING (SCH)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-SCH-010 | The system shall manage run days (cleanroom, date, start/end time, capacity, assigned analyst). | M | |
| URS-SCH-020 | The system shall automatically schedule Class-Complete personnel into the next available run day within the per-day capacity limit, advancing to the next day when full. | M | |
| URS-SCH-030 | The system shall not exceed a run day's capacity. | M | |
| URS-SCH-040 | The system shall allow manual reservation, rescheduling, and cancellation of run bookings by authorized users, with the change audited. | M | |
| URS-SCH-050 | The system shall identify personnel who require a run but are not yet scheduled. | M | |
| URS-SCH-060 | The system shall allow operators to self-select an open run day via the public portal where enabled. | m | |

## 7. RUN EXECUTION & INCUBATION (RUN)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-RUN-010 | The system shall record performance of a qualification run (run date, cleanroom, sites sampled, LIMS worklist reference). | C | 30419 |
| URS-RUN-020 | On run performance the system shall advance the person to Incubating and start the incubation period. | C | 30419 |
| URS-RUN-030 | The system shall advance a record from Incubating to Awaiting Results after the configured incubation period elapses. | M | 30419 |
| URS-RUN-040 | The system shall reference the LIMS worklist rather than transcribing LIMS data. | C | 11.10 |
| URS-RUN-050 | The system shall capture sampling sites/plates per run sufficient to support results entry and the qualification record. | M | 30419 |

## 8. RESULTS & DISPOSITION (RES)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-RES-010 | The system shall record each run's result as pass or fail against the action level (configurable; default 5 CFU/plate). | C | 30419 |
| URS-RES-020 | A passing run shall route the record to QA review. | C | 30419 |
| URS-RES-030 | A failing run shall route the record to the Failed state and automatically open a Non-Conformance. | C | 30419 |
| URS-RES-040 | Growth on a negative control shall be capable of initiating a Non-Conformance. | C | 30419 |
| URS-RES-050 | Failing runs shall not count toward required passes and shall not reset previously recorded passes. | C | 30419 |
| URS-RES-060 | The system shall track progress toward the required number of passing runs for the current cycle. | C | 30419 |

## 9. QA REVIEW & SIGN-OFF (QA)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-QA-010 | Qualification shall require QA electronic sign-off before a person is recorded as Qualified. | C | 36749 |
| URS-QA-020 | QA sign-off shall be a two-component electronic signature (identity + meaning) bound to the specific record. | C | 11.50, 11.200 |
| URS-QA-030 | On QA sign-off the system shall set the person Qualified and compute the next due date (pass date + cycle period; default 12 months). | C | 30419 |
| URS-QA-040 | The system shall produce the Gowning Qualification Approval record (FORM-AST-36749) as the official QA-approved record. | C | 36749 |
| URS-QA-050 | For a failed run, QA shall record a determination (number of requalification runs required and any retraining mandate) with rationale. | C | 30419 |
| URS-QA-060 | The QA review surface shall show the supporting information (runs, results, LIMS worklist, Veeva document reference) needed to make the determination. | M | |

## 10. REQUALIFICATION & LIFECYCLE (LIF)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-LIF-010 | The system shall track each qualification's due date and flag records approaching or past due. | C | 30419 |
| URS-LIF-020 | The system shall support annual requalification as a single passing run within the configured due window. | C | 30419 |
| URS-LIF-030 | The system shall automatically transition past-due qualifications (after any configured grace period) into a requalification, respecting class-on-file. | C | 30419 |
| URS-LIF-040 | A lapsed qualification shall require redoing initial qualification (3 passing runs) per the SOP. | C | 30419 |
| URS-LIF-050 | A QA-mandated retraining shall require a new gowning class before further runs. | C | 30419 |

## 11. NON-CONFORMANCE (NC)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-NC-010 | The system shall create and track Non-Conformances arising from failed runs or control growth and link them to the affected record. | C | 30419 |
| URS-NC-020 | The system shall link a Non-Conformance to its external TrackWise record rather than duplicating the investigation. | M | |
| URS-NC-030 | The system shall support capture of microbial identification / organism information sufficient for trending. | m | |

## 12. TEAMS & ASSIGNMENT (TEAM)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-TEAM-010 | The system shall represent the QC Micro and QA teams and team managers. | M | |
| URS-TEAM-020 | The system shall allow assignment of an analyst to a run day and an instructor to a class session. | M | |
| URS-TEAM-030 | The system shall allow assignment/ownership of QA approvals. | M | |
| URS-TEAM-040 | The system shall provide a team workload view (per-analyst load, unassigned-work alerts). | M | |

## 13. REPORTING & RECORDS (REP)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-REP-010 | The system shall produce a run-day roster and a compliance/status report. | M | |
| URS-REP-020 | The system shall produce the controlled forms (FORM-AST-36513, FORM-AST-36749) populated from system data. | C | 36513, 36749 |
| URS-REP-030 | Records and accurate human-readable copies shall be retrievable for review and inspection throughout the retention period. | C | 11.10(b) |
| URS-REP-040 | The system shall present management dashboards/boards showing population status, overdue items, and queue aging. | M | |

## 14. PUBLIC SELF-SERVICE (PUB)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-PUB-010 | The system shall provide a public portal for class sign-up, run-day sign-up, schedule viewing, and registration. | M | |
| URS-PUB-020 | Registration shall identify a person by employee ID and link to the personnel record. | M | |
| URS-PUB-030 | Any self-service that creates a login shall route through an approval workflow before access is granted. | C | 11.10(d) |

## 15. SECURITY & ACCESS / RBAC (SEC)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-SEC-010 | Each user shall authenticate with a unique individual account; shared accounts shall not be used. | C | 11.10(d),(g) |
| URS-SEC-020 | Access shall be controlled by role-based capabilities; authority checks shall limit actions to authorized users. | C | 11.10(d),(g) |
| URS-SEC-030 | The capability-to-role assignment shall be configurable by an authorized administrator and changes audited. | M | |
| URS-SEC-040 | New accounts shall require approval; access shall be revocable; access lists shall be reviewable. | C | 11.10(d) |
| URS-SEC-050 | The system shall enforce password controls (e.g., forced change on first login) and protect credentials. | M | 11.300 |
| URS-SEC-060 | All traffic shall be encrypted in transit (HTTPS) and database connections encrypted (SSL). | C | 11.10(c) |

## 16. ELECTRONIC RECORDS & SIGNATURES / PART 11 (ER)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-ER-010 | Electronic signatures shall capture the signer identity, the date/time, and the meaning (e.g., approval) of the signing. | C | 11.50 |
| URS-ER-020 | Electronic signatures shall be linked to their records so they cannot be excised, copied, or transferred to falsify a record. | C | 11.70 |
| URS-ER-030 | The system shall require the signer to re-authenticate at the point of signing. | C | 11.200 |
| URS-ER-040 | Electronic records shall be protected to enable accurate and ready retrieval throughout the retention period. | C | 11.10(b),(c) |
| URS-ER-050 | The system shall not permit destruction of GMP records (no hard delete); removal shall be by controlled deactivation/soft delete retaining the record. | C | 11.10(e) |

## 17. DATA INTEGRITY / ALCOA+ (DI)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-DI-010 | Records shall be attributable to the user who created/changed them. | C | ALCOA+ |
| URS-DI-020 | Records shall be contemporaneous (system-applied timestamps, not user-entered for events). | C | ALCOA+ |
| URS-DI-030 | Timestamps shall use a defined time zone (America/New_York) and an unambiguous date format. | M | ALCOA+ |
| URS-DI-040 | The system shall favor linkage to source systems (LIMS, Veeva, TrackWise) over manual transcription. | C | ALCOA+ |
| URS-DI-050 | Required fields shall be enforced so records are complete. | M | ALCOA+ |

## 18. AUDIT TRAIL (AUD)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-AUD-010 | The system shall maintain a secure, computer-generated, time-stamped audit trail of creation, modification, and deletion of GMP records. | C | 11.10(e) |
| URS-AUD-020 | The audit trail shall record the prior value on change (who, when, old, new) and shall not be alterable by users. | C | 11.10(e) |
| URS-AUD-030 | The audit trail shall be available for review and export by authorized users. | M | 11.10(e) |

## 19. INTERFACES (INT)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-INT-010 | The system shall reference LIMS worklists on runs/reservations. | M | |
| URS-INT-020 | The system shall store a Veeva document number/link on completed runs. | M | |
| URS-INT-030 | The system shall store a TrackWise reference on Non-Conformances. | M | |
| URS-INT-040 | *(Future)* The system shall accept a periodic LIMS export of worklist/sample statuses to reduce manual entry. | m | |

## 20. NON-FUNCTIONAL (NFR)
| ID | Requirement | Crit | Trace |
|---|---|---|---|
| URS-NFR-010 | The system shall support the full in-scope population (~370 personnel) and concurrent team use with acceptable responsiveness. | M | |
| URS-NFR-020 | Scheduled automation (auto-scheduling, lapse handling, incubation advance, notifications) shall run unattended on a schedule (cron), not depend on a user being logged in. | C | |
| URS-NFR-030 | The system shall be backed up on a defined schedule with a documented restore-verification capability. | C | 11.10(c) |
| URS-NFR-040 | Successful execution of scheduled automation shall be monitorable/evidenced. | M | |
| URS-NFR-050 | The system shall present a consistent, accessible user interface adhering to the Astellas visual identity. | m | |

---

## 21. TRACEABILITY
Every requirement above is carried into RTM-GQS-001 and linked to its Functional Specification item,
Design Specification section, and verifying OQ/PQ test. Requirements rated **C** receive full OQ/PQ
coverage; **M** receive functional verification; **m** receive light verification, per RA-GQS-001.

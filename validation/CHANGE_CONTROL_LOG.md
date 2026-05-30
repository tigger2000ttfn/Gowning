# VALIDATION CHANGE CONTROL LOG
## MATC Gowning Qualification System (GQS)

Purpose: a running record, updated at the **end of every development session**, of code changes that
affect the validated state and the validation documents updated in response. This implements the
living-validation model: DS-GQS-001 (config spec) and RTM-GQS-001 are kept current with the code, and
any URS/FS change is reflected here. Authoritative code history is the git log; this log ties releases
to validation-document updates for QA. Refit to the Astellas change-control form/numbering when provided.

| # | Date | Baseline (commit) | Code / configuration change | Validation impact | Docs updated |
|---|---|---|---|---|---|
| CC-0008 | 30-MAY-2026 | main @ 8a457da | No code change (documentation). Authored SOP batch 1 (core Part 11 procedures). | Procedural controls for access, e-signatures, audit-trail review, and change control. | SOP-GQS-001 (Access), SOP-GQS-004 (E-Signatures), SOP-GQS-003 (Audit-Trail Review), SOP-GQS-005 (Change Control). |
| CC-0007 | 30-MAY-2026 | main @ 3b6dca7 | No code change (documentation). Authored the Performance Qualification protocol. | Provides the PQ (10 real-use scenarios: full lifecycle, requal, auto-lapse over simulated time, unattended cron automation, population-scale load, concurrency, NC path, reporting at scale, backup/restore [deferred], and UAT). | PQ-GQS-001 created; RTM-GQS-001 note updated. |
| CC-0006 | 30-MAY-2026 | main @ db66ac2 | No code change (documentation). Authored the Operational Qualification protocol. | Provides the scripted OQ (16 test cases, GMP-critical + witnessed e-signature + negative tests) verifying functions against FS-GQS-001. | OQ-GQS-001 created; RTM-GQS-001 note updated. |
| CC-0005 | 30-MAY-2026 | main @ e9f50bd | No code change (documentation). Authored the Installation Qualification protocol. | Provides the executable IQ verifying the deployed baseline + environment against DS-GQS-001. | IQ-GQS-001 created. |
| CC-0004 | 30-MAY-2026 | main @ ccd7905 | No code change (documentation). Authored the Functional Specification. | Completes the URS→FS→DS→test chain; RTM FS column now backed by an authored FS. | FS-GQS-001 created; RTM-GQS-001 note updated. |
| CC-0003 | 30-MAY-2026 | main @ c21df91 | Page header reworked to read like the kanban pages (blank surface, thin bottom rule); QA Team View "Sign-off Forecast" calendar tab added (parallel commit). | UI/usability + a QA workload view; no GMP-control change. | DS §9/§14 reflect current panel/header; URS-TEAM-040 trace confirmed in RTM. |
| CC-0002 | 30-MAY-2026 | main @ (this session) | Attendance hardening: Class Board drag can no longer set Attended/Pending QA/Completed; Training Class > Sessions "Attendance" toggle now opens the Class Scheduler sheet instead of committing Completed + advancing the qualification. Kanban + timeline fill-to-bottom, swimlane groupings (Status Board: Job Title, Due Window; Class Board: Instructor, Session Date), header padding, filter-dropdown sizing, timeline width + deadspace. | **GMP-relevant (positive):** closes two paths that changed qualification/attendance status outside the trainer e-signature + QA flow (Part 11 sequencing, URS-CLS-030/040/060). Remainder UI. | URS-CLS-060, RA-GQS-001 R-03, DS §10 (attendance/e-sign behavior), RTM CLS rows. |
| CC-0001 | 30-MAY-2026 | main @ c21df91 | Validation package foundation authored (no code change): VP, URS, RA, then DS + RTM; validation folder structure; Validation Documentation Master Plan. | Establishes the CSV package and the living-validation process. | VP-GQS-001, URS-GQS-001, RA-GQS-001, DS-GQS-001, RTM-GQS-001, VALIDATION_MASTER_PLAN_v1. |

---

## End-of-session checklist (run every session)
1. Note the deployed/intended commit (`git log --oneline -1`).
2. If code changed behavior: update **DS-GQS-001** (config spec) and add a Revision History row with the commit.
3. If any requirement changed: update the **URS** item, the **FS** item, the **RTM** row, and the verifying **test**.
4. Add a row to this log (CC-nnnn) summarizing the change and the validation impact.
5. Commit and push so the package and the code stay in lockstep.

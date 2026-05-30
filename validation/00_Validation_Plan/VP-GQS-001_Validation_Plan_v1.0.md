# VALIDATION PLAN (VP)
## Computer System Validation — MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | VP-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT — for QA review |
| System Name | MATC Gowning Qualification System (GQS) |
| System Owner | Microbiology / QC Micro (MATC) |
| GxP Impact | GxP — Direct (supports GMP gowning qualification per SOP-AST-30419) |
| GAMP 5 Category | Category 5 (custom / bespoke application) |
| Effective Date | Upon final approval |

### Approvals
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| System Owner | | | | |
| Quality Assurance | | | | |
| IT / Infrastructure | | | | |

### Revision History
| Version | Date | Author | Description of Change |
|---|---|---|---|
| 1.0 | 30-MAY-2026 | | Initial draft for review |

---

## 1. PURPOSE
This Validation Plan defines the strategy, scope, deliverables, responsibilities, and acceptance
criteria for validating the MATC Gowning Qualification System (GQS) to demonstrate that it is fit for
its intended use and compliant with 21 CFR Part 11 and GAMP 5, supporting the gowning qualification
process described in SOP-AST-30419.

## 2. SCOPE
**In scope:** the GQS web application and all of its functions (gowning qualification lifecycle,
classroom/training management, run scheduling, results and disposition, QA review and electronic
sign-off, requalification and lapse handling, non-conformance linkage, team/resource assignment,
reporting, and the public self-service portal); the application database (AWS RDS PostgreSQL); the
hosting configuration (Apache, /gowning/ on port 8080 over HTTPS at matcastellas.com); and the
electronic records and electronic signatures the system creates.

**Out of scope (validated/qualified separately and leveraged as evidence):** LabWare LIMS, Veeva Vault,
and TrackWise (interfaced by reference only); the operating system, network, and the RDS/cloud platform
(covered by Astellas IT infrastructure qualification).

## 3. REFERENCES
- SOP-AST-30419 — Gowning Qualification (regulated process)
- FORM-AST-36513 — Gowning Class / Training Attendance Form
- FORM-AST-36749 — Gowning Qualification Approval Form (official QA record)
- 21 CFR Part 11 — Electronic Records; Electronic Signatures
- 21 CFR Part 211 — cGMP for Finished Pharmaceuticals (as applicable)
- ISPE GAMP 5 (2nd ed.) — A Risk-Based Approach to Compliant GxP Computerized Systems
- ICH Q9 — Quality Risk Management
- Astellas CSV / Computerized System Lifecycle SOP *(to be referenced once provided)*
- GQS System Master Plan (`docs/GQS_MASTER_PLAN_v4.md`); Validation Documentation Master Plan
  (`validation/VALIDATION_MASTER_PLAN_v1.md`)

## 4. SYSTEM OVERVIEW
GQS is a purpose-built web application that manages the full gowning qualification lifecycle for
cleanroom personnel (~370 people) at MATC. It tracks each person from gowning-class prerequisite
through qualification runs, sampling and incubation, results, QA electronic sign-off, the resulting
qualified status, and the yearly requalification cycle, including automatic lapse handling. It manages
the QC Micro and QA teams and the assignment of run days, classes, and approvals; produces GMP records
and Astellas-formatted printable forms; and offers a public self-service portal for sign-ups and
registration. Architecture: PHP 8.3 / Laravel 13 / Filament v5 on PostgreSQL (AWS RDS), deployed via a
controlled GitHub-to-server pipeline.

## 5. GAMP 5 CLASSIFICATION & JUSTIFICATION
GQS is classified **GAMP 5 Category 5 (custom application)** because it is bespoke software developed
specifically for the Astellas gowning process. Category 5 carries the highest validation rigor:
requirements (URS), functional and design specifications, a documented risk assessment, full
traceability, and installation/operational/performance qualification, with software-development
lifecycle (GEP) evidence supporting the design.

## 6. VALIDATION STRATEGY
- **Risk-based (GAMP 5 / ICH Q9).** Test depth is proportional to GMP risk (Section 10 and RA-GQS-001).
  Functions affecting data integrity, electronic signatures, qualification status, results disposition,
  and the QA gate receive full operational testing; low-risk/cosmetic items receive lighter verification.
- **Leveraged testing.** Platform qualification (OS, network, RDS) is leveraged from Astellas IT;
  framework internals (Laravel/Filament) are leveraged from their maintained test suites. GQS testing
  focuses on the bespoke configuration and business logic.
- **Living validation.** The package is versioned with the source code: each release updates its
  matching URS/FS/DS/RTM entry and OQ/PQ test, under change control. Git tags define the configuration
  baseline.
- **GEP evidence.** Good Engineering Practice during development (single-feature commits, code review,
  API verification against vendor source, automated balance checks, the deploy-script verification step)
  supports the Design Specification and IQ.

## 7. ROLES & RESPONSIBILITIES
| Role | Responsibility |
|---|---|
| System Owner (Business) | Owns the URS and PQ acceptance; confirms fitness for the gowning process. |
| Quality Assurance | Approves the package; owns the Risk/Part 11 assessment, SOPs, periodic review; witnesses e-signed test execution. |
| Subject Matter Expert / Author | Drafts VP, URS, FS, DS, RA, RTM, IQ/OQ/PQ protocols, and the VSR. |
| IT / Infrastructure | Provides platform-qualification evidence, backup/restore, and environment details for IQ. |
| Developer | Maintains DS accuracy and code-derived traceability; resolves test failures. |
| Validation Lead | Coordinates execution, deviation management, and final reporting. |

## 8. VALIDATION DELIVERABLES
| # | Deliverable | Document | Status |
|---|---|---|---|
| 00 | Validation Plan | VP-GQS-001 | This document |
| 01 | User Requirements Specification | URS-GQS-001 | Drafted |
| 02 | Functional Specification | FS-GQS-001 | Planned |
| 03 | Design / Configuration Specification | DS-GQS-001 | Planned |
| 04 | Risk Assessment + Part 11 Assessment | RA-GQS-001 | Drafted |
| 05 | Requirements Traceability Matrix | RTM-GQS-001 | Planned |
| 06 | Installation Qualification | IQ-GQS-001 | Planned |
| 07 | Operational Qualification | OQ-GQS-001 | Planned |
| 08 | Performance Qualification | PQ-GQS-001 | Planned |
| 09 | Validation Summary Report | VSR-GQS-001 | After execution |
| 10 | SOPs (access, backup/restore, audit-trail review, e-signatures, change control, periodic review, incident, BC/DR) | SOP-GQS-xxx | Planned |

## 9. ACCEPTANCE CRITERIA
The system is considered validated and fit for intended use when: (a) every approved URS requirement
traces through FS/DS to a passed OQ/PQ test in the RTM with no open gaps; (b) IQ, OQ, and PQ are
executed and approved; (c) all deviations are resolved or formally risk-accepted by QA; (d) the Part 11
controls in RA-GQS-001 are demonstrated; (e) the required SOPs are approved and effective; and (f) the
VSR is approved by QA and the System Owner.

## 10. TEST STRATEGY
- **IQ** verifies correct installation: prerequisite versions, the deployed commit/tag matches the
  approved baseline, database connectivity (SSL to RDS) and migration state, scheduled task (cron),
  web/HTTPS/headers configuration, file permissions, and vendored assets. The deploy-script verify
  output is an IQ artifact.
- **OQ** verifies each function against the FS via scripted test cases (steps, data, expected vs actual,
  evidence, pass/fail). GMP-critical cases are executed and **witnessed with real electronic signatures**
  so the test also evidences the e-signature control.
- **PQ** verifies real-use performance with representative (~370-person) data, concurrent users, and the
  daily automation running via cron, across a simulated qualify/requal/lapse cycle.

## 11. DEVIATION MANAGEMENT
Any test step not meeting its expected result raises a documented deviation (description, impact,
root cause, resolution). Deviations are resolved by correction + re-test or by a QA-approved
justification, and all are closed or risk-accepted before the VSR is approved.

## 12. CHANGE CONTROL
After approval, every production change references a git release/tag and a change record stating which
URS/FS/DS/RTM/test items are affected and the validation impact (risk-assessed; re-validation scoped to
risk). Emergency changes follow the same record retrospectively within a defined window. Governed by the
Change Control SOP.

## 13. CONFIGURATION MANAGEMENT & ENVIRONMENTS
Source code and the validation package are maintained in version control (GitHub, single protected
branch). Releases are tagged; the tag is the validated baseline. Environments: development (sandbox),
and production (Astellas E3 server + AWS RDS). A controlled deployment script performs fetch/reset to
the approved commit, migration, asset build, and a verification step. *(A formal staging/test
environment, if required by the Astellas CSV SOP, is to be confirmed.)*

## 14. VALIDATION SCHEDULE (indicative)
VP approved -> URS approved -> FS + DS -> RA + Part 11 -> RTM scaffolded -> IQ protocol + dry run ->
OQ scripts authored and executed (GMP-critical first) -> close technical gaps (backups + restore
record, scheduled-job monitor, production email) -> PQ -> SOPs approved -> VSR + approvals. Dates to
be set with QA.

## 15. ASSUMPTIONS & EXCLUSIONS
- All documents will be refit to Astellas CSV templates, numbering, and approval routing once provided.
- QA governs and approves; documents are working drafts until signed.
- Platform/infrastructure qualification (OS, network, RDS) is leveraged from Astellas IT.
- Interfaced systems (LIMS, Veeva, TrackWise) are validated separately; GQS links to them by reference.
- Open technical prerequisites to the VSR are tracked in `docs/GQS_OPEN_ISSUES.md`.

## 16. GLOSSARY (selected)
ALCOA+ (Attributable, Legible, Contemporaneous, Original, Accurate, + Complete/Consistent/Enduring/
Available); CSV (Computer System Validation); FMEA (Failure Mode and Effects Analysis); GAMP (Good
Automated Manufacturing Practice); GEP (Good Engineering Practice); IQ/OQ/PQ (Installation/Operational/
Performance Qualification); NC (Non-Conformance); RBAC (Role-Based Access Control); RTM (Requirements
Traceability Matrix); URS (User Requirements Specification); VSR (Validation Summary Report).

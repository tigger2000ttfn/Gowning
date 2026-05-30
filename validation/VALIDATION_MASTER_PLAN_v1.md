# GQS, Validation Documentation Master Plan (v1)

**Last updated:** 30-MAY-2026
**Pairs with:** `validation/README.md` (package structure) and `docs/GQS_MASTER_PLAN_v4.md` (the system).
**Status of this document:** working blueprint. QA owns review and approval of the validation package;
nothing here is a compliance sign-off. All documents will be **refit to Astellas templates, numbering,
and the local CSV procedure** once those are provided.

---

## 1. PURPOSE & SCOPE

This plan defines how the MATC Gowning Qualification System (GQS) will be validated and what documents
the validation package must contain, who authors and approves each, the order to produce them, and the
acceptance criteria for declaring the system fit for intended use under 21 CFR Part 11 and GAMP 5.

**In scope:** the GQS web application (qualification lifecycle, classroom, scheduling, teams,
reporting, public portal), its database (AWS RDS PostgreSQL), the hosting environment (Apache on the
Astellas E3 server, /gowning/ on 8080 over HTTPS), the electronic records and electronic signatures it
creates, and the interfaces it links to (LIMS worklist reference, Veeva document links, TrackWise NC
links).

**Out of scope (validated/managed separately):** LabWare LIMS, Veeva Vault, TrackWise, the underlying
OS/network/RDS platform (covered by Astellas infrastructure qualification; GQS leverages that evidence).

---

## 2. SYSTEM OVERVIEW & GAMP CATEGORY

GQS is **GAMP 5 Category 5 (custom / bespoke application)**, the highest rigor, because it is
purpose-built. The regulated business process it automates is gowning qualification per **SOP-AST-30419**
(initial = 3 passing runs; annual requal = 1 run within the due window; gowning class prerequisite;
fail -> NC + investigation; QA approves **FORM-AST-36749** as the official qualification record;
attendance recorded on **FORM-AST-36513**; two-person rule; QC Micro performs sampling/enumeration).

The validation must therefore trace the system's requirements back to SOP-AST-30419 and the two forms,
and must demonstrate the Part 11 controls (Section 7).

---

## 3. VALIDATION STRATEGY

- **Risk-based (GAMP 5 / ICH Q9).** Testing depth is proportional to risk. GMP-critical functions
  (e-signatures, audit trail, status/stage transitions, requal/lapse logic, results pass/fail routing,
  QA gates) get full OQ scripts and PQ scenarios. Cosmetic/UX items get lighter verification.
- **Leveraged testing.** Re-use evidence rather than re-testing the platform: Astellas infrastructure
  qualification for OS/network/RDS; framework test suites for Laravel/Filament internals. GQS IQ
  verifies the deployed configuration, not the cloud platform.
- **Living validation, versioned with the code.** Each release updates its matching requirement (URS),
  specification (FS/DS), risk line, RTM row, and test (OQ/PQ). Git tags = the configuration baseline;
  change control references the release. This is the operating model already described in
  `validation/README.md`.
- **Build-quality evidence feeds validation.** The development controls already in place (one feature
  per commit, balance checks, Filament-v5 API verification, the deploy script's verify step) are GEP
  evidence supporting the DS and IQ.

---

## 4. ROLES & RESPONSIBILITIES

| Role | Responsibility |
|---|---|
| System Owner (Business) | Owns URS and PQ acceptance; confirms the system meets the gowning process. |
| QA | Reviews and approves the whole package; owns the Part 11 assessment, periodic review, and SOPs; executes/witnesses e-signed test steps. |
| SME / Author (owner + Claude) | Drafts VP, URS, FS, DS, RA, RTM, IQ/OQ/PQ scripts, VSR from the system and this plan. |
| IT / Infrastructure | Provides platform-qualification evidence, backup/restore, environment details for IQ. |
| Developer (Claude-assisted) | Maintains DS accuracy, supplies code-derived traceability, fixes test failures. |
| Approvers | VP, RA, RTM, VSR, SOPs require QA + System Owner signatures (per Astellas CSV SOP). |

---

## 5. THE DOCUMENT SET (the core of this plan)

Folders match `validation/`. Each document below lists **purpose**, **must contain**, **owner**,
**status**, and **depends on**. Default status is **TO AUTHOR** unless noted; the system itself is
built, so most of the source content already exists and needs to be written up and formalized.

### 00 - Validation Plan (VP)
- **Purpose:** the controlling document; defines scope, GAMP category, strategy, deliverables, roles,
  acceptance criteria, and the validation schedule (this plan is its working basis).
- **Must contain:** system description, GAMP 5 Cat 5 justification, risk approach, deliverables list,
  roles/approvers, environments, assumptions/exclusions, acceptance criteria, schedule.
- **Owner:** QA + System Owner approve; SME drafts. **Depends on:** nothing (write first).

### 01 - User Requirements Specification (URS)
- **Purpose:** what the business needs, in numbered, testable requirements, traceable to SOP-AST-30419.
- **Must contain:** functional requirements (class -> runs -> incubation -> results -> QA -> qualified
  -> requal/lapse; teams/assignment; scheduling; reporting; public self-service), data requirements,
  Part 11 / data-integrity requirements, security/RBAC requirements, performance/availability,
  interfaces (LIMS/Veeva/TrackWise). Each requirement gets a unique ID (e.g., URS-FUNC-010) and a
  risk/criticality rating.
- **Owner:** System Owner. **Depends on:** VP. **Source:** `GQS_MASTER_PLAN_v4.md` + SOP-AST-30419.

### 02 - Functional Specification (FS)
- **Purpose:** what the system does to satisfy each URS requirement.
- **Must contain:** per-feature behavior (the state machine, auto-scheduler rules, lifecycle/lapse
  rules, e-signature flows, audit-trail behavior, role capabilities, notifications/automation rules,
  import behavior, print/forms). Each FS item references its URS ID.
- **Owner:** SME. **Depends on:** URS.

### 03 - Design / Configuration Specification (DS)
- **Purpose:** how it is built.
- **Must contain:** architecture (PHP 8.3 / Laravel 13 / Filament v5 / PostgreSQL on RDS / Apache
  subfolder), the data model (tables, keys, enums, relationships), configuration and Settings, the
  capability matrix, the deployment topology and pipeline (`deploy.sh`), the `.htaccess` cache/headers
  config, vendored assets, and security configuration (HTTPS, SSL to RDS, auth, soft deletes).
- **Owner:** Developer (Claude-assisted). **Depends on:** FS. **Source:** the codebase + Section 6 of
  the system master plan. **Note:** keep this auto-refreshable from the schema.

### 04 - Risk Assessment + Part 11 Assessment (RA)
- **Purpose:** identify risks to product quality / data integrity / patient safety and the controls
  that mitigate them; assess each Part 11 clause.
- **Must contain:** an FMEA-style table (failure mode, effect, severity/probability/detectability,
  mitigation, residual risk, verifying test) for GMP-critical functions; a Part 11 clause-by-clause
  assessment (Section 7 is the seed); data-integrity (ALCOA+) assessment.
- **Owner:** QA. **Depends on:** FS, DS.

### 05 - Requirements Traceability Matrix (RTM)
- **Purpose:** prove every requirement is specified, designed, and tested.
- **Must contain:** URS ID -> FS ID -> DS section -> OQ/PQ test ID -> result, with no gaps. Bi-directional.
- **Owner:** SME. **Depends on:** URS, FS, DS, test scripts. **Goal:** auto-generate as much as possible
  from code annotations / a requirements map so it stays current with each release (see Section 6.2).

### 06 - Installation Qualification (IQ)
- **Purpose:** verify GQS is installed correctly in the qualified environment.
- **Must contain (evidenced):** server/OS and prerequisite versions (PHP 8.3, web server, Postgres
  client), the deployed git commit/tag matches the approved baseline, database connectivity (SSL to
  RDS) and migration state, storage symlink, scheduled cron job present, queue/worker config, Apache
  vhost + HTTPS + headers (`.htaccess`), file permissions, vendored assets present. The `deploy.sh`
  verify output (commit + curl) is an IQ artifact.
- **Owner:** IT + SME. **Depends on:** DS. **Status:** much of this is mechanically checkable now.

### 07 - Operational Qualification (OQ)
- **Purpose:** verify each function works per FS under controlled conditions.
- **Must contain:** scripted test cases with steps, test data, expected vs actual, pass/fail, evidence
  (screenshots), executed and witnessed (e-signed where the step exercises an e-signature). Cover at
  minimum: login + RBAC enforcement per role; class signup -> attendance draft -> submit (trainer
  e-sign) -> Pending QA -> QA approve -> Class Complete + ClassCompletion; auto-scheduler booking +
  capacity bump; mark performed -> incubating -> awaiting results; enter results pass -> QA Sign-off
  (two-component e-sign) -> Qualified with correct due date; enter results fail -> Failed + auto-NC +
  QA Determination -> requal booking; lapse logic; audit-trail capture of prior values; soft-delete
  (no hard delete); negative paths (cannot set attended by drag; cannot self-qualify; locked after
  submission); import (idempotent, dedup, rejected-row report); print/forms render with correct data.
- **Owner:** SME executes; QA witnesses. **Depends on:** FS, RTM, IQ pass.

### 08 - Performance Qualification (PQ)
- **Purpose:** verify the system performs in real use with representative data and users.
- **Must contain:** end-to-end business scenarios with ~370-person scale data, concurrent users,
  the daily automation running via cron (not page load), board responsiveness, report generation,
  and the full qualify/requal/lapse cycle over simulated time. Confirms fitness for intended use.
- **Owner:** System Owner + QA. **Depends on:** OQ pass.

### 09 - Validation Summary Report (VSR)
- **Purpose:** conclude the system is validated and fit for use; summarize results, deviations, and
  their resolution; list any residual risks/restrictions.
- **Owner:** QA. **Depends on:** IQ/OQ/PQ complete, deviations closed.

### 10 - SOPs (procedural controls)
- **Purpose:** the procedures Part 11 expects around the system.
- **Must contain (one each):** Access Management (request/grant/review/revoke); Backup & Restore (and
  restore-verification record); Audit-Trail Review (frequency, what to review, e-sign); Electronic
  Signatures (meaning, manifestation, accountability); Change Control (tied to git releases);
  Periodic Review (annual); Incident/Deviation handling; Business Continuity/Disaster Recovery.
- **Owner:** QA. **Depends on:** RA, DS.

---

## 6. REQUIREMENTS & TRACEABILITY

### 6.1 URS structure (trace to the SOP)
Group requirements as: Functional (lifecycle, classroom, scheduling, teams, reporting, portal),
Data, Security/RBAC, Electronic Records & Signatures (Part 11), Data Integrity (ALCOA+),
Performance/Availability, Interfaces. Every requirement: unique ID + criticality + SOP/form cross-ref.

### 6.2 Keeping the RTM alive
Maintain a single `validation/requirements_map` (e.g., a YAML/CSV) keyed by URS ID, with FS ID, DS
section, and test ID columns. Annotate code (controllers/services/tests) with the URS IDs they
implement/verify so the RTM can be regenerated each release and gaps surface automatically. This makes
the living-validation workflow enforceable rather than aspirational.

---

## 7. PART 11 CONTROL MAPPING (seed for the RA; expand into the assessment)

| Part 11 area | Implemented in GQS | Verified by |
|---|---|---|
| Unique user identity (11.10.d, g) | Per-person login; no shared accounts; approval workflow | OQ login/RBAC |
| Access controls / authority checks | Editable capability matrix; role gates on actions | OQ RBAC negative tests |
| Audit trail (11.10.e) | Computer-generated, attributable, time-stamped, retains prior values (activitylog) | OQ audit capture + review SOP |
| No obscuring/deleting records | Soft deletes only; no hard delete paths | OQ delete test |
| Electronic signatures (11.50, 11.70, 11.200) | Two-component e-sign (meaning + manifestation) linked to the signed record; trainer attendance e-sign; QA sign-off | OQ e-sign steps (witnessed) |
| Operational/sequencing checks (11.10.f) | State machine enforces stage order; attendance draft -> submit -> QA gates; cannot set attended/complete by shortcut | OQ workflow + negative tests |
| Accurate copies / records available | Astellas-themed PDF roster/report; FORM-AST-36513/36749 fillers | OQ print verification |
| Time stamps | America/New_York; GMP display format DD-MON-YYYY | OQ timestamp check |
| Record retention / backup | (Planned) scheduled DB+file backups + restore-verification record | IQ + Backup/Restore SOP |
| Change control / system integrity | Git-tagged baselines; one-feature commits; deploy verify | Change Control SOP + IQ baseline check |
| Data integrity (ALCOA+) | External linkage (LIMS/Veeva/TrackWise) not transcription; required fields; comments feed | RA + OQ |

**Gaps to close before VSR (also in OPEN_ISSUES):** scheduled backups + restore-verification record;
scheduled-job monitor proving the daily automation ran; the SOP set; email delivery in production.

---

## 8. QUALIFICATION TESTING APPROACH

- **Pre-approval:** VP, URS, FS, DS, RA, RTM, and the IQ/OQ/PQ protocols are approved before execution.
- **Execution:** in a controlled environment against a known baseline (git tag). Each step records
  expected vs actual + objective evidence (screenshots/exports). Steps exercising e-signatures are
  executed and witnessed with real e-signatures so the test also evidences the control.
- **Deviations:** any failure raises a deviation; resolved (fix + re-test, or documented justification)
  before the VSR.
- **Re-validation triggers:** GMP-impacting change, environment/platform change, or periodic review
  finding; scope per risk (not necessarily full re-test).

---

## 9. ACCEPTANCE CRITERIA (definition of "validated")

The system is fit for intended use when: all approved URS requirements trace through FS/DS to a passed
test in the RTM with no open gaps; IQ, OQ, and PQ are executed and approved; all deviations are closed
or risk-accepted by QA; the Part 11 controls in Section 7 are demonstrated; the SOP set is approved and
effective; and the VSR is approved by QA and the System Owner.

---

## 10. CHANGE CONTROL & PERIODIC REVIEW

- **Change control:** every production change references a git release/tag and a change record stating
  the URS/FS/DS/RTM/test updates and the validation impact (risk-assessed). Emergency changes follow
  the same record retrospectively within a defined window.
- **Periodic review:** annual e-signed system review (access list, audit-trail review evidence, open
  deviations, change history, backup/restore tests, residual risks) to confirm the validated state holds.

---

## 11. SEQUENCE & MILESTONES

1. **VP** approved (controls everything).
2. **URS** authored + approved (System Owner), traced to SOP-AST-30419.
3. **FS** + **DS** authored (DS pulled from the codebase/schema).
4. **RA** + Part 11 assessment (QA).
5. **RTM** scaffolded + requirements_map wired to code annotations.
6. **IQ** protocol + dry-run on the E3 deployment (much is mechanically checkable now).
7. **OQ** scripts authored (GMP-critical first) + executed/witnessed.
8. Close the technical gaps (backups + restore record, schedule monitor, email) so OQ steps pass.
9. **PQ** scenarios at scale.
10. **SOPs** approved.
11. **VSR** + approvals -> validated state. Then operate under change control + periodic review.

---

## 12. DELIVERABLES STATUS

| # | Deliverable | Owner | Status |
|---|---|---|---|
| 00 | Validation Plan | QA/Owner | TO AUTHOR (this plan is the basis) |
| 01 | URS | Owner | TO AUTHOR (source: master plan + SOP) |
| 02 | FS | SME | TO AUTHOR |
| 03 | DS | Dev | TO AUTHOR (auto-derivable from code) |
| 04 | RA + Part 11 | QA | TO AUTHOR (Section 7 seed) |
| 05 | RTM | SME | TO SCAFFOLD (auto from code) |
| 06 | IQ | IT/SME | TO AUTHOR (deploy verify = partial evidence) |
| 07 | OQ | SME/QA | TO AUTHOR |
| 08 | PQ | Owner/QA | TO AUTHOR |
| 09 | VSR | QA | AFTER IQ/OQ/PQ |
| 10 | SOPs | QA | TO AUTHOR (8 procedures) |

---

## 13. ASSUMPTIONS & OPEN ITEMS

- All documents refit to **Astellas CSV templates, numbering, and the local validation SOP** once
  provided; the structure above maps cleanly to a standard Astellas package.
- QA governs and approves; these are working drafts until signed.
- Technical gaps that must close before the VSR are tracked in `docs/GQS_OPEN_ISSUES.md` (backups +
  restore-verification record, scheduled-job monitoring, the SOP set, production email delivery,
  personnel dedupe completed, manager/capability flags set).
- Platform/infra qualification (OS, network, RDS) is leveraged from Astellas IT, not re-performed here.

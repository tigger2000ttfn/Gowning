# RISK ASSESSMENT & 21 CFR PART 11 ASSESSMENT (RA)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | RA-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT — for QA review |
| System | MATC Gowning Qualification System (GQS) |
| GxP Impact | GxP — Direct |
| References | VP-GQS-001, URS-GQS-001, FS-GQS-001, DS-GQS-001, GAMP 5, ICH Q9, 21 CFR Part 11 |

### Approvals
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| Quality Assurance | | | | |
| System Owner | | | | |

### Revision History
| Version | Date | Author | Description |
|---|---|---|---|
| 1.0 | 30-MAY-2026 | | Initial draft |

---

## 1. PURPOSE & SCOPE
This document assesses risks to product quality, patient safety, and data integrity arising from the
GQS computerized system, identifies the controls that mitigate them, and assesses the system against
21 CFR Part 11. It informs the risk-based test depth in OQ-GQS-001 / PQ-GQS-001. Scope matches
VP-GQS-001.

## 2. METHODOLOGY (GAMP 5 / ICH Q9)
A functional risk assessment (FMEA-style) is applied to GMP-relevant functions. Each function is rated
for **Severity (S)** of harm if it fails, **Probability (P)** of the failure occurring, and
**Detectability (D)** of the failure before impact, each Low/Medium/High.

- **Risk Class** is derived from Severity × Probability; **Risk Priority** then factors Detectability.
- **Risk Class:** High (S High with P Med/High; or S Med with P High), Medium, Low.
- Required test rigor: High -> full scripted OQ + PQ scenario, witnessed where signatures apply;
  Medium -> scripted functional test; Low -> light verification / inspection.

Residual risk is the risk remaining after the listed control(s) and verifying test(s).

## 3. FUNCTIONAL RISK ASSESSMENT
| Ref | Function (URS) | Failure mode | Effect | S | P | D | Class | Control (built-in) | Verifying test | Residual |
|---|---|---|---|---|---|---|---|---|---|---|
| R-01 | Stage sequencing (GEN-030) | Person advances without prerequisites | Unqualified person treated as qualified | H | L | M | Med | State machine enforces order; QA gate before Qualified | OQ workflow + negative | Low |
| R-02 | Class-as-prerequisite & completion (CLS-050) | Class falsely marked complete | Run-eligible without training | H | L | M | Med | Only QA approval sets class-on-file/ClassComplete | OQ class flow | Low |
| R-03 | Attendance draft vs submitted (CLS-030/040/060) | Status changes before trainer e-sign | Premature/incorrect status; weakened Part 11 e-sign | H | L | H | Med | Deferred in-memory intent; status set only on submit (trainer e-sign); board shortcuts blocked | OQ attendance + negative (drag) | Low |
| R-04 | Two-person rule (GEN-060) | Self-qualification | Integrity/compliance breach | H | L | M | Med | Performer ≠ qualified; QCM (not self) reviews | OQ negative (self) | Low |
| R-05 | Run performed -> incubation (RUN-020/030) | Incorrect incubation timing | Premature results / invalid qualification | H | L | M | Med | Timer from performed date; configurable incubation days | OQ incubation timing | Low |
| R-06 | Results pass/fail routing (RES-010/020/030) | Fail recorded as pass (or mis-routed) | Failed run treated as passing -> false qualification | H | L | M | Med | Pass->QA review; fail->Failed + auto-NC; spec configurable | OQ pass + fail paths | Low |
| R-07 | Pass counting (RES-050/060) | Failed run counts / passes reset | Wrong number of valid runs | H | L | M | Med | Failed excluded; prior passes preserved | OQ counting | Low |
| R-08 | QA electronic sign-off (QA-010/020) | Sign-off without authority or binding | Unauthorized/repudiable approval | H | L | M | Med | Two-component e-sign (identity+meaning), re-auth, bound to record; capability-gated | OQ e-sign (witnessed) | Low |
| R-09 | Due date / qualified calc (QA-030, LIF-010) | Wrong due date | Person qualifies/lapses at wrong time | H | L | M | Med | due = pass + cycle (config) | OQ due calc | Low |
| R-10 | Auto lapse / requal (LIF-030/040) | Lapsed not detected, or wrong requal scope | Expired person treated as qualified | H | L | M | Med | Scheduled lapse + grace; lapsed -> initial(3) | OQ + PQ over simulated time | Low |
| R-11 | QA determination on failure (QA-050) | Wrong requal count / no rationale | Inadequate requalification | H | L | M | Med | Determination 1/2/3 + retraining + rationale recorded | OQ determination | Low |
| R-12 | Audit trail (AUD-010/020) | Change not captured / prior value lost / alterable | Loss of attributability / data-integrity finding | H | L | M | Med | Computer-generated audit trail retains prior values; not user-editable | OQ audit capture + review SOP | Low |
| R-13 | Record deletion (ER-050) | Hard delete of GMP record | Record destroyed | H | L | H | Med | Soft delete only; no hard-delete paths | OQ delete test | Low |
| R-14 | Access control / RBAC (SEC-010/020) | Unauthorized action | Integrity/security breach | H | L | M | Med | Unique login; capability matrix; authority checks | OQ RBAC negative | Low |
| R-15 | Account provisioning (PUB-030, SEC-040) | Self-granted access | Unauthorized access | H | L | M | Med | Approval workflow before access | OQ approval flow | Low |
| R-16 | External linkage vs transcription (RUN-040, DI-040) | Manual transcription error from LIMS/Veeva | Inaccurate record | M | M | M | Med | Reference worklist/doc, not transcribe | OQ linkage review | Low |
| R-17 | Forms generation (REP-020) | Form prints wrong/missing data | Incorrect official record | H | L | H | Med | Forms populated from system data | OQ form verification | Low |
| R-18 | Capacity limits (CLS-020, SCH-030) | Over-capacity scheduling | Operational error | M | M | M | Med | Capacity caps enforced | OQ capacity | Low |
| R-19 | Scheduled automation (NFR-020) | Daily jobs do not run | Lapses/notifications missed | M | M | M | Med | Cron; (planned) schedule monitor | IQ cron + PQ; monitor evidence | Med* |
| R-20 | Backup / recovery (NFR-030) | Data loss without recovery | Record loss | H | L | H | Med | (Planned) scheduled backups + restore-verification | IQ + Backup SOP | Med* |
| R-21 | Notifications/email (REP, automation) | Notices not delivered | Missed action | M | M | M | Med | Queued; (pending) production relay | OQ queue; PQ after relay | Med* |
| R-22 | Time zone / date format (DI-030) | Ambiguous timestamps | Mis-dated records | M | L | M | Low | America/New_York; DD-MON-YYYY | OQ timestamp check | Low |

*Items marked Med residual are gated on technical prerequisites tracked in `docs/GQS_OPEN_ISSUES.md`
(production mail relay, scheduled backups + restore-verification record, scheduled-job monitoring).
They must be closed and re-rated before VSR approval.

## 4. 21 CFR PART 11 ASSESSMENT
| Clause | Requirement | GQS assessment | Status |
|---|---|---|---|
| 11.10(a) | Validation | System being validated per VP-GQS-001 (this package). | In progress |
| 11.10(b) | Accurate/ready copies | Human-readable records + Astellas-formatted PDF forms; retrievable. | Met (verify in OQ) |
| 11.10(c) | Record protection/retention | HTTPS + SSL; soft deletes; (planned) scheduled backups. | Partial — backup SOP pending |
| 11.10(d) | Limited system access | Unique logins; capability-based RBAC; account approval. | Met (verify in OQ) |
| 11.10(e) | Audit trail | Computer-generated, time-stamped, retains prior values, not user-editable. | Met (verify in OQ) |
| 11.10(f) | Operational sequencing | State machine enforces stage order; shortcuts to attended/complete blocked. | Met (verify in OQ) |
| 11.10(g) | Authority checks | Capability checks gate actions by role. | Met (verify in OQ) |
| 11.10(h) | Device checks | N/A (no instrument data acquisition by GQS; LIMS owns instruments). | N/A |
| 11.10(i) | Personnel qualifications | Procedural (training of users) — SOP. | SOP pending |
| 11.10(j) | Accountability policy | Procedural — e-signature SOP. | SOP pending |
| 11.10(k) | System documentation control | Versioned with code; change control. | Met via git + SOP |
| 11.50 | Signature manifestations | Signer name, date/time, and meaning captured and displayed. | Met (verify in OQ) |
| 11.70 | Signature/record linking | Signatures bound to their records; cannot be excised/transferred. | Met (verify in OQ) |
| 11.100 | Unique signatures | Tied to unique individual accounts; not reused. | Met (verify in OQ) |
| 11.200 | Signature components/controls | Re-authentication at signing; component controls. | Met (verify in OQ) |
| 11.300 | Identification code/password controls | Password controls (e.g., forced first-login change); procedural management. | Partial — SOP pending |

## 5. DATA INTEGRITY (ALCOA+)
| Attribute | How GQS supports it |
|---|---|
| Attributable | Every create/change carries the acting user; audit trail records who. |
| Legible | Records and forms are human-readable and exportable. |
| Contemporaneous | Events are system-timestamped at the time of action. |
| Original | The system is the originating record for qualification status; external data referenced, not copied. |
| Accurate | Enforced fields, controlled vocabularies, linkage to source systems, validation rules. |
| Complete | Required fields + the full audit trail of changes. |
| Consistent | Defined enums/states; one workflow; defined time zone and date format. |
| Enduring | Persistent storage; (planned) scheduled backups. |
| Available | Retrievable for review/inspection throughout retention. |

## 6. CONCLUSION (RISK-BASED TEST DEPTH)
The GMP-critical functions (R-01 through R-17) are mitigated by built-in technical controls and shall
receive full scripted OQ coverage, with QA-witnessed execution for the electronic-signature steps
(R-03, R-08) and negative testing for the integrity controls (sequencing, self-qualification, board
shortcuts, hard-delete, RBAC). Performance-related and time-dependent behaviors (R-10, R-19) shall be
confirmed in PQ over simulated time and at population scale. The three Medium residual items (R-19,
R-20, R-21) depend on technical prerequisites that must be implemented and verified, and the affected
clauses (11.10(c), 11.300) and SOPs completed, before the Validation Summary Report is approved.

## 7. RESIDUAL RISK STATEMENT
With the listed controls verified and the open technical prerequisites and SOPs completed, residual
risk is assessed as **Low** and acceptable for intended use. QA approval of this assessment confirms
the methodology, ratings, and the conditions for VSR approval.

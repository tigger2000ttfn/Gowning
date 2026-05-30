# VALIDATION SUMMARY REPORT (VSR)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | VSR-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 (DRAFT — template; completed after execution) |
| System | MATC Gowning Qualification System (GQS) |
| Validated baseline | git `main` @ tag/commit: ____________ (record at release) |

### Approvals
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| System Owner | | | | |
| Quality Assurance | | | | |

### Revision History
| Version | Date | Author | Description |
|---|---|---|---|
| 1.0 | 30-MAY-2026 | | Initial template (to be completed upon completion of IQ/OQ/PQ) |

> This is the report **template**. Fields marked "[complete at execution]" are filled once the
> qualification activities are executed and deviations are dispositioned.

---

## 1. PURPOSE
To summarize the validation activities performed for GQS and conclude whether the system is validated
and fit for its intended use per VP-GQS-001 and SOP-AST-30419.

## 2. SYSTEM SUMMARY
GQS is a GAMP 5 Category 5 custom application managing the cleanroom gowning qualification lifecycle
under 21 CFR Part 11. (See DS-GQS-001 for the as-built description.)

## 3. VALIDATION ACTIVITIES PERFORMED
| Deliverable | Document | Version | Approved | Notes |
|---|---|---|---|---|
| Validation Plan | VP-GQS-001 | | | |
| User Requirements | URS-GQS-001 | | | |
| Functional Spec | FS-GQS-001 | | | |
| Design/Config Spec | DS-GQS-001 | | | |
| Risk + Part 11 Assessment | RA-GQS-001 | | | |
| Traceability Matrix | RTM-GQS-001 | | | |
| Installation Qualification | IQ-GQS-001 | | | [complete at execution] |
| Operational Qualification | OQ-GQS-001 | | | [complete at execution] |
| Performance Qualification | PQ-GQS-001 | | | [complete at execution] |
| SOPs | SOP-GQS-001..008 | | | |

## 4. SUMMARY OF RESULTS  *(complete at execution)*
| Phase | Cases executed | Passed | Failed (deviations) | Result |
|---|---|---|---|---|
| IQ | | | | |
| OQ | | | | |
| PQ | | | | |

## 5. DEVIATIONS SUMMARY  *(complete at execution)*
| # | Phase / Test | Description | Disposition | Status |
|---|---|---|---|---|
| | | | | |
All deviations are resolved (corrected + re-tested) or formally risk-accepted by QA prior to approval.

## 6. TRACEABILITY CONFIRMATION  *(complete at execution)*
Confirmation that every approved URS requirement traces through FS/DS to a passed OQ/PQ test in
RTM-GQS-001 with **no open gaps**. Note any requirements verified by alternative means and the rationale.

## 7. OUTSTANDING ITEMS, RESIDUAL RISK & RESTRICTIONS  *(complete at execution)*
- Status of the technical prerequisites from RA-GQS-001 (R-19 scheduled-job monitor, R-20 backups +
  restore-verification, R-21 production email) — all must be **closed** (implemented + verified) before
  approval, or any residual restriction on use must be stated here.
- Status of SOP approval/effectiveness (SOP-GQS-001..008).
- Any agreed restrictions or conditions of use.
- Residual risk statement (target: Low/acceptable).

## 8. CONCLUSION  *(complete at execution)*
Based on the executed and approved IQ, OQ, and PQ, the closure of deviations, complete traceability, the
demonstrated Part 11 controls, and effective SOPs, GQS **[is / is not] validated and fit for intended
use** as of the validated baseline recorded above. Ongoing compliance is maintained under Change Control
(SOP-GQS-005) and Periodic Review (SOP-GQS-006).

## 9. RELEASE DECISION  *(complete at execution)*
The System Owner and Quality Assurance approve the release of GQS for production use (signatures above).

## 10. REFERENCES
VP-GQS-001; URS/FS/DS/RA/RTM; IQ/OQ/PQ-GQS-001; SOP-GQS-001..008; SOP-AST-30419; 21 CFR Part 11; GAMP 5.

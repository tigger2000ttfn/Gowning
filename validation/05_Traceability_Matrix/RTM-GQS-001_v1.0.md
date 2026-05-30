# REQUIREMENTS TRACEABILITY MATRIX (RTM)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | RTM-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT — for QA review |
| System | MATC Gowning Qualification System (GQS) |
| References | URS-GQS-001, FS-GQS-001, DS-GQS-001, RA-GQS-001, OQ-GQS-001, PQ-GQS-001 |
| As-built baseline | git `main` @ c21df91 |

### Approvals
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| Quality Assurance | | | | |

### Revision History
| Version | Date | Baseline | Author | Description |
|---|---|---|---|---|
| 1.0 | 30-MAY-2026 | main @ c21df91 | | Initial matrix; full URS coverage; FS/OQ IDs reserved and traced |

> **Maintenance rule (living document):** updated at the **end of every development session** alongside
> DS-GQS-001; any new/changed URS or FS adds/updates a row here and is logged in
> `validation/CHANGE_CONTROL_LOG.md`. No requirement may exist without a row.

---

## 1. HOW TO READ
Each User Requirement (URS) is traced forward to its Functional Specification item (**FS ID** — mirrors
the URS suffix; FS-GQS-001 to be authored), its **Design Spec section** (DS-GQS-001 §), the
**verifying test** (OQ/PQ ID — OQ-GQS-001/PQ-GQS-001 to be authored), and a **Verification status**:
- **Impl** = implemented in the baseline; OQ to execute.
- **Impl (witnessed)** = implemented; OQ executed with a real e-signature.
- **Impl (neg)** = implemented; verified by a negative test.
- **PQ** = confirmed in performance qualification (real-use / over time / at scale).
- **Gap (Rxx)** = depends on a technical prerequisite per RA-GQS-001; close before VSR.
- **Future** = planned, not in this baseline.

Critical (C) requirements receive full OQ (and PQ where time/scale dependent) per RA-GQS-001.

## 2. TRACEABILITY TABLE

### Process (GEN)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-GEN-010 | FS-GEN-010 | 3, 6 | OQ-GEN-010 | Impl |
| URS-GEN-020 | FS-GEN-020 | 6, 7 | OQ-GEN-020 | Impl |
| URS-GEN-030 | FS-GEN-030 | 7, 14 | OQ-GEN-030 | Impl (neg) |
| URS-GEN-040 | FS-GEN-040 | 7, 8 | OQ-GEN-040 | Impl |
| URS-GEN-050 | FS-GEN-050 | 6, 10 | OQ-GEN-050 | Impl |
| URS-GEN-060 | FS-GEN-060 | 9, 14 | OQ-GEN-060 | Impl (neg) |
| URS-GEN-070 | FS-GEN-070 | 9, 14 | OQ-GEN-070 | Impl |

### Personnel (PER)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-PER-010 | FS-PER-010 | 6 | OQ-PER-010 | Impl |
| URS-PER-020 | FS-PER-020 | 6, 9 | OQ-PER-020 | Impl |
| URS-PER-030 | FS-PER-030 | 6 | OQ-PER-030 | Impl |
| URS-PER-040 | FS-PER-040 | 6 | OQ-PER-040 | Impl |
| URS-PER-050 | FS-PER-050 | 6 | OQ-PER-050 | Impl |

### Classroom (CLS)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-CLS-010 | FS-CLS-010 | 6 | OQ-CLS-010 | Impl |
| URS-CLS-020 | FS-CLS-020 | 6, 8 | OQ-CLS-020 | Impl |
| URS-CLS-030 | FS-CLS-030 | 6, 10 | OQ-CLS-030 | Impl |
| URS-CLS-040 | FS-CLS-040 | 10 | OQ-CLS-040 | Impl (witnessed) |
| URS-CLS-050 | FS-CLS-050 | 6, 10 | OQ-CLS-050 | Impl |
| URS-CLS-060 | FS-CLS-060 | 7, 14 | OQ-CLS-060 | Impl (neg) |
| URS-CLS-070 | FS-CLS-070 | 10, 14 | OQ-CLS-070 | Impl |
| URS-CLS-080 | FS-CLS-080 | 6, 10 | OQ-CLS-080 | Impl |

### Scheduling (SCH)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-SCH-010 | FS-SCH-010 | 6 | OQ-SCH-010 | Impl |
| URS-SCH-020 | FS-SCH-020 | 13 | OQ-SCH-020 / PQ-SCH-020 | Impl / PQ |
| URS-SCH-030 | FS-SCH-030 | 6, 8 | OQ-SCH-030 | Impl |
| URS-SCH-040 | FS-SCH-040 | 6, 11 | OQ-SCH-040 | Impl |
| URS-SCH-050 | FS-SCH-050 | 6 | OQ-SCH-050 | Impl |
| URS-SCH-060 | FS-SCH-060 | 14 | OQ-SCH-060 | Impl |

### Runs & Incubation (RUN)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-RUN-010 | FS-RUN-010 | 6, 12 | OQ-RUN-010 | Impl |
| URS-RUN-020 | FS-RUN-020 | 7, 13 | OQ-RUN-020 | Impl |
| URS-RUN-030 | FS-RUN-030 | 13 | OQ-RUN-030 / PQ-RUN-030 | Impl / PQ |
| URS-RUN-040 | FS-RUN-040 | 12 | OQ-RUN-040 | Impl |
| URS-RUN-050 | FS-RUN-050 | 6 | OQ-RUN-050 | Impl |

### Results & Disposition (RES)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-RES-010 | FS-RES-010 | 6, 8 | OQ-RES-010 | Impl |
| URS-RES-020 | FS-RES-020 | 7 | OQ-RES-020 | Impl |
| URS-RES-030 | FS-RES-030 | 6, 7 | OQ-RES-030 | Impl |
| URS-RES-040 | FS-RES-040 | 6 | OQ-RES-040 | Impl |
| URS-RES-050 | FS-RES-050 | 7 | OQ-RES-050 | Impl |
| URS-RES-060 | FS-RES-060 | 6 | OQ-RES-060 | Impl |

### QA Review & Sign-off (QA)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-QA-010 | FS-QA-010 | 10 | OQ-QA-010 | Impl (witnessed) |
| URS-QA-020 | FS-QA-020 | 10 | OQ-QA-020 | Impl (witnessed) |
| URS-QA-030 | FS-QA-030 | 6, 8 | OQ-QA-030 | Impl |
| URS-QA-040 | FS-QA-040 | 10, 14 | OQ-QA-040 | Impl |
| URS-QA-050 | FS-QA-050 | 6 | OQ-QA-050 | Impl |
| URS-QA-060 | FS-QA-060 | 12, 14 | OQ-QA-060 | Impl |

### Requalification & Lifecycle (LIF)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-LIF-010 | FS-LIF-010 | 6 | OQ-LIF-010 | Impl |
| URS-LIF-020 | FS-LIF-020 | 7, 8 | OQ-LIF-020 | Impl |
| URS-LIF-030 | FS-LIF-030 | 8, 13 | OQ-LIF-030 / PQ-LIF-030 | Impl / PQ |
| URS-LIF-040 | FS-LIF-040 | 7 | OQ-LIF-040 | Impl |
| URS-LIF-050 | FS-LIF-050 | 6, 10 | OQ-LIF-050 | Impl |

### Non-Conformance (NC)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-NC-010 | FS-NC-010 | 6, 7 | OQ-NC-010 | Impl |
| URS-NC-020 | FS-NC-020 | 12 | OQ-NC-020 | Impl |
| URS-NC-030 | FS-NC-030 | 6 | OQ-NC-030 | Impl |

### Teams & Assignment (TEAM)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-TEAM-010 | FS-TEAM-010 | 9 | OQ-TEAM-010 | Impl |
| URS-TEAM-020 | FS-TEAM-020 | 6, 14 | OQ-TEAM-020 | Impl |
| URS-TEAM-030 | FS-TEAM-030 | 6 | OQ-TEAM-030 | Impl |
| URS-TEAM-040 | FS-TEAM-040 | 14 | OQ-TEAM-040 | Impl |

### Reporting & Records (REP)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-REP-010 | FS-REP-010 | 14 | OQ-REP-010 | Impl |
| URS-REP-020 | FS-REP-020 | 10, 14 | OQ-REP-020 | Impl |
| URS-REP-030 | FS-REP-030 | 10, 16 | OQ-REP-030 | Impl |
| URS-REP-040 | FS-REP-040 | 14 | OQ-REP-040 | Impl |

### Public Self-Service (PUB)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-PUB-010 | FS-PUB-010 | 14 | OQ-PUB-010 | Impl |
| URS-PUB-020 | FS-PUB-020 | 6, 14 | OQ-PUB-020 | Impl |
| URS-PUB-030 | FS-PUB-030 | 9 | OQ-PUB-030 | Impl (neg) |

### Security & Access (SEC)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-SEC-010 | FS-SEC-010 | 9 | OQ-SEC-010 | Impl |
| URS-SEC-020 | FS-SEC-020 | 9 | OQ-SEC-020 | Impl (neg) |
| URS-SEC-030 | FS-SEC-030 | 9 | OQ-SEC-030 | Impl |
| URS-SEC-040 | FS-SEC-040 | 9 | OQ-SEC-040 | Impl |
| URS-SEC-050 | FS-SEC-050 | 9 | OQ-SEC-050 | Impl |
| URS-SEC-060 | FS-SEC-060 | 5, 9 | OQ-SEC-060 / IQ-SEC-060 | Impl |

### Electronic Records & Signatures (ER)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-ER-010 | FS-ER-010 | 10 | OQ-ER-010 | Impl (witnessed) |
| URS-ER-020 | FS-ER-020 | 10 | OQ-ER-020 | Impl (witnessed) |
| URS-ER-030 | FS-ER-030 | 10 | OQ-ER-030 | Impl (witnessed) |
| URS-ER-040 | FS-ER-040 | 5, 10, 16 | OQ-ER-040 | Impl |
| URS-ER-050 | FS-ER-050 | 6, 10 | OQ-ER-050 | Impl (neg) |

### Data Integrity / ALCOA+ (DI)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-DI-010 | FS-DI-010 | 10, 11 | OQ-DI-010 | Impl |
| URS-DI-020 | FS-DI-020 | 5, 11 | OQ-DI-020 | Impl |
| URS-DI-030 | FS-DI-030 | 5 | OQ-DI-030 | Impl |
| URS-DI-040 | FS-DI-040 | 12 | OQ-DI-040 | Impl |
| URS-DI-050 | FS-DI-050 | 6 | OQ-DI-050 | Impl |

### Audit Trail (AUD)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-AUD-010 | FS-AUD-010 | 11 | OQ-AUD-010 | Impl |
| URS-AUD-020 | FS-AUD-020 | 11 | OQ-AUD-020 | Impl (neg) |
| URS-AUD-030 | FS-AUD-030 | 11 | OQ-AUD-030 | Impl |

### Interfaces (INT)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-INT-010 | FS-INT-010 | 12 | OQ-INT-010 | Impl |
| URS-INT-020 | FS-INT-020 | 12 | OQ-INT-020 | Impl |
| URS-INT-030 | FS-INT-030 | 12 | OQ-INT-030 | Impl |
| URS-INT-040 | FS-INT-040 | 12 | OQ-INT-040 | Future |

### Non-Functional (NFR)
| URS | FS | DS § | Test | Status |
|---|---|---|---|---|
| URS-NFR-010 | FS-NFR-010 | 3, 4 | PQ-NFR-010 | PQ |
| URS-NFR-020 | FS-NFR-020 | 13 | OQ-NFR-020 / IQ-NFR-020 / PQ-NFR-020 | Impl / PQ |
| URS-NFR-030 | FS-NFR-030 | 16 | IQ-NFR-030 | Gap (R-20) |
| URS-NFR-040 | FS-NFR-040 | 13 | OQ-NFR-040 | Gap (R-19) |
| URS-NFR-050 | FS-NFR-050 | 9, 14 | OQ-NFR-050 | Impl |

## 3. COVERAGE SUMMARY
- **Total requirements:** as enumerated in URS-GQS-001 (≈90 across 18 areas), every one traced above.
- **Implemented in baseline (OQ to execute):** the large majority.
- **e-Signature / witnessed:** CLS-040, QA-010/020, ER-010/020/030.
- **Negative tests:** GEN-030/060, CLS-060, PUB-030, SEC-020, ER-050, AUD-020.
- **Performance/time/scale (PQ):** SCH-020, RUN-030, LIF-030, NFR-010/020.
- **Open gaps to close before VSR:** NFR-030 (backups/restore-verification, RA R-20), NFR-040
  (scheduled-job monitor, RA R-19); also production email delivery for the REP/notification path.
- **Future (not this baseline):** INT-040 (periodic LIMS import — Master Plan Section 10).

## 4. NOTES
FS-GQS-001 is authored (v1.0; one functional item per URS requirement). OQ-GQS-001 is authored (v1.0),
organized as test cases TC-01..TC-16; each "OQ-…" reference in the rows above is realized by the OQ test
case that covers that requirement (see the OQ traceability mapping in OQ-GQS-001 §8). PQ-GQS-001 is
forthcoming; its IDs are reserved here. When a requirement changes, update its URS text, the FS item, the
DS section, this row, and the OQ/PQ test, in the same session, and log it in the change-control log.

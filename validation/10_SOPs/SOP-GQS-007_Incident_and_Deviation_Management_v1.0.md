# SOP — INCIDENT & DEVIATION MANAGEMENT
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | SOP-GQS-007 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 (DRAFT) |
| System | MATC Gowning Qualification System (GQS) |
| Effective Date | Upon approval |

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

## 1. PURPOSE
To define how incidents and deviations involving GQS or its data are reported, assessed, investigated,
and resolved, protecting data integrity and the validated state.

## 2. SCOPE
System errors/malfunctions, unplanned downtime, suspected or actual **data-integrity** issues, security
events, audit-trail review findings, and any GQS behavior inconsistent with FS-GQS-001.
(Quality non-conformances of the gowning process itself, e.g., a failed run, are handled in-system as
Non-Conformances and via the site quality process; this SOP covers the *computerized system*.)

## 3. RESPONSIBILITIES
- **Any user:** promptly reports a suspected incident/deviation.
- **System Owner:** triages and coordinates resolution.
- **IT / Developer:** investigates technical root cause; implements fixes via Change Control.
- **Quality Assurance:** assesses GxP/data-integrity impact, approves the investigation and CAPA, and
  closes the record.

## 4. PROCEDURE
### 4.1 Report & log
1. The incident/deviation is reported and logged with date/time, description, who identified it, and
   affected records/functions.

### 4.2 Assess & contain
1. QA (with the Owner/IT) assesses **severity and GxP/data-integrity impact** and scope (records,
   users, period affected).
2. Immediate containment is taken as needed (e.g., restrict an action, take the system offline, invoke
   BC/DR per SOP-GQS-008).

### 4.3 Investigate
1. Root cause is investigated using available evidence (audit trail, logs, reproduction).
2. Impact on existing records and on the validated state is determined.

### 4.4 Correct & prevent (CAPA)
1. Corrective actions (and preventive actions) are defined. System changes are implemented under
   SOP-GQS-005 (Change Control) with the applicable re-testing.
2. Data corrections are made under control and fully audited; original values remain in the audit trail.

### 4.5 Approve & close
1. QA reviews and approves the investigation and CAPA; the record is closed.
2. Significant incidents feed the periodic review (SOP-GQS-006).

## 5. RECORDS
Incident/deviation records, investigation and CAPA records, related Change Control and audit-trail
review records.

## 6. REFERENCES
21 CFR Part 11; GAMP 5; SOP-GQS-003; SOP-GQS-005; SOP-GQS-006; SOP-GQS-008; DS-GQS-001.

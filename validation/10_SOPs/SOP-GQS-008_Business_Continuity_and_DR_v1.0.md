# SOP — BUSINESS CONTINUITY & DISASTER RECOVERY
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | SOP-GQS-008 *(placeholder; refit to Astellas numbering)* |
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
To define how GQS service and data are recovered after a disruption, within agreed recovery objectives,
and how the gowning qualification process continues during an outage.

## 2. SCOPE
The GQS application, its database (AWS RDS), and its hosting environment.

## 3. RESPONSIBILITIES
- **IT / Infrastructure:** executes recovery; maintains RDS/server availability.
- **System Owner:** declares an outage, authorizes recovery, and manages the interim process.
- **Quality Assurance:** confirms data integrity post-recovery; reviews the post-incident record.

## 4. PROCEDURE
### 4.1 Recovery objectives
1. **RTO** (recovery time objective) and **RPO** (recovery point objective) are defined with the business
   and recorded here: RTO = ____; RPO = ____.

### 4.2 Recovery steps
1. Provision/restore the server (or replacement) and the runtime environment.
2. **Redeploy** GQS from the approved tagged baseline via the controlled deployment (`deploy.sh`).
3. **Restore the database** from the most recent valid RDS snapshot/PITR and/or application backup per
   SOP-GQS-002, consistent with the deployed baseline and the RPO.
4. **Verify** recovery: run the relevant IQ checks (IQ-GQS-001) and a restore-verification (SOP-GQS-002)
   confirming data, audit trail, and electronic signatures are intact.
5. **Reinstate** the scheduler (cron) and confirm scheduled automation resumes.
6. Communicate restoration to users.

### 4.3 Interim (continuity) process
1. During an outage, the gowning qualification process continues under a defined manual/interim
   procedure (e.g., paper/temporary records). Interim records are reconciled into GQS after recovery,
   under control and fully audited.

### 4.4 Post-incident
1. The event is handled as an incident under SOP-GQS-007; a post-incident review captures lessons and
   any CAPA.

### 4.5 DR testing
1. A recovery/DR test is performed at the defined frequency to demonstrate the plan works, producing a
   test record.

## 5. RECORDS
BC/DR plan and test records; recovery records; restore-verification records; post-incident reviews.

## 6. REFERENCES
SOP-GQS-002; SOP-GQS-007; IQ-GQS-001; DS-GQS-001 §15, §16; VP-GQS-001.

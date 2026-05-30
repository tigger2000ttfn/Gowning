# SOP — BACKUP & RESTORE
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | SOP-GQS-002 *(placeholder; refit to Astellas numbering)* |
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
To define how GQS data and configuration are backed up and restored, and how restores are verified, so
electronic records are protected and retrievable throughout the retention period (21 CFR 11.10(b),(c)).

## 2. SCOPE
The GQS database (AWS RDS PostgreSQL), application code and configuration (version control), any uploaded
media/attachments (when implemented), and the validation package.

## 3. RESPONSIBILITIES
- **IT / Infrastructure:** configures and monitors backups; performs restores and restore tests.
- **System Owner:** ensures backups and restore tests occur at the defined cadence.
- **Quality Assurance:** approves this SOP; reviews restore-verification records.

## 4. PROCEDURE
### 4.1 Database backups
1. **Platform backups:** AWS RDS automated backups/snapshots are enabled per the Astellas RDS standard
   (retention period and point-in-time recovery window recorded).
2. **Application-level scheduled backups** *(to be implemented — RA-GQS-001 R-20):* a scheduled database
   export retained per the retention policy, with success logged.

### 4.2 Application & configuration
1. Application code and configuration are maintained in version control; any release is reproducible
   from its tagged commit via the controlled deployment (`deploy.sh`).
2. Uploaded media/attachments (when the feature is implemented) are included in backups.

### 4.3 Restore
1. Restores are performed by IT using a documented procedure to a defined point in time (RDS
   snapshot/PITR and/or the application-level export).
2. Application is redeployed from the corresponding tagged baseline to match the restored data.

### 4.4 Restore verification
1. After a restore (real or test), IT/QA verify integrity: representative record counts, that the
   **audit trail and electronic signatures are intact**, and that key records reconcile.
2. A **restore-verification record** is produced and signed.

### 4.5 Restore testing
1. A restore test is performed at the defined frequency (e.g., periodically/annually) to demonstrate
   recoverability, producing a restore-verification record (supports PQ-GQS-001 PQ-09).

### 4.6 Retention
1. Backups and records are retained per the Astellas record-retention policy applicable to GMP
   electronic records.

## 5. RECORDS
Backup configuration and logs; restore-verification records; restore-test records; retention schedule.

## 6. REFERENCES
21 CFR Part 11 (11.10(b),(c)); DS-GQS-001 §16; PQ-GQS-001 (PQ-09); SOP-GQS-008; RA-GQS-001 (R-20).

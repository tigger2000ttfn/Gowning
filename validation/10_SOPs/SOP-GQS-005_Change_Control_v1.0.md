# SOP — CHANGE CONTROL
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | SOP-GQS-005 *(placeholder; refit to Astellas numbering)* |
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
To control changes to the validated GQS so that the validated state is maintained and changes are
assessed for impact, tested, documented, and approved before production use (GAMP 5; 21 CFR 11.10(a),(k)).

## 2. SCOPE
All changes to GQS application code, configuration, database schema, the hosting/deployment
configuration, and the validation documents. Excludes changes to leveraged platform/infrastructure
(managed under Astellas IT change control).

## 3. RESPONSIBILITIES
- **Requestor:** raises the change with a description and rationale.
- **Developer:** implements the change in version control; supplies the impact detail and updates the
  affected validation documents.
- **System Owner:** authorizes the change and its release.
- **Quality Assurance:** assesses GxP/validation impact, determines testing/re-validation scope, and
  approves the change and its closure.

## 4. DEFINITIONS
- **Baseline:** the validated configuration, identified by a git tag/commit.
- **Release:** a deployed baseline.

## 5. PROCEDURE
### 5.1 Request & impact assessment
1. A change is requested with description, reason, and the affected area.
2. QA (with the Owner/Developer) assesses **GxP and validation impact**: which URS/FS/DS items and which
   OQ/PQ tests are affected, and the **re-validation scope** (risk-based; not necessarily full re-test).

### 5.2 Implementation
1. The Developer implements the change in version control (single protected branch; one logical change
   per commit) and updates the affected **URS/FS/DS/RTM** and any **OQ/PQ** test.
2. The change and its validation-document updates are recorded in `validation/CHANGE_CONTROL_LOG.md`
   (CC-nnnn) referencing the commit, per the end-of-session living-validation rule.

### 5.3 Testing & approval
1. The applicable IQ/OQ/PQ tests (per the impact scope) are executed and pass.
2. The System Owner authorizes and QA approves the change for release.

### 5.4 Release & baseline
1. The release is deployed to production via the controlled deployment (`deploy.sh`), which fetches and
   resets to the approved commit, applies migrations, rebuilds assets (with build-failure rollback), and
   verifies the deployed commit.
2. The release is **tagged**; the tag is the new validated baseline and is recorded in the affected
   documents' revision history.

### 5.5 Emergency changes
1. Emergency changes to restore service may be made with verbal/expedited authorization, then documented
   and assessed retrospectively within a defined window (e.g., a set number of business days) and brought
   under this SOP.

## 6. RECORDS
Change records / `CHANGE_CONTROL_LOG.md` entries; git history and release tags; updated validation
documents; executed re-test evidence; approvals.

## 7. REFERENCES
GAMP 5; 21 CFR Part 11 (11.10(a),(k)); VP-GQS-001 §12; DS-GQS-001 §15, §17; RTM-GQS-001;
`validation/CHANGE_CONTROL_LOG.md`; SOP-GQS-006.

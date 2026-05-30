# SOP — USER ACCESS MANAGEMENT
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | SOP-GQS-001 *(placeholder; refit to Astellas numbering)* |
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
To define how user accounts and access privileges in GQS are requested, granted, modified, reviewed, and
revoked, ensuring access is limited to authorized individuals (21 CFR 11.10(d),(g)).

## 2. SCOPE
All GQS user accounts and the role-based capability assignments that govern what each user may do.

## 3. RESPONSIBILITIES
- **System Owner / Manager:** authorizes access requests and the role assigned.
- **System Administrator (ManageUsers/ManageRoles capability):** provisions, modifies, and revokes
  accounts and maintains the capability matrix.
- **Quality Assurance:** approves this SOP; performs/witnesses periodic access reviews.
- **User:** safeguards their unique credentials; does not share accounts.

## 4. DEFINITIONS
- **Capability:** a permission (e.g., RecordRuns, QaApprove) granted to one or more roles.
- **Role:** a named set of capabilities assigned to a user.

## 5. PROCEDURE
### 5.1 Account request & provisioning
1. Access is requested for a named individual with a justified role.
2. The System Owner/Manager authorizes the request.
3. The Administrator creates the account (or it is created via self-registration). New accounts are
   created in **pending** status and cannot access the application until approved.
4. The Administrator/approver **approves** the account and assigns the role; the system records the
   approver and timestamp. Accounts requiring it are flagged to force a password change at first login.
5. Each account is unique to one individual. Shared/generic accounts are prohibited.

### 5.2 Role & capability changes
1. Changes to which capabilities a role holds are made only by an authorized Administrator
   (ManageRoles).
2. All role/capability changes are captured in the audit trail.

### 5.3 Modification & revocation
1. Access changes (role change, deactivation) are requested and authorized as in 5.1.
2. On personnel change/departure, the account is **deactivated** promptly (access revoked). GMP records
   are retained (no deletion of the user record).

### 5.4 Periodic access review
1. At the frequency defined in SOP-GQS-006 (Periodic Review), QA/Owner reviews the active user list and
   role assignments for appropriateness, evidenced by signature.
2. Discrepancies are corrected via 5.3 and recorded.

### 5.5 Credentials
1. Users keep credentials confidential. Password controls (e.g., forced first-login change) are enforced
   by the system; management of identification codes/passwords follows 21 CFR 11.300.

## 6. RECORDS
Account approvals (approver + timestamp, in-system), audit-trail entries for access/role changes, and
the periodic access-review record.

## 7. REFERENCES
21 CFR Part 11 (11.10(d),(g), 11.300); DS-GQS-001 §9; SOP-GQS-006; VP-GQS-001.

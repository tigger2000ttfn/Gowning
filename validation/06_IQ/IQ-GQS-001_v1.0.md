# INSTALLATION QUALIFICATION (IQ)
## MATC Gowning Qualification System (GQS)

| Field | Value |
|---|---|
| Document Number | IQ-GQS-001 *(placeholder; refit to Astellas numbering)* |
| Version | 1.0 |
| Status | DRAFT protocol — for QA approval prior to execution |
| System | MATC Gowning Qualification System (GQS) |
| GxP Impact | GxP — Direct |
| References | VP-GQS-001, DS-GQS-001, RA-GQS-001, RTM-GQS-001 |
| Baseline under test | git `main` @ tag/commit: ____________ (record at execution) |

### Approvals (protocol)
| Role | Name | Title | Signature | Date |
|---|---|---|---|---|
| Author | | | | |
| Quality Assurance | | | | |

### Execution & Review
| Role | Name | Signature | Date |
|---|---|---|---|
| Executed by | | | |
| Reviewed by (QA) | | | |

### Revision History
| Version | Date | Author | Description |
|---|---|---|---|
| 1.0 | 30-MAY-2026 | | Initial protocol |

---

## 1. PURPOSE & SCOPE
The IQ verifies that GQS is installed and configured correctly in the qualified production environment
and that the deployed software matches the approved baseline. It is executed against the production
deployment (Astellas E3 server + AWS RDS) after the VP, URS, FS, and DS are approved and before OQ.

## 2. REFERENCES
DS-GQS-001 (the as-built/as-configured target), VP-GQS-001, the deployment script (`deploy.sh`), and the
git repository (`tigger2000ttfn/Gowning`, branch `main`).

## 3. PREREQUISITES
- VP, URS, FS, DS approved; this protocol approved by QA.
- The intended release commit/tag is identified and recorded above.
- Access to the E3 server and the application URL is available to the executor.
- Platform/infrastructure qualification (OS, network, RDS) evidence is available (leveraged).

## 4. TEST ENVIRONMENT RECORD (complete at execution)
| Item | Recorded value |
|---|---|
| Server hostname / IP | |
| Operating system / version | |
| Web server + version | |
| PHP version | |
| PHP-FPM service(s) | |
| Database endpoint (RDS) / engine version | |
| Application path | /var/www/html/gowning/ |
| Application URL | https://…/gowning/ (port 8080) |
| Deployed git commit/tag | |
| Executor / date | |

## 5. IQ TEST CASES
Record Actual + Pass/Fail + Evidence (screenshot/console capture) for each. Suggested verification
command/observation given in *italics*.

| ID | Verification | Expected Result | Actual | P/F |
|---|---|---|---|---|
| IQ-001 | PHP version | PHP ≥ 8.3 installed. *`php -v`* | | |
| IQ-002 | Web server + headers module | Apache running; `mod_headers` enabled. *`aphttpd -M \| grep headers`* | | |
| IQ-003 | PHP dependencies | `composer install --no-dev` completes; lock consistent. *deploy step / `composer validate`* | | |
| IQ-004 | Software baseline | Deployed commit matches the approved tag/commit. *`git -C /var/www/html/gowning log --oneline -1`* | | |
| IQ-005 | Database connectivity (SSL) | App connects to RDS over SSL. *`php artisan db:show` / tinker `DB::select('select 1')`* | | |
| IQ-006 | Schema / migrations | All migrations report "Ran". *`php artisan migrate:status`* | | |
| IQ-007 | Storage symlink | `public/storage` → `storage/app/public` present. *`ls -l public/storage`* | | |
| IQ-008 | Front-end build | `public/build/manifest.json` exists (assets compiled). *`ls public/build`* | | |
| IQ-009 | Filament assets | Filament assets published. *deploy `filament:assets` + asset request 200* | | |
| IQ-010 | Cache/headers policy | `public/.htaccess` serves `/build/` immutable and HTML `no-cache, must-revalidate`. *response header check* | | |
| IQ-011 | Secure serving | App served over HTTPS at the subfolder URL on port 8080. *browser / `curl -I`* | | |
| IQ-012 | Scheduler | Cron entry runs `php artisan schedule:run` every minute. *`crontab -l \| grep schedule:run`* | | |
| IQ-013 | File ownership | Application files owned by `www-data`. *`ls -l` / `stat`* | | |
| IQ-014 | Vendored assets | `public/vendor/sortable` and `public/vendor/flatpickr` present (CDNs blocked). *`ls public/vendor`* | | |
| IQ-015 | Application loads | Login page renders; smoke request returns HTTP 200. *`curl -I https://…/gowning/admin/login`* | | |
| IQ-016 | Time zone | App time zone = America/New_York. *`php artisan tinker` `config('app.timezone')`* | | |
| IQ-017 | Queue/jobs infrastructure | `jobs`/`queued_emails` tables present for queued email. *`migrate:status` / table check* | | |
| IQ-018 | Configuration cache clear | `optimize:clear` + `view:clear` ran (no stale cached config/views). *deploy step* | | |
| IQ-019 | Backup configuration *(when implemented, RA R-20)* | Scheduled backup configured; restore-verification record exists. | | |

## 6. DEVIATIONS
| # | Test ID | Description | Resolution | Closed by / date |
|---|---|---|---|---|
| | | | | |

## 7. ACCEPTANCE
The IQ is accepted when all applicable test cases pass (or open deviations are resolved/risk-accepted by
QA) and the deployed baseline matches the approved commit/tag. IQ-019 is deferred until the backup
capability is implemented (RA-GQS-001 R-20) and must be satisfied before VSR approval.

## 8. TRACEABILITY
This IQ provides installation/environment evidence supporting URS-NFR-020 (scheduled automation
infrastructure), URS-NFR-030 (backup — pending), URS-SEC-060 (HTTPS/SSL), and URS-ER-040 (record
protection), and confirms the as-built configuration described in DS-GQS-001 (§4, §5, §13, §15, §16).

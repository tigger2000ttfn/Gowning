# MATC Gowning Qualification System (GQS)

Centralized tracking of the cleanroom gowning qualification cycle at MATC: qualification
status and due dates, cleanroom run scheduling, class-completion import, notifications, and
reporting. Built with 21 CFR Part 11 technical controls (per-user login, role-based access,
append-only audit trail, soft deletes, electronic-signature fields) and a validation package
maintained alongside the code.

## Stack

- PHP 8.3 + Laravel 13
- Filament 5 admin panel
- PostgreSQL on AWS RDS (`gowning_e3`)
- Apache, served at `https://matcastellas.com:8080/gowning/`

## Deploy

The app lives here; the server pulls and sets up the environment. See **DEPLOY.md**.

## Qualification rules

- Initial qualification: 3 successful cleanroom runs, then qualified for 12 months.
- Annual requalification: 1 successful run if on or before the due date.
- Lapsed (past due date): treated as initial, 3 runs required again.
- Gowning class completion (imported from the LMS) is the prerequisite for initial runs.

Failed runs do not count toward required passes and do not reset prior passes. Qualification
state is derived deterministically from the full run history (see
`app/Services/QualificationEngine.php`), so it can be rebuilt from source records.

## Layout

```
app/Models        domain models (audited, soft-deleted)
app/Enums         Role, QualificationStatus/Type, RunResult, ReservationStatus, RunSlotStatus
app/Services      QualificationEngine (the rules engine)
app/Observers     AuditObserver (append-only audit trail)
database/migrations  full schema
validation/       21 CFR Part 11 / GAMP 5 validation package (versioned with the code)
docs/             design documents
```

## Status

Foundation: schema, models, rules engine, audit trail, admin login. Next: Filament resources
(personnel, qualifications, runs, slots, reservations), CSV import, notifications, reporting.

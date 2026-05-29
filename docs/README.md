# Design documents

Design history for GQS is tracked in the chat-delivered design docs (v0.1 -> v0.3) and the
project-knowledge file. Key decisions:

- PHP 8.3 / Laravel 13 / Filament 5, PostgreSQL on RDS, served at /gowning/ on port 8080.
- Part 11 controls from the first commit (login, RBAC, append-only audit trail, soft deletes,
  e-signature fields). Validation package in /validation, GAMP 5, refit to Astellas templates.
- CSV import is a core feature; gowning class completions imported from the LMS (one-way).
- Rules: initial = 3 runs; annual = 1 run if on time; lapsed = 3 runs again.

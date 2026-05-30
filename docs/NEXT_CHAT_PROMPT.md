# GQS, Next-Chat Continuation Prompt

Paste this to start the next chat. It bootstraps the full context. Read the three docs in the repo
(`docs/GQS_MASTER_PLAN_v4.md`, `docs/GQS_OPEN_ISSUES.md`, `docs/DESIGN_AND_LESSONS_v1.md`) and the
project knowledge first.

---

```
Continue building the MATC Gowning Qualification System (GQS), the cleanroom gowning qualification
tracker for Astellas (MATC). We have built this across many sessions. Do not lose any prior knowledge.

READ FIRST (in the repo / project knowledge):
- docs/GQS_MASTER_PLAN_v4.md   (single source of truth: what it is, what is built, the roadmap, and
  Section 10 = the new QCM Incubation / Plate-Read Workspace)
- docs/GQS_OPEN_ISSUES.md      (honest list of unresolved / unconfirmed / server-needed items)
- docs/DESIGN_AND_LESSONS_v1.md (design language + the hard-won UI lessons; obey the Golden Rules)
- GOWNING_ACCESS_REFERENCE.md  (server / RDS / GitHub credentials; PAT for push)

STACK: PHP 8.3 + Laravel 13 + Filament v5; PostgreSQL on AWS RDS; Apache subfolder /gowning/ on port
8080 (HTTPS) at matcastellas.com. Repo: github.com/tigger2000ttfn/Gowning (branch: main only).

HOW WE WORK:
- Claude edits in the sandbox and PUSHES to main; the owner deploys on the E3 server with
  `cd /var/www/html/gowning && sudo bash deploy.sh`, then hard-refreshes (Ctrl-Shift-R).
- Claude CANNOT run PHP/composer/npm (packagist + npm blocked for composer). github.com and
  registry.npmjs.org (raw curl) and pypi are reachable. Vendor JS/CSS locally (CDNs are blocked on
  the Astellas network). A Filament v5 source clone for API checks may be in /tmp/fil.
- GOLDEN RULES: get the EXACT error before fixing a 500 (`grep -i production.ERROR storage/logs/
  laravel.log | tail -3`); verify Filament v5 namespaces/return-types against vendor source before
  pushing; balance-check braces/parens and @if/@endif/@foreach/@endforeach before every commit; NO
  em dashes in user-facing text; Title Case ALL UI labels/headers/buttons/columns; read the DOM
  before guessing CSS (ask for a console snippet if a layout fix loops more than twice); one feature
  per commit; deploy = `sudo bash deploy.sh`. Push: 
  `git push https://tigger2000ttfn:<PAT>@github.com/tigger2000ttfn/Gowning.git main`.

WHERE WE ARE: The full GMP pipeline, teams/resourcing, auto-scheduling, lifecycle automation, the
public portal, the cosmic login + dashboard, CSV import, reports, the Part 11 audit trail +
e-signatures, and the kanban/timeline tracking surfaces are all built and live. Recent session
closed: the page-header light-theme regression, two attendance status-leaks (Class Board drag and the
Sessions "Present" toggle, attendance is now draft until submitted with the trainer e-signature),
kanban + timeline fill-to-bottom + header padding + filter-dropdown clipping, and added 4 swimlane
groupings (Status Board: Job Title, Due Window; Class Board: Instructor, Session Date).

IMMEDIATE PRIORITIES (pick up here, confirm with me):
1. QA Team View (mirror QCM Team View) + assign instructors to class sessions from the team view.
2. QCM Incubation / Plate-Read Workspace (Master Plan Section 10): worklist model + weekly LIMS CSV
   import + bench kanban with team assignment + results-bridge back to qualification_runs. (Ask me
   for the LIMS export format first.)
3. Kanban bulk actions + saved board filters/views.
4. Unified scheduling calendar (run days + class sessions + due dates, drag-to-reschedule).
5. Work through docs/GQS_OPEN_ISSUES.md (server items, behavior confirmations, validation content).

Start by reading the three docs, then tell me which item to tackle and what (if anything) you need
from me or the server.
```

---

### Quick command reference

Sandbox clone + push (replace `<PAT>` with the token from GOWNING_ACCESS_REFERENCE.md):
```bash
git clone https://<PAT>@github.com/tigger2000ttfn/Gowning /home/claude/gowning
cd /home/claude/gowning && git checkout main
# ...edit...
git add -A && git commit -m "feat: ..." && git push https://tigger2000ttfn:<PAT>@github.com/tigger2000ttfn/Gowning.git main
```

Server deploy:
```bash
cd /var/www/html/gowning && sudo bash deploy.sh
```

500 triage / cache:
```bash
grep -i "production.ERROR" storage/logs/laravel.log | tail -3
# browser: Ctrl-Shift-R
```

# GQS Master Plan v5 - LIMS-Integrated Workflow

This version captures what the LabWare EM worklist export tells us about the real qualification
lifecycle, and where GQS automates vs. where LIMS is already the source of truth. It supersedes the
LIMS sections of v4.

## 1. The two systems and the join

GQS owns scheduling, the human workflow (class -> run -> review -> QA), and the regulated record.
LabWare LIMS owns the lab reality: the EM samples, incubation, and the QCM pass/fail evaluation.

The **worklist** (e.g. EM-13MAY2026-03) is the single join key. When a run is scheduled, the analyst
creates the worklist + samples in LIMS, then links that worklist to the GQS run. From then on the
worklist catalog (re-imported as data is entered) drives the GQS run forward.

Runs with no worklist (older, pre-LabWare) stay fully manual. Automation only ever applies to a run
once a worklist is linked - this is the safe fallback.

## 2. The two meta-samples per worklist

Each worklist carries two meta-samples, surfaced as two status columns in the export:

- **QC_EM_PERSONNEL_QUAL** -> SAMPLE_STATUS + the qualification fields (evaluation, EM area, dates,
  plates, controls, QUAL REFERENCE). This is the personnel-qual result.
- **QC_INC_META** -> INC_SAMPLE_STATUS + the incubation timeline (two incubations, bins, storage,
  INC REFERENCE). This is the incubation record.

Status vocabulary (confirmed): **A=Authorized, C=Complete, I=Incomplete, P=Pending, X=Cancelled.**

## 3. The authoritative gates (confirmed against the data)

| Signal | Meaning | Drives in GQS |
|---|---|---|
| INC_SAMPLE_STATUS = A | Incubation authorized = incubation done | Incubating -> Awaiting Results |
| SAMPLE_STATUS = A **and** INC_SAMPLE_STATUS = A **and** WORKLIST_ALL_FINAL = Yes **and** evaluation = Pass | QCM-result-ready | Records the Pass, releases to Results Released, run shows "LIMS: Pass - Authorized - Incubation Complete" and is QCM-reviewable. NEVER auto-sent to QA. |
| SAMPLE_STATUS = A **and** evaluation = Fail | Authorized fail = confirmed excursion | Surfaced for the NC path; QUAL REFERENCE NC number stored + linked to NC Catalog |
| SAMPLE_STATUS in (P, I) / evaluation blank / ALL_FINAL = No | Not final | Stays in Lab Review / Awaiting Results |
| SAMPLE_STATUS = X | Cancelled sample | Flagged, not treated as a result |

The QCM always reviews and builds the FORM-AST-36749 cover page before QA. The import never advances
into QA on its own.

## 4. What the data confirms (worked examples)

- Clean passes (RRODRIGUEZ, EVITO, ...): SAMPLE_STATUS=A, INC=A, ALL_FINAL=Yes, Pass -> QCM-ready.
- DINNAMORATI (EM-15APR2026-02): SAMPLE_STATUS=P, ALL_FINAL=No, Fail -> not final, fail pending.
- IBLISS (EM-16FEB2026-02): SAMPLE_STATUS=P, evaluation blank -> authorized-pending, no QCM call yet.
- Escamilla requal (EM-23APR2026-05): SAMPLE_STATUS=I, INC=I, all blank -> created, nothing done.
- IBLISS initial (EM-23MAR2026-02): SAMPLE_STATUS=C, Fail, INC=A -> the failed initial; cancelled/complete
  personnel sample, not a valid pass.
- Multi-run initial (MESCAMILLA, EM-13MAY2026-03): 3 dates, 3 CR grades, RUN 2 RESCHEDULED=Yes -> a
  full 3-run initial captured in one worklist row.
- Routine EM (JGEORGE, AKONTOR, ...): QUALIFICATION TYPE blank + "Routine EM" in EM AREA -> NOT a
  gowning qual; filtered out of qual processing. (Their INC REFERENCE is the routine-EM NC, ignored.)

## 5. Date handling

QUAL DATE 1/2/3 now arrive normalized from the query (CONVERT to ISO via style 105), but older exports
mixed M/D/Y and D-M-Y. Parsing stays defensive (try ISO, then D-M-Y, then M/D/Y).

## 6. Where GQS automates / fills blanks

Built (catalog + sync, shipped):
- Worklist Catalog import (idempotent on worklist), with the SQL query stored on its own tab.
- WorklistSync: incubation-complete advance; QCM-ready result + release; authorized-fail + QUAL
  REFERENCE NC stored and linked to the NC Catalog; person-match warning.
- LIMS state on the run: evaluation, sample/inc status, all-final, qcm-ready, nc number/url, synced-at.

Next (to wire the loop):
1. **Worklist picker on the run** - replace the free-text worklist entry on the Run Scheduler roster
   with a pick-from-catalog dropdown; on link, run the person-match confirmation (warn if the
   worklist's PERSONNEL/description does not match the run's person).
2. **Unmatched / needs-review bucket** - LIMS rows whose person cannot be confidently matched
   (lims_username first, then last-name + first-initial) are listed for manual lookup-by-name and
   linking; never silently created or mis-linked.
3. **Cover-page (FORM-AST-36749) pre-fill** - populate the cover page from the linked worklist:
   person, qual type, EM area, CR grade(s), dates, plate/control IDs, evaluation. For a 3-run initial,
   CR GRADE 1/2/3 map to the three runs.
4. **Incubation board enrichment** - show the real LIMS incubation timeline (start/end/due, incubator,
   bin) on a linked run instead of manual tracking; INC=A is the "ready to read" signal.
5. **Display on cards/lists** - a "LIMS: Pass - Authorized" badge on Lab Review / QA Review cards for
   linked runs, with the worklist detail in the modal.

## 7. Open questions / parked

- Auto-link confirmed: when a fail has a QUAL REFERENCE, store it AND link to the NC Catalog (done in sync).
- 3-run initial: confirm CR GRADE 1/2/3 each populate a separate run on the cover page (likely yes).
- Whether INC=A alone (without SAMPLE auth) should ever move a run past Awaiting Results (currently no -
  it only advances Incubating -> Awaiting Results; the Pass gate is separate).

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LimsWorklist extends Model
{
    use Auditable;

    protected $fillable = [
        'worklist', 'is_legacy', 'non_reportable', 'non_reportable_reason', 'worklist_description', 'sample_number', 'sample_status',
        'samples_on_worklist', 'non_final_count', 'worklist_all_final',
        'qualification_type', 'personnel', 'initial_run_no', 'annual_requal', 'additional_requal',
        'evaluation', 'em_area', 'cr_grade_1', 'cr_grade_2', 'cr_grade_3', 'grade_a_ops', 'grade_b_ops',
        'qual_date_1', 'qual_date_2', 'qual_date_3', 'run2_rescheduled', 'run3_rescheduled',
        'tsa_contact_plate', 'tsa_contact_plate_1', 'tsa_contact_plate_2', 'tsa_contact_plate_3',
        'tsa_control_1', 'tsa_control_2', 'tsa_control_3', 'qual_reference', 'inc_reference',
        'inc_sample_number', 'inc_sample_status',
        'inc1_incubator', 'inc1_bin', 'inc1_start', 'inc1_end', 'inc1_due', 'inc1_total',
        'inc2_incubator', 'inc2_start', 'inc2_end', 'inc2_due', 'inc2_total',
        'storage3_location', 'storage3_start', 'catalog_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'worklist_all_final' => 'boolean',
            'is_legacy' => 'boolean',
            'non_reportable' => 'boolean',
            'samples_on_worklist' => 'integer',
            'non_final_count' => 'integer',
            'catalog_synced_at' => 'datetime',
        ];
    }

    // LIMS status codes.
    public const STATUS_AUTHORIZED = 'A';
    public const STATUS_COMPLETE = 'C';
    public const STATUS_INCOMPLETE = 'I';
    public const STATUS_PENDING = 'P';
    public const STATUS_CANCELLED = 'X';

    public static function statusLabel(?string $s): string
    {
        return match (strtoupper(trim((string) $s))) {
            'A' => 'Authorized',
            'C' => 'Complete',
            'I' => 'Incomplete',
            'P' => 'Pending',
            'X' => 'Cancelled',
            default => $s ? (string) $s : '—',
        };
    }

    public static function findByWorklist(?string $wl): ?self
    {
        $wl = trim((string) $wl);
        if ($wl === '') return null;
        return static::where('worklist', $wl)->first();
    }

    /** Which personnel record does this worklist's PERSONNEL value belong to? (LIMS username, else name.) */
    public function matchPersonnel(): ?\App\Models\Personnel
    {
        $wlPersonnel = strtoupper(trim((string) $this->personnel));
        if ($wlPersonnel === '') return null;

        // 1) Exact LIMS username match (e.g. RRODRIGUEZ).
        $p = \App\Models\Personnel::whereRaw('UPPER(lims_username) = ?', [$wlPersonnel])->first();
        if ($p) return $p;

        // 2) Pattern: first-initial + last name contained in the PERSONNEL token.
        foreach (\App\Models\Personnel::whereNotNull('last_name')->get() as $cand) {
            $last = strtoupper(trim((string) $cand->last_name));
            $first = strtoupper(trim((string) $cand->first_name));
            if ($last === '') continue;
            if (str_contains($wlPersonnel, $last) && ($first === '' || str_starts_with($wlPersonnel, $first[0]))) {
                return $cand;
            }
        }
        return null;
    }

    /**
     * Worklists in LIMS that name this person (by username or name pattern) and represent a performed run,
     * optionally on/after a date. Used to reconcile attendance the analyst may have forgotten to mark.
     */
    public static function forPersonnel(\App\Models\Personnel $p, ?string $onOrAfter = null)
    {
        $login = strtoupper(trim((string) $p->lims_username));
        $last = strtoupper(trim((string) $p->last_name));
        if ($login === '' && $last === '') return collect();
        // Note: inc1_start is a free-text LIMS datetime string that can be empty (""), so we do NOT filter
        // it in SQL (casting "" to date errors in Postgres). The authorized/final gate at the call site is
        // the real safety; the most recent matching worklist (orderByDesc id) is returned.
        return static::query()
            ->where(function ($q) use ($login, $last) {
                if ($login !== '') $q->orWhereRaw('UPPER(personnel) = ?', [$login]);
                if ($last !== '') $q->orWhereRaw('UPPER(personnel) LIKE ?', ['%' . $last . '%']);
            })
            ->orderByDesc('id')
            ->get();
    }

    public function isPass(): bool
    {
        return strcasecmp(trim((string) $this->evaluation), 'pass') === 0;
    }

    public function isFail(): bool
    {
        return strcasecmp(trim((string) $this->evaluation), 'fail') === 0;
    }

    public function isAuthorized(): bool
    {
        return strtoupper(trim((string) $this->sample_status)) === self::STATUS_AUTHORIZED;
    }

    public function incubationAuthorized(): bool
    {
        return strtoupper(trim((string) $this->inc_sample_status)) === self::STATUS_AUTHORIZED;
    }

    /** Parse a LIMS datetime string (M/D/Y H:i and variants) defensively. */
    public function parseLimsDateTime(?string $v): ?\Illuminate\Support\Carbon
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        foreach (['m/d/Y H:i', 'm/d/Y G:i', 'n/j/Y H:i', 'n/j/Y G:i', 'm/d/Y', 'Y-m-d H:i', 'Y-m-d'] as $fmt) {
            try { $c = \Illuminate\Support\Carbon::createFromFormat($fmt, $v); if ($c) return $c; } catch (\Throwable $e) {}
        }
        try { return \Illuminate\Support\Carbon::parse($v); } catch (\Throwable $e) { return null; }
    }

    /** Plates are in incubation once the first incubation has a start timestamp. */
    public function incubationStarted(): bool
    {
        return $this->parseLimsDateTime($this->inc1_start) !== null;
    }

    /** Incubation is complete (ready to read) once the SECOND incubation has an end timestamp. */
    public function incubationComplete(): bool
    {
        return $this->parseLimsDateTime($this->inc2_end) !== null;
    }

    /** When incubation is due to finish (2nd due, else 2nd end). */
    public function incubationDue(): ?\Illuminate\Support\Carbon
    {
        return $this->parseLimsDateTime($this->inc2_due) ?? $this->parseLimsDateTime($this->inc2_end);
    }

    /**
     * The authoritative QCM-result-ready gate: worklist all final, the EM personnel-qual sample
     * authorized, the incubation sample authorized, and a Pass evaluation. (Does NOT auto-send to QA;
     * it makes the run reviewable by the QCM, who confirms and builds the cover page.)
     */
    public function isQcmReady(): bool
    {
        return (bool) $this->worklist_all_final
            && $this->isAuthorized()
            && $this->incubationAuthorized()
            && $this->isPass();
    }

    /** An authorized fail = a confirmed excursion. */
    public function isAuthorizedFail(): bool
    {
        return $this->isAuthorized() && $this->isFail();
    }

    /** Routine EM monitoring (not a gowning qual) - skip from qual processing. */
    public function isRoutineEm(): bool
    {
        // A row is "routine EM" only when it has NO explicit qualification type AND the EM area says
        // routine. Once a type is assigned (e.g. hand-fixed on a legacy row), it is a real qual.
        if (trim((string) $this->qualification_type) !== '') return false;
        return stripos((string) $this->em_area, 'routine em') !== false;
    }

    /**
     * The effective qualification type. Uses QUALIFICATION TYPE when present; otherwise infers from the
     * worklist description ("Initial" vs "Re-qual/Annual" vs "Additional"). Returns null for routine EM
     * or when nothing can be inferred.
     */
    public function effectiveType(): ?string
    {
        if ($this->isRoutineEm()) return null;
        $t = strtolower(trim((string) $this->qualification_type));
        if ($t !== '') {
            if (str_contains($t, 'initial')) return 'Initial Gowning Qualification';
            if (str_contains($t, 'additional')) return 'Additional Requalification';
            if (str_contains($t, 'annual') || str_contains($t, 'requal')) return 'Annual Requalification';
            return $this->qualification_type;
        }
        // infer from the description
        $d = strtolower(trim((string) $this->worklist_description));
        if ($d === '') return null;
        if (str_contains($d, 'additional')) return 'Additional Requalification';
        if (str_contains($d, 're-qual') || str_contains($d, 'requal') || str_contains($d, 're qual') || str_contains($d, 'annual')) return 'Annual Requalification';
        if (str_contains($d, 'initial') || str_contains($d, 'qual')) return 'Initial Gowning Qualification';
        return null;
    }

    /** Whether the type was inferred from the description rather than the explicit column. */
    public function typeWasInferred(): bool
    {
        return trim((string) $this->qualification_type) === '' && $this->effectiveType() !== null;
    }

    /** Defensive date parse: try ISO (Y-M-D), then D-M-Y, then M/D/Y. Returns a Carbon date or null. */
    public static function parseLimsDate(?string $raw): ?\Illuminate\Support\Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'n/j/Y', 'j-n-Y'] as $fmt) {
            try {
                $d = \Illuminate\Support\Carbon::createFromFormat($fmt, $raw);
                if ($d !== false) return $d->startOfDay();
            } catch (\Throwable $e) {}
        }
        try { return \Illuminate\Support\Carbon::parse($raw)->startOfDay(); } catch (\Throwable $e) { return null; }
    }

    protected function yes(?string $v): bool
    {
        return in_array(strtolower(trim((string) $v)), ['yes', 'y', 'true', '1'], true);
    }

    /**
     * Derive the effective run dates from the date columns + reschedule flags. The DATES are authoritative:
     * a reschedule flag of "yes" only records that the analyst EXPECTED a separate day. If the person ended
     * up doing the run the same day after all, the actual Qual Date shows it (same as the prior run, or - on
     * a finalized worklist - simply never entered as a separate date). That is correct, not an error, and is
     * not flagged. We only flag needs_review when a reschedule was expected, no date was entered, AND the
     * worklist is not yet final (so it is genuinely pending/ambiguous rather than resolved as same-day).
     *
     * - Run 1 = Qual Date 1.
     * - Run 2 = Qual Date 2 when present (may equal Run 1 = same day despite the flag); else same day as
     *   Run 1 (flag=no, or finalized with no separate date); else (reschedule expected, blank, not final)
     *   Run 1 + needs_review.
     * - Run 3 = same rule against Run 2.
     *
     * @return array{run1: ?\Illuminate\Support\Carbon, run2: ?\Illuminate\Support\Carbon, run3: ?\Illuminate\Support\Carbon, needs_review: bool}
     */
    public function effectiveRunDates(): array
    {
        $d1 = self::parseLimsDate($this->qual_date_1);
        $d2 = self::parseLimsDate($this->qual_date_2);
        $d3 = self::parseLimsDate($this->qual_date_3);
        $r2 = $this->yes($this->run2_rescheduled);
        $r3 = $this->yes($this->run3_rescheduled);
        $final = (bool) $this->worklist_all_final;
        $needsReview = false;

        $run1 = $d1;

        // Run 2: the date wins. Same-day-despite-a-yes-flag is a legitimate, common case (they planned to
        // reschedule but did it the same day), so it is NOT flagged.
        if ($d2) {
            $run2 = $d2;                        // explicit date (may equal run 1 = same day)
        } elseif ($r2 && ! $final) {
            $run2 = $d1; $needsReview = true;   // reschedule expected, no date yet, not final -> pending
        } else {
            $run2 = $d1;                        // same day as run 1 (flag=no, or finalized same-day)
        }

        if ($d3) {
            $run3 = $d3;
        } elseif ($r3 && ! $final) {
            $run3 = $run2; $needsReview = true;
        } else {
            $run3 = $run2;                      // same day as run 2
        }

        return ['run1' => $run1, 'run2' => $run2, 'run3' => $run3, 'needs_review' => $needsReview];
    }
}

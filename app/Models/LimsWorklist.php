<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LimsWorklist extends Model
{
    use Auditable;

    protected $fillable = [
        'worklist', 'is_legacy', 'worklist_description', 'sample_number', 'sample_status',
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
     * Derive the effective run dates from the date columns + reschedule flags, since analysts may copy
     * the same date into all three and reschedules are the real signal.
     *
     * - Run 1 = Qual Date 1.
     * - Run 2 = Qual Date 2 if RUN 2 RESCHEDULED and Date 2 present; if rescheduled but blank -> Date 1
     *   (and flagged needs_review). Otherwise -> Date 1.
     * - Run 3 = Qual Date 3 if RUN 3 RESCHEDULED and Date 3 present; if rescheduled but blank -> run 2's
     *   date (and flagged needs_review). Otherwise -> run 2's date.
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
        $needsReview = false;

        $run1 = $d1;

        if ($r2) {
            if ($d2) { $run2 = $d2; }
            else { $run2 = $d1; $needsReview = true; } // rescheduled but missing the date (possibly mid-stream)
        } else {
            $run2 = $d1; // same day as run 1
        }

        if ($r3) {
            if ($d3) { $run3 = $d3; }
            else { $run3 = $run2; $needsReview = true; }
        } else {
            $run3 = $run2; // same day as run 2
        }

        return ['run1' => $run1, 'run2' => $run2, 'run3' => $run3, 'needs_review' => $needsReview];
    }
}

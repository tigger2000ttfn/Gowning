<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LimsWorklist extends Model
{
    use Auditable;

    protected $fillable = [
        'worklist', 'worklist_description', 'sample_number', 'sample_status',
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
}

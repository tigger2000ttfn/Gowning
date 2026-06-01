<?php

namespace App\Models;

use App\Enums\QualificationType;
use App\Enums\RunResult;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class QualificationRun extends Model implements HasMedia
{
    protected static function booted(): void
    {
        // Auto-link the Veeva document URL from the catalog whenever a Veeva number is set/changed and no
        // explicit URL was provided. Keeps run records hyperlinked without anyone pasting the link.
        // Each link type can be turned off in Settings (auto-link is on by default).
        static::saving(function ($run) {
            if ($run->veeva_doc_number && (empty($run->veeva_url) || $run->isDirty('veeva_doc_number'))
                && \App\Models\Setting::get('autolink_veeva', true)) {
                $url = \App\Models\VeevaDocument::urlForNumber($run->veeva_doc_number);
                if ($url) $run->veeva_url = $url;
            }
            // Same for an NC/TrackWise number stored on the run (failed runs carry lims_nc_number).
            if ($run->lims_nc_number && (empty($run->lims_nc_url) || $run->isDirty('lims_nc_number'))
                && \App\Models\Setting::get('autolink_nc', true)) {
                $ncUrl = \App\Models\NcDocument::urlForNumber($run->lims_nc_number);
                if ($ncUrl) $run->lims_nc_url = $ncUrl;
            }
        });

        static::created(function ($run) {
            if (! $run->run_uid) {
                $run->run_uid = 'QRUN-' . str_pad((string) $run->id, 5, '0', STR_PAD_LEFT);
                $run->saveQuietly();
            }
            if (($run->result->value ?? $run->result) === 'fail') {
                $name = $run->personnel?->full_name ?? 'A trainee';
                \App\Services\Notifier::toCapability(
                    \App\Enums\Capability::QaReview,
                    'Failed Qualification Run',
                    "{$name} had a failed run on {$run->run_date?->gmp()} — QA determination needed.",
                    \App\Filament\Admin\Resources\QualificationResource::getUrl(),
                    'danger',
                );
            }
        });
    }

    use Auditable, SoftDeletes, GqsActivityLog, InteractsWithMedia;

    protected $fillable = [
        'run_uid',
        'personnel_id', 'qualification_id', 'run_slot_id', 'reservation_id',
        'parent_run_id', 'qa_determination', 'qa_determined_at', 'is_complete',
        'run_date', 'result', 'cycle_type', 'notes', 'recorded_by', 'is_seed',
        'signed_by', 'signed_at', 'signature_meaning',
        'lims_worklist_id', 'lims_evaluation', 'lims_sample_status', 'lims_inc_status', 'lims_all_final', 'lims_qcm_ready', 'lims_synced_at', 'lims_nc_number', 'lims_nc_url', 'lims_inc1_incubator', 'lims_inc1_start', 'lims_inc1_end', 'lims_inc2_incubator', 'lims_inc2_start', 'lims_inc2_end', 'lims_inc_due', 'veeva_doc_number', 'veeva_url', 'results_entered_at', 'incubation_started_at', 'results_released_at', 'qa_signed_at', 'qa_signed_by', 'qa_notes', 'qcm_signed_at', 'qcm_signed_by',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'result' => RunResult::class,
            'is_seed' => 'boolean',
            'is_complete' => 'boolean',
            'qa_determined_at' => 'datetime',
            'cycle_type' => QualificationType::class,
            'signed_at' => 'datetime',
            'incubation_started_at' => 'datetime',
            'results_released_at' => 'datetime',
            'results_entered_at' => 'datetime',
            'qa_signed_at' => 'datetime',
            'qcm_signed_at' => 'datetime',
            'lims_all_final' => 'boolean',
            'lims_qcm_ready' => 'boolean',
            'lims_synced_at' => 'datetime',
        ];
    }

    /** The run this one descends from (e.g. a requal run spawned after a failed run's QA determination). */
    public function parentRun(): BelongsTo { return $this->belongsTo(QualificationRun::class, 'parent_run_id'); }
    public function childRuns(): HasMany { return $this->hasMany(QualificationRun::class, 'parent_run_id'); }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }

    public function runSlot(): BelongsTo
    {
        return $this->belongsTo(RunSlot::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function qaSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_signed_by');
    }

    public function qcmSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qcm_signed_by');
    }

    public function isPass(): bool
    {
        return $this->result === RunResult::Pass;
    }
}

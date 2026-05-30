<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class NonConformance extends Model implements HasMedia
{
    use Auditable, SoftDeletes, GqsActivityLog, InteractsWithMedia;

    protected $fillable = [
        'nc_number',
        'qualification_id', 'qualification_run_id', 'personnel_id',
        'trackwise_id', 'nc_type', 'organism', 'site', 'cfu_count',
        'over_threshold', 'status', 'summary', 'observed_date',
        'assigned_to', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (NonConformance $nc) {
            if (empty($nc->nc_number)) {
                $nc->nc_number = self::nextNumber();
            }
        });
    }

    /** Generate the next NC number, e.g. NC-2026-0007. */
    public static function nextNumber(): string
    {
        $year = now()->format('Y');
        $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;
        // ensure uniqueness if backfilled/out-of-band numbers exist
        do {
            $candidate = 'NC-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            $count++;
        } while (static::withTrashed()->where('nc_number', $candidate)->exists());
        return $candidate;
    }

    protected function casts(): array
    {
        return [
            'over_threshold' => 'boolean',
            'cfu_count' => 'integer',
            'observed_date' => 'date',
        ];
    }

    public function personnel(): BelongsTo { return $this->belongsTo(Personnel::class); }
    public function qualification(): BelongsTo { return $this->belongsTo(Qualification::class); }
    public function run(): BelongsTo { return $this->belongsTo(QualificationRun::class, 'qualification_run_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}

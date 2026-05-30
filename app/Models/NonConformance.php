<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonConformance extends Model
{
    use Auditable, SoftDeletes, GqsActivityLog;

    protected $fillable = [
        'qualification_id', 'qualification_run_id', 'personnel_id',
        'trackwise_id', 'nc_type', 'organism', 'site', 'cfu_count',
        'over_threshold', 'status', 'summary', 'observed_date',
        'assigned_to', 'created_by',
    ];

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

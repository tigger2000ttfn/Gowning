<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunSample extends Model
{
    use Auditable, SoftDeletes, GqsActivityLog;

    protected $fillable = [
        'qualification_run_id', 'reservation_id', 'personnel_id',
        'site', 'result', 'plate_id', 'cfu_count', 'read_date', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['read_date' => 'date', 'cfu_count' => 'integer'];
    }

    public function personnel(): BelongsTo { return $this->belongsTo(Personnel::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function run(): BelongsTo { return $this->belongsTo(QualificationRun::class, 'qualification_run_id'); }
}

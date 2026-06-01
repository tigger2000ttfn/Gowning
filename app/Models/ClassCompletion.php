<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCompletion extends Model
{
    use Auditable;

    protected $fillable = [
        'personnel_id', 'employee_id', 'class_name', 'lms_number',
        'completion_date', 'source', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['completion_date' => 'date'];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}

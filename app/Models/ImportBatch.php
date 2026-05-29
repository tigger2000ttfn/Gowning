<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'type', 'filename', 'imported_by', 'total_rows',
        'imported_rows', 'skipped_rows', 'rejected_rows', 'report',
    ];

    protected function casts(): array
    {
        return ['report' => 'array'];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function classCompletions(): HasMany
    {
        return $this->hasMany(ClassCompletion::class);
    }
}

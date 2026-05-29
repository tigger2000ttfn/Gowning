<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassEnrollment extends Model
{
    use Auditable;

    protected $fillable = [
        'class_session_id', 'personnel_id', 'name', 'email',
        'employee_id', 'status', 'signed_up_at',
    ];

    protected function casts(): array
    {
        return ['signed_up_at' => 'datetime'];
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}

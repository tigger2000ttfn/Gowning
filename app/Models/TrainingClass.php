<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingClass extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description', 'is_gowning_prerequisite', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_gowning_prerequisite' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}

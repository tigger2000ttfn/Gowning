<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomationRule extends Model
{
    use Auditable, SoftDeletes, GqsActivityLog;

    protected $fillable = [
        'name', 'trigger', 'trigger_stage', 'action', 'action_config',
        'is_enabled', 'run_count', 'last_fired_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'action_config' => 'array',
            'is_enabled' => 'boolean',
            'last_fired_at' => 'datetime',
        ];
    }
}

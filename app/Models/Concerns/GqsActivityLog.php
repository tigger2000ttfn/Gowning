<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Part 11 audit logging: computer-generated, attributable, time-stamped,
 * preserves old + new values, only logs real changes.
 * Models declare protected array $auditLabel for a human description.
 */
trait GqsActivityLog
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename($this));
    }
}

<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records every create/update/delete/restore on audited models into audit_logs.
 * Entries are append-only: they are never updated or deleted by the application.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        $this->write('created', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if (empty($changes)) {
            return;
        }
        $old = array_intersect_key($model->getOriginal(), $changes);
        $this->write('updated', $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        // Soft delete is the normal path; hard deletes should not occur.
        $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
            ? 'force_deleted'
            : 'deleted';
        $this->write($event, $model, $model->getOriginal(), null);
    }

    public function restored(Model $model): void
    {
        $this->write('restored', $model, null, $model->getAttributes());
    }

    protected function write(string $event, Model $model, ?array $old, ?array $new): void
    {
        $request = request();

        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $this->scrub($old),
            'new_values' => $this->scrub($new),
            'reason' => $model->auditReason ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /** Never record secrets in the audit trail. */
    protected function scrub(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        foreach (['password', 'remember_token'] as $secret) {
            unset($values[$secret]);
        }
        return $values;
    }
}

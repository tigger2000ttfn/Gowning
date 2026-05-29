<?php

namespace App\Models\Concerns;

/**
 * Marker trait for models whose changes must be captured in the audit trail.
 * The AuditObserver is registered for these models in AppServiceProvider.
 */
trait Auditable
{
    /**
     * Optional reason captured with the next change (Part 11 "reason for change").
     * Set transiently before saving: $model->auditReason = '...'.
     */
    public ?string $auditReason = null;
}

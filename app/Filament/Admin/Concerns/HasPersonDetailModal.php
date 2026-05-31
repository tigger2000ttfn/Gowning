<?php

namespace App\Filament\Admin\Concerns;

/**
 * Per-person detail modal (class context): stage/status/type/due/class-on-file/class-completion-date +
 * recent class enrollments, with an Open Record link. Used by Class Scheduler and Active Bookings so the
 * click-a-person detail behaves identically.
 */
trait HasPersonDetailModal
{
    public ?array $personDetail = null;
    public function closePersonDetail(): void { $this->personDetail = null; }

    public function showPersonDetail(?int $personnelId): void
    {
        if (! $personnelId) { $this->personDetail = null; return; }
        $p = \App\Models\Personnel::find($personnelId);
        if (! $p) { $this->personDetail = null; return; }
        $q = \App\Models\Qualification::currentFor($p->id);

        $enrollments = \App\Models\ClassEnrollment::with('classSession.trainingClass')
            ->where('personnel_id', $p->id)->latest('id')->limit(6)->get()
            ->map(fn ($e) => [
                'class' => $e->classSession?->trainingClass?->name ?? 'Class',
                'date' => $e->classSession?->session_date?->gmp(),
                'status' => ucwords(str_replace('_', ' ', (string) ($e->status instanceof \BackedEnum ? $e->status->value : $e->status))),
            ])->all();

        $classCompletion = \App\Models\ClassCompletion::where('personnel_id', $p->id)
            ->latest('completion_date')->first();
        $classDate = $q?->class_on_file_date?->gmp() ?? $classCompletion?->completion_date?->gmp();

        $this->personDetail = [
            'name' => $p->full_name,
            'employee_id' => $p->employee_id,
            'department' => $p->department,
            'job_title' => $p->job_title,
            'email' => $p->email,
            'stage' => $q?->workflow_stage?->label(),
            'status' => $q ? ucwords(str_replace('_', ' ', (string) ($q->status instanceof \BackedEnum ? $q->status->value : $q->status))) : null,
            'type' => $q ? $q->sessionLabel() : null,
            'due' => $q?->due_date?->gmp(),
            'class_on_file' => (bool) ($q?->class_on_file),
            'class_date' => $classDate,
            'enrollments' => $enrollments,
            'view_url' => $q
                ? \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $q->id])
                : \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $p->id]),
        ];
    }
}

<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\Qualification;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class QualificationTimeline extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ViewReports) || $u->hasCapability(Capability::ManageScheduling)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Timeline';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 9;
    protected static ?string $title = 'Qualification Timeline';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qualification-timeline';

    public string $search = '';
    public string $deptFilter = '';
    public string $statusFilter = '';
    public string $viewMode = 'Month';   // Frappe Gantt view_mode: Day/Week/Month/Quarter Day...

    public function departmentOptions(): array
    {
        return \App\Models\Department::where('is_active', true)->orderBy('name')->pluck('name', 'name')->all();
    }

    /**
     * Tasks shaped for Frappe Gantt: each person's path from first activity to due date,
     * with progress reflecting runs completed and a status-based color class.
     */
    public function tasks(): array
    {
        $quals = Qualification::with(['personnel', 'runs'])
            ->whereHas('personnel')
            ->when($this->search !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('first_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('last_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('employee_id', 'ilike', '%' . $this->search . '%')))
            ->when($this->deptFilter !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('department', $this->deptFilter)))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->limit(300)->get();

        $tasks = [];
        foreach ($quals as $q) {
            $toCI = fn ($d) => $d ? CarbonImmutable::parse($d) : null;
            $start = $toCI($q->class_on_file_date)
                ?? $toCI($q->runs->min('run_date'))
                ?? $toCI($q->stage_changed_at)
                ?? CarbonImmutable::now()->subDays(7);
            $end = $toCI($q->due_date) ?? $toCI($q->qualified_date) ?? $start->addMonths(3);
            if ($end->lte($start)) { $end = $start->addDays(7); }

            $req = max(1, (int) $q->runs_required);
            $statusVal = $q->status instanceof \BackedEnum ? $q->status->value : $q->status;
            $progress = min(100, (int) round(((int) $q->runs_completed / $req) * 100));
            if ($statusVal === 'qualified') { $progress = 100; }

            $class = match ($statusVal) {
                'qualified' => 'gantt-qualified',
                'lapsed' => 'gantt-lapsed',
                default => 'gantt-active',
            };

            $stageVal = $q->workflow_stage instanceof \BackedEnum ? $q->workflow_stage->value : $q->workflow_stage;
            $stageLabel = \App\Models\WorkflowStatus::labelFor('run', (string) $stageVal, $q->workflow_stage?->label() ?? (string) $statusVal);

            $tasks[] = [
                'id' => 'q' . $q->id,
                'name' => ($q->personnel?->full_name ?? 'Unknown') . '  ·  ' . $stageLabel
                    . '  ·  ' . (int) $q->runs_completed . '/' . $req . ' runs',
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'progress' => $progress,
                'custom_class' => $class,
            ];
        }
        return $tasks;
    }
}

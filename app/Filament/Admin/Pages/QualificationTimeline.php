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
    protected static ?string $slug = 'timeline';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 9;
    protected static ?string $title = 'Timeline';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qualification-timeline';

    public string $search = '';
    public string $deptFilter = '';
    public string $statusFilter = '';
    // view: 'due_window' (30d before due = time to complete next round) | 'full' (whole cycle path)
    public string $view_mode = 'due_window';
    public int $windowDays = 30;
    public ?int $detailId = null;

    public function departmentOptions(): array
    {
        return \App\Models\Department::where('is_active', true)->orderBy('name')->pluck('name', 'name')->all();
    }

    /**
     * Tasks shaped for Frappe Gantt: each person's path from first activity to due date,
     * with progress reflecting runs completed and a status-based color class.
     */
    /**
     * Rows for the custom timeline. Each row = one person, with a bar positioned on a
     * shared date axis. Two views:
     *  - due_window: the N days BEFORE the due date = the window they have to complete
     *    their next round (the scheduling view). Bar = [due - N days, due].
     *  - full: the whole current cycle, first activity to due date.
     */
    public function rows(): array
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
            ->limit(400)->get();

        $toCI = fn ($d) => $d ? CarbonImmutable::parse($d) : null;
        $rows = [];

        foreach ($quals as $q) {
            $statusVal = $q->status instanceof \BackedEnum ? $q->status->value : $q->status;
            $req = max(1, (int) $q->runs_required);
            $done = (int) $q->runs_completed;
            $progress = $statusVal === 'qualified' ? 100 : min(100, (int) round(($done / $req) * 100));
            $stageVal = $q->workflow_stage instanceof \BackedEnum ? $q->workflow_stage->value : $q->workflow_stage;
            $stageLabel = \App\Models\WorkflowStatus::labelFor('run', (string) $stageVal, $q->workflow_stage?->label() ?? (string) $statusVal);

            $due = $toCI($q->due_date);

            if ($this->view_mode === 'due_window') {
                // Only people with a due date have a meaningful window.
                if (! $due) continue;
                $start = $due->subDays($this->windowDays);
                $end = $due;
            } else {
                $start = $toCI($q->class_on_file_date)
                    ?? $toCI($q->runs->min('run_date'))
                    ?? $toCI($q->stage_changed_at)
                    ?? CarbonImmutable::now()->subDays(7);
                $end = $due ?? $toCI($q->qualified_date) ?? $start->addMonths(3);
                if ($end->lte($start)) $end = $start->addDays(7);
            }

            $class = match ($statusVal) {
                'qualified' => 'qualified',
                'lapsed' => 'lapsed',
                default => 'active',
            };
            // Overdue flag: due date in the past and not qualified
            $overdue = $due && $due->isPast() && $statusVal !== 'qualified';

            $rows[] = [
                'id' => $q->id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
                'stage' => $stageLabel,
                'status' => $statusVal,
                'runs' => $done . '/' . $req,
                'progress' => $progress,
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'due' => $due?->gmp(),
                'class' => $overdue ? 'overdue' : $class,
            ];
        }

        // Sort: due_window by soonest due first; full view by name.
        if ($this->view_mode === 'due_window') {
            usort($rows, fn ($a, $b) => strcmp($a['end'], $b['end']));
        } else {
            usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));
        }

        return $rows;
    }

    /** The date axis (min start .. max end) across all visible rows, padded a little. */
    public function axis(): array
    {
        $rows = $this->rows();
        if (empty($rows)) {
            $today = CarbonImmutable::now();
            return ['start' => $today->subDays(15)->format('Y-m-d'), 'end' => $today->addDays(15)->format('Y-m-d'), 'days' => 30, 'today' => $today->format('Y-m-d')];
        }
        $min = min(array_map(fn ($r) => $r['start'], $rows));
        $max = max(array_map(fn ($r) => $r['end'], $rows));
        $start = CarbonImmutable::parse($min)->subDays(3);
        $end = CarbonImmutable::parse($max)->addDays(3);
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'days' => max(1, $start->diffInDays($end)),
            'today' => CarbonImmutable::now()->format('Y-m-d'),
        ];
    }

    public function showDetail(int $id): void { $this->detailId = $id; }
    public function closeDetail(): void { $this->detailId = null; }

    public function detail(): ?array
    {
        if (! $this->detailId) return null;
        $q = Qualification::with('personnel')->find($this->detailId);
        if (! $q) return null;
        $statusVal = $q->status instanceof \BackedEnum ? $q->status->value : $q->status;
        return [
            'id' => $q->id,
            'name' => $q->personnel?->full_name ?? 'Unknown',
            'employee_id' => $q->personnel?->employee_id,
            'department' => $q->personnel?->department,
            'stage' => \App\Models\WorkflowStatus::labelFor('run', (string) ($q->workflow_stage?->value), $q->workflow_stage?->label() ?? ''),
            'status' => ucfirst(str_replace('_', ' ', (string) $statusVal)),
            'runs' => (int) $q->runs_completed . ' / ' . (int) $q->runs_required,
            'due' => $q->due_date?->gmp(),
            'edit_url' => $q->personnel_id
                ? \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $q->personnel_id])
                : null,
        ];
    }
}

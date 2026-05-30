<?php

namespace App\Filament\Admin\Pages;

use App\Enums\WorkflowStage;
use App\Enums\Capability;
use App\Models\Qualification;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class StatusBoard extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling)
            || $u->hasCapability(Capability::QaReview)
            || $u->hasCapability(Capability::ViewQualifications)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Status Board';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Qualification Status Board';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.status-board';

    public string $search = '';
    public string $deptFilter = '';
    public string $typeFilter = '';

    public function departmentOptions(): array
    {
        return \App\Models\Department::where('is_active', true)->orderBy('name')->pluck('name', 'name')->all();
    }

    public function getStages(): array
    {
        // keep the board current: lapse overdue quals, then promote anything past incubation
        app(\App\Services\LifecycleAdvancer::class)->run();
        app(\App\Services\IncubationAdvancer::class)->run();
        $out = [];
        $byStage = Qualification::with('personnel')
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->search !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('first_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('last_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('employee_id', 'ilike', '%' . $this->search . '%')))
            ->when($this->deptFilter !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('department', $this->deptFilter)))
            ->get()
            ->groupBy(fn ($q) => $q->workflow_stage?->value ?? 'class_pending');

        foreach (WorkflowStage::pipeline() as $stage) {
            $cards = ($byStage[$stage->value] ?? collect())->map(fn ($q) => [
                'id' => $q->id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'meta' => $q->runs_completed . '/' . $q->runs_required . ' runs',
                'runs_done' => (int) $q->runs_completed,
                'runs_req' => (int) $q->runs_required,
                'due' => $q->due_date?->format('M j'),
            ])->values()->all();

            $out[] = [
                'key' => $stage->value,
                'label' => $stage->label(),
                'color' => $stage->color(),
                'cards' => $cards,
            ];
        }
        // Failed lane at the end
        $failed = ($byStage['failed'] ?? collect())->map(fn ($q) => [
            'id' => $q->id, 'name' => $q->personnel?->full_name ?? 'Unknown',
            'employee_id' => $q->personnel?->employee_id, 'meta' => 'Needs determination', 'due' => null,
        ])->values()->all();
        $out[] = ['key' => 'failed', 'label' => WorkflowStage::Failed->label(), 'color' => WorkflowStage::Failed->color(), 'cards' => $failed];

        return $out;
    }

    /** Drag a person's card to a new workflow stage. QA sign-off requires QaApprove. */
    /** Detail payload for the click-to-view modal. */
    public ?array $detail = null;

    public function showDetail(int $id): void
    {
        $q = Qualification::with('personnel', 'qaOwner')->find($id);
        if (! $q) { $this->detail = null; return; }

        $runs = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)
            ->latest('run_date')->latest('id')->limit(5)->get()
            ->map(fn ($r) => [
                'date' => $r->run_date?->format('M j, Y'),
                'result' => ucfirst($r->result?->value ?? (string) $r->result),
                'worklist' => $r->lims_worklist_id,
            ])->all();

        $this->detail = [
            'id' => $q->id,
            'name' => $q->personnel?->full_name ?? 'Unknown',
            'employee_id' => $q->personnel?->employee_id,
            'department' => $q->personnel?->department,
            'stage' => $q->workflow_stage?->label(),
            'status' => ucfirst((string) ($q->status?->value ?? $q->status ?? '')),
            'type' => ucfirst((string) ($q->type ?? '')),
            'runs' => $q->runs_completed . ' / ' . $q->runs_required,
            'due' => $q->due_date?->format('M j, Y'),
            'class_on_file' => (bool) $q->class_on_file,
            'qa_owner' => $q->qaOwner?->name,
            'recent_runs' => $runs,
            'edit_url' => $q->personnel_id
                ? \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $q->personnel_id])
                : \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $q->id]),
        ];
    }

    public function closeDetail(): void { $this->detail = null; }

    public function moveCard(int $id, string $toStage): void
    {
        $stage = WorkflowStage::tryFrom($toStage);
        if (! $stage) {
            return;
        }
        $q = Qualification::find($id);
        if (! $q) {
            return;
        }

        // QA sign-off gate
        if ($stage === WorkflowStage::QaSignoff && ! Auth::user()?->hasCapability(Capability::QaApprove)) {
            Notification::make()->danger()->title('Not authorized')
                ->body('Only QA approvers can sign off a qualification.')->send();
            return;
        }

        $q->workflow_stage = $stage;
        $q->stage_changed_at = now();

        // QA sign-off = Qualified + stamp the run
        if ($stage === WorkflowStage::QaSignoff) {
            $q->status = 'qualified';
            if (! $q->qualified_date) {
                $q->qualified_date = now();
            }
            if (! $q->due_date) {
                $q->due_date = now()->addMonths((int) \App\Models\Setting::get('cycle_months', 12));
            }
        }
        $q->save();

        Notification::make()->success()->title('Stage updated')
            ->body(($q->personnel?->full_name ?? 'Card') . ' → ' . $stage->label())->send();
    }
}

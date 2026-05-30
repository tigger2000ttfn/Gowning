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
        app(\App\Services\RunCycleAdvancer::class)->sweep();
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
            $cards = ($byStage[$stage->value] ?? collect())->map(function ($q) {
                // Cycle-aware: only count runs in the CURRENT cycle, so a fresh cycle
                // doesn't show prior-cycle runs or an old worklist/date.
                $cycleRuns = app(\App\Services\RunCycleAdvancer::class)->cycleRuns($q);
                $lastRun = $cycleRuns->last();
                $passes = $cycleRuns->filter(fn ($r) => (($r->result->value ?? $r->result) === 'pass'))->count();
                return [
                'id' => $q->id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
                'type' => $q->type?->label(),
                'meta' => $passes . '/' . $q->runs_required . ' runs',
                'runs_done' => (int) $passes,
                'runs_req' => (int) $q->runs_required,
                'due' => $q->due_date?->format('M j, Y'),
                'last_run_date' => $lastRun?->run_date?->format('M j'),
                'last_run_worklist' => $lastRun?->lims_worklist_id,
                'status' => ucfirst(str_replace('_', ' ', ($q->status?->value ?? (string) $q->status ?? ''))),
                'status_key' => $q->status?->value ?? (string) $q->status,
            ];
            })->values()->all();

            $out[] = [
                'key' => $stage->value,
                'label' => \App\Models\WorkflowStatus::labelFor('run', $stage->value, $stage->label()),
                'color' => \App\Models\WorkflowStatus::colorFor('run', $stage->value, $stage->color()),
                'cards' => $cards,
            ];
        }
        // Failed lane at the end (toggleable in Settings)
        if ((bool) \App\Models\Setting::get('board_show_failed', true)) {
            $failed = ($byStage['failed'] ?? collect())->map(fn ($q) => [
                'id' => $q->id, 'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id, 'meta' => 'Needs determination', 'due' => null,
            ])->values()->all();
            $out[] = ['key' => 'failed', 'label' => \App\Models\WorkflowStatus::labelFor('run', 'failed', WorkflowStage::Failed->label()), 'color' => \App\Models\WorkflowStatus::colorFor('run', 'failed', WorkflowStage::Failed->color()), 'cards' => $failed];
        }

        // Apply the saved lane order (drag-to-reorder persists this).
        $order = $this->laneOrder();
        if ($order) {
            usort($out, function ($a, $b) use ($order) {
                $ia = array_search($a['key'], $order, true);
                $ib = array_search($b['key'], $order, true);
                $ia = $ia === false ? 999 : $ia;
                $ib = $ib === false ? 999 : $ib;
                return $ia <=> $ib;
            });
        }

        return $out;
    }

    /** Fully-done (Archived) records, shown in a collapsed far-right Archive lane. */
    public function getArchive(): array
    {
        $signed = Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Archived->value)
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->search !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('first_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('last_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('employee_id', 'ilike', '%' . $this->search . '%')))
            ->when($this->deptFilter !== '', fn ($q) => $q->whereHas('personnel', fn ($p) => $p->where('department', $this->deptFilter)))
            ->latest('stage_changed_at')->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'due' => $q->due_date?->format('M j, Y'),
            ])->values()->all();

        return [
            'label' => \App\Models\WorkflowStatus::labelFor('run', 'archived', 'Archived'),
            'color' => \App\Models\WorkflowStatus::colorFor('run', 'archived', WorkflowStage::Archived->color()),
            'cards' => $signed,
        ];
    }

    public function laneOrder(): array
    {
        $raw = \App\Models\Setting::get('board_lane_order', '');
        return $raw ? array_values(array_filter(explode(',', $raw))) : [];
    }

    public function setLaneOrder(array $keys): void
    {
        if (! Auth::user()?->hasCapability(Capability::ManageScheduling)) return;
        \App\Models\Setting::put('board_lane_order', implode(',', $keys));
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
            'type' => ucfirst((string) ($q->type?->value ?? $q->type ?? '')),
            'runs' => app(\App\Services\RunCycleAdvancer::class)->cycleRuns($q)->filter(fn ($r) => (($r->result->value ?? $r->result) === 'pass'))->count() . ' / ' . $q->runs_required,
            'due' => $q->due_date?->format('M j, Y'),
            'class_on_file' => (bool) $q->class_on_file,
            'qa_owner' => $q->qaOwner?->name,
            'recent_runs' => $runs,
            'quick_url' => $this->quickActionUrl($q),
            'quick_label' => $this->quickActionLabel($q),
            'edit_url' => $q->personnel_id
                ? \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $q->personnel_id])
                : \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $q->id]),
        ];
    }

    public function closeDetail(): void { $this->detail = null; }

    /** Where to go to make the next entry for this person, based on their stage. */
    protected function quickActionUrl(Qualification $q): ?string
    {
        $stage = $q->workflow_stage?->value;
        return match ($stage) {
            // ready to run / scheduled / performing / incubating / results -> Run Scheduler
            'class_complete', 'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released'
                => \App\Filament\Admin\Pages\RunDayRoster::getUrl(),
            // QA stages -> QA Sign-off Queue
            'qa_review', 'qa_signoff', 'failed'
                => \App\Filament\Admin\Pages\QaQueue::getUrl(),
            // class pending -> Class Scheduler
            'class_pending'
                => \App\Filament\Admin\Pages\ClassScheduler::getUrl(),
            default => null,
        };
    }

    protected function quickActionLabel(Qualification $q): ?string
    {
        $stage = $q->workflow_stage?->value;
        return match ($stage) {
            'class_complete', 'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released'
                => 'Go To Run Scheduler',
            'qa_review', 'qa_signoff', 'failed' => 'Go To QA Queue',
            'class_pending' => 'Go To Class Scheduler',
            default => null,
        };
    }

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
            $q->archived_at = null; // back in an active completed state, not archived
        }

        // Archived = fully done and filed. Stamp archived_at and mark the latest run completed,
        // so run history reflects a completed cycle (and a future automation can sweep these).
        if ($stage === WorkflowStage::Archived) {
            $q->archived_at = now();
            $latest = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)
                ->latest('run_date')->latest('id')->first();
            if ($latest && ! $latest->qa_signed_at) {
                $latest->qa_signed_at = now();
                $latest->qa_signed_by = Auth::id();
                $latest->save();
            }
        }
        $q->save();
        // No success toast on drag-move: the card visibly moving is the confirmation.
        // (Errors/auth failures above still notify, and the workflow automations still fire.)
    }

    /** Bulk action: book the selected people into the next available run day. */
    public function bulkBookRunDay(array $ids): void
    {
        if (! Auth::user()?->hasCapability(Capability::ManageScheduling)) {
            Notification::make()->danger()->title('Not authorized')
                ->body('You need scheduling permission to book run days.')->send();
            return;
        }
        $scheduler = app(\App\Services\AutoScheduler::class);
        $booked = 0; $skipped = 0;
        foreach ($ids as $id) {
            $q = Qualification::with('personnel')->find((int) $id);
            if (! $q) { continue; }
            // only people who are class-complete / ready (not already scheduled or qualified)
            if (in_array($q->workflow_stage, [WorkflowStage::ClassComplete, WorkflowStage::ClassPending], true)) {
                if ($scheduler->bookNext($q)) { $booked++; } else { $skipped++; }
            } else {
                $skipped++;
            }
        }
        Notification::make()->success()->title('Bulk scheduling done')
            ->body("Booked {$booked}. Skipped {$skipped} (not ready or no slot available).")->send();
    }
}

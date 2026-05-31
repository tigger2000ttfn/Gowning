<?php
namespace App\Filament\Admin\Resources\QualificationResource\Pages;

use App\Enums\Capability;
use App\Enums\QualificationStatus;
use App\Enums\QualificationType;
use App\Enums\WorkflowStage;
use App\Filament\Admin\Resources\QualificationResource;
use App\Models\ClassCompletion;
use App\Models\Personnel;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ListQualifications extends ListRecords
{
    protected static string $resource = QualificationResource::class;

    /** Render our custom command-center view instead of the default Filament table page. */
    public function getView(): string { return 'filament.pages.active-runs'; }
    public function getHeader(): ?\Illuminate\Contracts\View\View { return view('filament.empty-header'); }
    public function getHeading(): string { return ''; }
    public function getSubheading(): ?string { return null; }

    public string $tab = 'roster';                 // roster | dashboard
    public string $filterStage = '';               // '' | stage value
    public string $search = '';

    public function getTitle(): string { return 'Active Runs'; }

    public function setTab(string $t): void { $this->tab = in_array($t, ['roster', 'dashboard'], true) ? $t : 'roster'; }
    public function isSuperUser(): bool { return (bool) Auth::user()?->hasCapability(Capability::ManageUsers); }

    // ===================== ROSTER DATA =====================

    protected function activeQuery()
    {
        return Qualification::query()
            ->with('personnel')
            ->where('workflow_stage', '!=', WorkflowStage::Archived->value)
            ->whereNull('superseded_at');
    }

    public function stageOptions(): array
    {
        $opts = ['' => 'All Stages'];
        foreach (WorkflowStage::cases() as $c) {
            if (in_array($c->value, ['archived'], true)) continue;
            $opts[$c->value] = \App\Models\WorkflowStatus::labelFor('run', $c->value, $c->label());
        }
        return $opts;
    }

    public function rows(): array
    {
        $q = $this->activeQuery();
        if ($this->filterStage !== '') $q->where('workflow_stage', $this->filterStage);
        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->whereHas('personnel', fn ($p) => $p
                ->where('first_name', 'ilike', $term)->orWhere('last_name', 'ilike', $term)
                ->orWhere('employee_id', 'ilike', $term));
        }

        return $q->get()
            ->sortBy(fn ($qual) => sprintf('%02d-%020d', $this->stageSort($qual->workflow_stage?->value), $qual->due_date?->timestamp ?? PHP_INT_MAX))
            ->map(function ($qual) {
                $stageEnum = $qual->workflow_stage;
                $stageVal = $stageEnum?->value;
                $stageLabel = $stageEnum ? \App\Models\WorkflowStatus::labelFor('run', $stageVal, $stageEnum->label()) : '-';
                [$pillTxt, $pillColor] = $this->statusPill($qual);
                $latestRun = $qual->runs()->whereNotNull('lims_worklist_id')->latest('id')->first();
                $worklist = $latestRun?->lims_worklist_id ?? $qual->lims_worklist_id;
                $needsWorklist = in_array($stageVal, ['run_performed', 'incubating', 'awaiting_results'], true) && ! $worklist;
                return [
                    'id' => $qual->id,
                    'personnel_id' => $qual->personnel_id,
                    'name' => $qual->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $qual->personnel?->employee_id,
                    'department' => $qual->personnel?->department,
                    'type' => $qual->sessionLabel(),
                    'stage' => $stageVal,
                    'stage_label' => $stageLabel,
                    'stage_color' => $stageEnum ? $stageEnum->color() : '#6B6B73',
                    'status_pill' => $pillTxt,
                    'status_color' => $pillColor,
                    'passes' => (int) $qual->runs_completed,
                    'required' => (int) $qual->runs_required,
                    'due' => $qual->due_date?->gmp(),
                    'past_due' => $qual->isPastDue(),
                    'worklist' => $worklist,
                    'needs_worklist' => $needsWorklist,
                ];
            })->values()->all();
    }

    protected function stageSort(?string $stage): int
    {
        $order = ['class_pending', 'class_complete', 'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released', 'qa_review', 'qa_signoff', 'failed'];
        $i = array_search($stage, $order, true);
        return $i === false ? 99 : $i;
    }

    /** The display status pill (mirrors the board logic). Returns [label, hexColor]. */
    protected function statusPill(Qualification $qual): array
    {
        $stage = $qual->workflow_stage?->value;
        $status = $qual->status instanceof \BackedEnum ? $qual->status->value : $qual->status;
        $inPipeline = in_array($stage, ['run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released', 'qa_review'], true);
        if ($status === 'lapsed') return ['Lapsed Qual', '#C8102E'];
        if ($stage === 'failed') return ['Failed', '#C8102E'];
        if ($stage === 'qa_signoff') return ['Qualified', '#2E7D5B'];
        if ($status === 'qualified' && $qual->qualified_date && ! $inPipeline) {
            return $qual->isPastDue() ? ['Lapsed Qual', '#C8102E'] : ['Qualified', '#2E7D5B'];
        }
        if ($inPipeline || $status === 'in_progress') return ['In Progress', '#C79A2E'];
        if ($status === 'pending') return ['Pending', '#6B6B73'];
        return [ucwords(str_replace('_', ' ', (string) $status)), '#6B6B73'];
    }

    // ===================== DASHBOARD STATS =====================

    public function stats(): array
    {
        $all = $this->activeQuery()->get();
        $byStage = $all->groupBy(fn ($q) => $q->workflow_stage?->value);
        $pipeline = ['run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released', 'qa_review'];
        $dueSoonWindow = (int) Setting::get('requal_window_days', 30);

        $inPipeline = $all->filter(fn ($q) => in_array($q->workflow_stage?->value, $pipeline, true))->count();
        $pastDue = $all->filter(fn ($q) => $q->isPastDue())->count();
        $dueSoon = $all->filter(function ($q) use ($dueSoonWindow) {
            if (! $q->due_date || $q->isPastDue()) return false;
            return now()->diffInDays($q->due_date, false) <= $dueSoonWindow;
        })->count();
        $awaitingClass = $all->filter(fn ($q) => in_array($q->workflow_stage?->value, ['class_pending', 'class_complete'], true))->count();
        $inQa = ($byStage['qa_review'] ?? collect())->count();

        return [
            'total' => $all->count(),
            'in_pipeline' => $inPipeline,
            'awaiting_class' => $awaitingClass,
            'in_qa' => $inQa,
            'due_soon' => $dueSoon,
            'past_due' => $pastDue,
        ];
    }

    /** Per-stage counts for the dashboard funnel. */
    public function stageFunnel(): array
    {
        $all = $this->activeQuery()->get();
        $byStage = $all->groupBy(fn ($q) => $q->workflow_stage?->value);
        $funnel = [];
        foreach (['class_pending' => 'Class Pending', 'class_complete' => 'Class Complete', 'run_scheduled' => 'Run Scheduled', 'run_performed' => 'Run Performed', 'incubating' => 'Incubating', 'awaiting_results' => 'Awaiting Results', 'results_released' => 'QCM Review', 'qa_review' => 'QA Review', 'qa_signoff' => 'QA Approved'] as $k => $label) {
            $funnel[] = ['key' => $k, 'label' => $label, 'count' => ($byStage[$k] ?? collect())->count(), 'color' => WorkflowStage::tryFrom($k)?->color() ?? '#6B6B73'];
        }
        return $funnel;
    }

    // ===================== DATA-GAP FIX-IT CARDS =====================

    public function gaps(): array
    {
        $gaps = [];

        $bookedNoQual = \App\Models\Reservation::query()
            ->whereIn('status', ['requested', 'approved'])
            ->whereHas('personnel', fn ($p) => $p->whereDoesntHave('qualification'))
            ->with('personnel')->get()->pluck('personnel')->filter()->unique('id');
        $gaps['booked_no_qual'] = [
            'count' => $bookedNoQual->count(),
            'people' => $bookedNoQual->take(12)->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name, 'employee_id' => $p->employee_id])->all(),
        ];

        $noWorklist = $this->activeQuery()
            ->whereDoesntHave('runs', fn ($r) => $r->whereNotNull('lims_worklist_id'))
            ->whereNull('lims_worklist_id')
            ->whereIn('workflow_stage', [WorkflowStage::RunPerformed->value, WorkflowStage::Incubating->value, WorkflowStage::AwaitingResults->value])
            ->get();
        $gaps['no_worklist'] = [
            'count' => $noWorklist->count(),
            'people' => $noWorklist->take(12)->map(fn ($q) => ['id' => $q->id, 'name' => $q->personnel?->full_name ?? 'Unknown', 'employee_id' => $q->personnel?->employee_id])->all(),
        ];

        $noClass = $this->activeQuery()
            ->where('class_on_file', false)
            ->whereIn('workflow_stage', [WorkflowStage::RunScheduled->value, WorkflowStage::RunPerformed->value, WorkflowStage::Incubating->value, WorkflowStage::AwaitingResults->value])
            ->get();
        $gaps['no_class'] = [
            'count' => $noClass->count(),
            'people' => $noClass->take(12)->map(fn ($q) => ['id' => $q->id, 'name' => $q->personnel?->full_name ?? 'Unknown', 'employee_id' => $q->personnel?->employee_id])->all(),
        ];

        return $gaps;
    }

    // ---- Link-worklist modal (fix the gap right here) ----
    public ?int $wlQid = null;
    public string $wlValue = '';
    public function openLinkWorklist(int $qid): void
    {
        $q = Qualification::find($qid);
        $run = $q ? QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first() : null;
        $this->wlQid = $qid;
        $this->wlValue = $run?->lims_worklist_id ?? '';
    }
    public function closeLinkWorklist(): void { $this->wlQid = null; $this->wlValue = ''; }
    public function wlPersonName(): ?string
    {
        return $this->wlQid ? (Qualification::with('personnel')->find($this->wlQid)?->personnel?->full_name ?? 'Operator') : null;
    }
    public function saveLinkWorklist(): void
    {
        $q = Qualification::find($this->wlQid);
        if (! $q) { $this->wlQid = null; return; }
        $wl = strtoupper(trim($this->wlValue));
        if ($wl === '') { Notification::make()->danger()->title('Worklist Required')->send(); return; }
        if (! str_starts_with($wl, 'EM-')) { $wl = 'EM-' . ltrim(preg_replace('/^EM[-\s]*/i', '', $wl), '-'); }
        $q->lims_worklist_id = $wl;
        $q->save();
        $runsQ = QualificationRun::where('personnel_id', $q->personnel_id);
        if ($q->cycle_started_at) { $runsQ->whereDate('run_date', '>=', $q->cycle_started_at); }
        $runsQ->update(['lims_worklist_id' => $wl]);
        $latest = QualificationRun::where('personnel_id', $q->personnel_id)->latest('id')->first();
        if ($latest) { app(\App\Services\WorklistSync::class)->syncRun($latest); }
        $this->wlQid = null; $this->wlValue = '';
        Notification::make()->success()->title('Worklist Linked')->body($wl . ' linked. LIMS data will sync.')->send();
    }

    // ---- In-place onboarding (super user) for a booked person with no qualification ----
    public ?int $onboardPersonId = null;
    public array $onboard = ['type' => 'initial', 'due_date' => null, 'class_done' => false, 'class_date' => null];

    public function openOnboard(int $personnelId): void
    {
        if (! $this->isSuperUser()) { Notification::make()->danger()->title('Not Authorized')->body('Super user required.')->send(); return; }
        $this->onboardPersonId = $personnelId;
        $this->onboard = ['type' => 'initial', 'due_date' => null, 'class_done' => false, 'class_date' => null];
    }
    public function closeOnboard(): void { $this->onboardPersonId = null; }
    public function onboardPersonName(): ?string
    {
        return $this->onboardPersonId ? Personnel::find($this->onboardPersonId)?->full_name : null;
    }

    public function saveOnboard(): void
    {
        if (! $this->isSuperUser()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $p = Personnel::find($this->onboardPersonId);
        if (! $p) { $this->onboardPersonId = null; return; }
        if ($p->qualification) {
            Notification::make()->warning()->title('Already Has A Qualification')->send();
            $this->onboardPersonId = null;
            return;
        }
        if (! $this->onboard['due_date']) {
            Notification::make()->danger()->title('Due Date Required')->send();
            return;
        }
        $isAnnual = ($this->onboard['type'] ?? 'initial') === 'annual';
        $cycleType = $isAnnual ? QualificationType::Annual : QualificationType::Initial;
        $recordClass = $isAnnual && ($this->onboard['class_done'] ?? false) && ($this->onboard['class_date'] ?? null);
        $stage = $recordClass ? WorkflowStage::ClassComplete : WorkflowStage::ClassPending;

        $qual = Qualification::create([
            'personnel_id' => $p->id,
            'type' => $cycleType,
            'status' => QualificationStatus::Pending,
            'runs_required' => $cycleType->runsRequired(),
            'runs_completed' => 0,
            'due_date' => Carbon::parse($this->onboard['due_date'])->toDateString(),
            'workflow_stage' => $stage,
            'stage_changed_at' => now(),
            'class_on_file' => (bool) $recordClass,
            'class_on_file_date' => $recordClass ? Carbon::parse($this->onboard['class_date'])->toDateString() : null,
        ]);
        if ($recordClass) {
            ClassCompletion::create([
                'personnel_id' => $p->id,
                'employee_id' => $p->employee_id,
                'class_name' => 'Gowning Qualification Class',
                'completion_date' => Carbon::parse($this->onboard['class_date'])->toDateString(),
                'source' => 'manual',
            ]);
        }
        app(\App\Services\QualificationSeeder::class)->reconcile($qual);
        $this->onboardPersonId = null;
        Notification::make()->success()->title('Qualification Created')->body($p->full_name . ' is now in the workflow.')->send();
    }
}

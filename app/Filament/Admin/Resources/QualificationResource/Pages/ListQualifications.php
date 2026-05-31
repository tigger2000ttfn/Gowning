<?php
namespace App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Enums\Capability;
use App\Enums\QualificationStatus;
use App\Enums\QualificationType;
use App\Enums\WorkflowStage;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\QualificationResource;
use App\Models\ClassCompletion;
use App\Models\Personnel;
use App\Models\Qualification;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ListQualifications extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Active Runs';
    public ?string $gqsSubtitle = 'Current qualification status for active people: due, in progress, lapsed, or mid-pipeline. Completed run history lives under Run Completions.';
    public ?string $gqsIcon = 'heroicon-o-shield-check';
    protected static string $resource = QualificationResource::class;

    // ---- In-place onboarding (super user) for a booked person with no qualification ----
    public ?int $onboardPersonId = null;
    public array $onboard = ['type' => 'initial', 'due_date' => null, 'class_done' => false, 'class_date' => null];

    public function isSuperUser(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::ManageUsers);
    }

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
        Notification::make()->success()->title('Qualification Created')
            ->body($p->full_name . ' is now in the workflow.')->send();
    }

    /**
     * Data-gap alerts so analysts can fill missing pieces on people who are real/active but whose
     * historical data is not fully entered yet. Shown as stat boxes above the table.
     */
    public function gqsAlerts(): array
    {
        $alerts = [];

        // 1) People with an active run reservation but NO qualification record at all. They are booked
        //    but invisible to the pipeline until a qualification exists - their historical/onboarding
        //    data needs entering.
        $bookedNoQual = \App\Models\Reservation::query()
            ->whereIn('status', ['requested', 'approved'])
            ->whereHas('personnel', fn ($p) => $p->whereDoesntHave('qualification'))
            ->with('personnel')->get()
            ->pluck('personnel')->filter()->unique('id');
        if ($bookedNoQual->count()) {
            $alerts[] = [
                'count' => $bookedNoQual->count(),
                'label' => 'Booked, No Qualification Record',
                'hint' => 'Has a run reservation but no qualification. Set up their qualification to enter the workflow.',
                'names' => $bookedNoQual->take(5)->map(fn ($p) => $p->full_name)->implode(', '),
                'people' => $bookedNoQual->take(8)->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name])->all(),
                'action' => $this->isSuperUser() ? 'openOnboard' : null,
                'accent' => '#C8102E', 'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }

        // 2) Active qualifications in/near the run pipeline with NO classroom training on file. The class
        //    is a prerequisite; flag so it can be recorded.
        $noClass = \App\Models\Qualification::query()
            ->whereNull('superseded_at')
            ->where('class_on_file', false)
            ->whereIn('workflow_stage', [
                \App\Enums\WorkflowStage::RunScheduled->value, \App\Enums\WorkflowStage::RunPerformed->value,
                \App\Enums\WorkflowStage::Incubating->value, \App\Enums\WorkflowStage::AwaitingResults->value,
            ])->with('personnel')->get();
        if ($noClass->count()) {
            $alerts[] = [
                'count' => $noClass->count(),
                'label' => 'Missing Classroom Training',
                'hint' => 'In the run pipeline without gowning class on file. Record their class.',
                'names' => $noClass->take(5)->map(fn ($q) => $q->personnel?->full_name)->filter()->implode(', '),
                'accent' => '#C79A2E', 'icon' => 'heroicon-o-academic-cap',
            ];
        }

        // 3) Runs performed / in pipeline with no LIMS worklist linked yet (so nothing auto-flows).
        $noWorklist = \App\Models\Qualification::query()
            ->whereNull('superseded_at')
            ->whereNull('lims_worklist_id')
            ->whereIn('workflow_stage', [
                \App\Enums\WorkflowStage::RunPerformed->value, \App\Enums\WorkflowStage::Incubating->value,
                \App\Enums\WorkflowStage::AwaitingResults->value,
            ])->with('personnel')->get();
        if ($noWorklist->count()) {
            $alerts[] = [
                'count' => $noWorklist->count(),
                'label' => 'No LIMS Worklist Linked',
                'hint' => 'Run is performed but has no worklist, so LIMS data cannot sync. Link it on the Run Scheduler.',
                'names' => $noWorklist->take(5)->map(fn ($q) => $q->personnel?->full_name)->filter()->implode(', '),
                'accent' => '#1F6FB2', 'icon' => 'heroicon-o-beaker',
            ];
        }

        return $alerts;
    }
}

<?php
namespace App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\QualificationResource;
use Filament\Resources\Pages\ListRecords;
class ListQualifications extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Active Runs';
    public ?string $gqsSubtitle = 'Current qualification status for active people: due, in progress, lapsed, or mid-pipeline. Completed run history lives under Run Completions.';
    public ?string $gqsIcon = 'heroicon-o-shield-check';
    protected static string $resource = QualificationResource::class;

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
                'hint' => 'Has a run reservation but no qualification. Enter their onboarding/historical data.',
                'names' => $bookedNoQual->take(5)->map(fn ($p) => $p->full_name)->implode(', '),
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

<?php

namespace App\Livewire;

use App\Models\Qualification;
use App\Models\QualificationRun;
use Livewire\Component;

/**
 * One shared qualification detail modal, mounted globally, opened from ANY page by dispatching:
 *   $dispatch('open-qual-modal', { id: <qualification id> })
 * or in Blade:  wire:click="$dispatch('open-qual-modal', { id: {{ $id }} })"
 *
 * This is the single source of truth for "click a person -> see their record" across Active Runs,
 * Active Bookings, Lab Review, QA Review, and the kanban board.
 */
class QualificationModal extends Component
{
    public ?array $detail = null;
    public ?int $qid = null;

    // Inline worklist link (so the gap can be fixed right from the modal).
    public bool $linking = false;
    public string $wlValue = '';

    #[\Livewire\Attributes\On('open-qual-modal')]
    public function open(int $id): void
    {
        $this->qid = $id;
        $this->linking = false;
        $this->wlValue = '';
        $this->build();
    }

    public function close(): void
    {
        $this->detail = null;
        $this->qid = null;
        $this->linking = false;
    }

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

    public function isSuperUser(): bool
    {
        return (bool) \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageUsers);
    }

    protected function build(): void
    {
        $q = Qualification::with(['personnel'])->find($this->qid);
        if (! $q) { $this->detail = null; return; }

        $runs = $q->runs()->orderByDesc('run_date')->orderByDesc('id')->limit(6)->get()
            ->map(fn ($r) => [
                'date' => $r->run_date?->gmp(),
                'result' => ucfirst((string) ($r->result instanceof \BackedEnum ? $r->result->value : $r->result)),
                'worklist' => $r->lims_worklist_id,
                'inc_status' => $r->lims_inc_status,
                'evaluation' => $r->lims_evaluation,
                'nc' => $r->lims_nc_number,
                'nc_url' => $r->lims_nc_url,
            ])->all();

        [$pillTxt, $pillColor] = $this->statusPill($q);
        $stageVal = $q->workflow_stage?->value;
        $type = $q->type instanceof \BackedEnum ? $q->type->value : $q->type;

        // Where the record is worked next - and whether sign-off can be launched from here.
        $reviewUrl = match ($stageVal) {
            'awaiting_results', 'results_released' => \App\Filament\Admin\Pages\IncubationBoard::getUrl(),
            'qa_review', 'qa_signoff', 'failed' => \App\Filament\Admin\Pages\QaQueue::getUrl(),
            'class_pending', 'class_complete' => \App\Filament\Admin\Pages\ClassScheduler::getUrl(),
            'run_scheduled', 'run_performed', 'incubating' => \App\Filament\Admin\Pages\RunDayRoster::getUrl(),
            default => null,
        };
        $reviewLabel = match ($stageVal) {
            'awaiting_results', 'results_released' => 'Open In Lab Review',
            'qa_review', 'qa_signoff', 'failed' => 'Open In QA Review',
            'class_pending', 'class_complete' => 'Open In Class Scheduler',
            'run_scheduled', 'run_performed', 'incubating' => 'Open In Run Scheduler',
            default => null,
        };

        $latestRun = $q->runs()->whereNotNull('lims_worklist_id')->latest('id')->first();

        $stepOrder = ['class_complete', 'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released', 'qa_review', 'qa_signoff'];
        $stepLabels = ['Class', 'Scheduled', 'Performed', 'Incubating', 'Results', 'QCM Review', 'QA Review', 'QA Approved'];
        $curStep = array_search($stageVal, $stepOrder, true);
        if ($curStep === false) $curStep = -1;
        $steps = [];
        foreach ($stepOrder as $i => $sk) {
            $steps[] = ['label' => $stepLabels[$i], 'done' => $i < $curStep, 'current' => $i === $curStep];
        }

        $lims = null;
        if ($latestRun) {
            $lims = [
                'worklist' => $latestRun->lims_worklist_id,
                'evaluation' => $latestRun->lims_evaluation,
                'inc1' => trim(($latestRun->lims_inc1_incubator ? $latestRun->lims_inc1_incubator . ': ' : '') . ($latestRun->lims_inc1_start ?: '?') . ($latestRun->lims_inc1_end ? ' to ' . $latestRun->lims_inc1_end : '')),
                'inc2' => trim(($latestRun->lims_inc2_incubator ? $latestRun->lims_inc2_incubator . ': ' : '') . ($latestRun->lims_inc2_start ?: '?') . ($latestRun->lims_inc2_end ? ' to ' . $latestRun->lims_inc2_end : '')),
                'nc' => $latestRun->lims_nc_number,
                'nc_url' => $latestRun->lims_nc_url,
            ];
        }

        $dueTag = ($pillTxt === 'Lapsed Qual' || $q->isPastDue()) ? 'Lapsed' : ($type === 'annual' ? 'Requal' : 'Initial');

        $this->detail = [
            'id' => $q->id,
            'name' => $q->personnel?->full_name ?? 'Unknown',
            'employee_id' => $q->personnel?->employee_id,
            'department' => $q->personnel?->department,
            'job_title' => $q->personnel?->job_title,
            'type' => $q->sessionLabel(),
            'stage_val' => $stageVal,
            'stage_label' => $q->workflow_stage ? \App\Models\WorkflowStatus::labelFor('run', $stageVal, $q->workflow_stage->label()) : '-',
            'stage_color' => $q->workflow_stage?->color() ?? '#6B6B73',
            'status_pill' => $pillTxt,
            'status_color' => $pillColor,
            'passes' => (int) $q->runs_completed,
            'required' => (int) $q->runs_required,
            'due' => $q->due_date?->gmp(),
            'due_label' => $type === 'annual' ? 'Requal Due' : 'Initial Due',
            'due_tag' => $dueTag,
            'past_due' => $q->isPastDue(),
            'qualified_date' => $q->qualified_date?->gmp(),
            'class_on_file' => (bool) $q->class_on_file,
            'class_on_file_date' => $q->class_on_file_date?->gmp(),
            'worklist' => $latestRun?->lims_worklist_id ?? $q->lims_worklist_id,
            'needs_worklist' => in_array($stageVal, ['run_performed', 'incubating', 'awaiting_results'], true) && ! ($latestRun?->lims_worklist_id ?? $q->lims_worklist_id),
            'qa_owner' => $q->qaOwner?->name,
            'runs' => $runs,
            'steps' => $steps,
            'lims' => $lims,
            'review_url' => $reviewUrl,
            'review_label' => $reviewLabel,
            'record_url' => \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $q->id]),
            // QA sign-off can be launched straight from the modal when the record is in QA review.
            'can_signoff' => in_array($stageVal, ['qa_review'], true)
                && (bool) \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::QaApprove),
            'signoff_url' => \App\Filament\Admin\Pages\QaQueue::getUrl(['signoff' => $q->id]),
        ];
    }

    // ----- Inline worklist link -----
    public function startLink(): void { $this->linking = true; $this->wlValue = ''; }
    public function cancelLink(): void { $this->linking = false; $this->wlValue = ''; }

    public function worklistSuggestions(): array
    {
        $term = preg_replace('/^EM[-\s]*/i', '', trim($this->wlValue));
        $q = \App\Models\LimsWorklist::query()->orderByDesc('id')->limit(15);
        if ($term !== '') $q->where('worklist', 'ilike', '%' . $term . '%');
        return $q->pluck('worklist')->map(fn ($w) => preg_replace('/^EM-/i', '', (string) $w))->filter()->unique()->values()->all();
    }

    public function saveLink(): void
    {
        $q = Qualification::find($this->qid);
        if (! $q) return;
        $wl = strtoupper(trim($this->wlValue));
        if ($wl === '') { \Filament\Notifications\Notification::make()->danger()->title('Worklist Required')->send(); return; }
        if (! str_starts_with($wl, 'EM-')) { $wl = 'EM-' . ltrim(preg_replace('/^EM[-\s]*/i', '', $wl), '-'); }
        $q->lims_worklist_id = $wl;
        $q->save();
        $runsQ = QualificationRun::where('personnel_id', $q->personnel_id);
        if ($q->cycle_started_at) { $runsQ->whereDate('run_date', '>=', $q->cycle_started_at); }
        $runsQ->update(['lims_worklist_id' => $wl]);
        $latest = QualificationRun::where('personnel_id', $q->personnel_id)->latest('id')->first();
        if ($latest) { app(\App\Services\WorklistSync::class)->syncRun($latest); }
        $this->linking = false;
        $this->wlValue = '';
        $this->build();
        \Filament\Notifications\Notification::make()->success()->title('Worklist Linked')->body($wl . ' linked. LIMS data will sync.')->send();
    }

    public function render()
    {
        return view('livewire.qualification-modal');
    }
}

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

    public function getStages(): array
    {
        $out = [];
        $byStage = Qualification::with('personnel')
            ->get()
            ->groupBy(fn ($q) => $q->workflow_stage?->value ?? 'class_pending');

        foreach (WorkflowStage::pipeline() as $stage) {
            $cards = ($byStage[$stage->value] ?? collect())->map(fn ($q) => [
                'id' => $q->id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'meta' => $q->runs_completed . '/' . $q->runs_required . ' runs',
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

<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Models\Qualification;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class QaQueue extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'QA Sign-off Queue';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'QA Sign-off Queue';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qa-queue';

    public function getQueue()
    {
        return Qualification::with('personnel')
            ->whereIn('workflow_stage', [WorkflowStage::QaReview->value, WorkflowStage::ResultsReleased->value])
            ->orderBy('stage_changed_at')
            ->get();
    }

    public function getFailed()
    {
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Failed->value)
            ->get();
    }

    public function canApprove(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::QaApprove);
    }

    public function signOff(int $id): void
    {
        if (! $this->canApprove()) {
            Notification::make()->danger()->title('Not authorized')->body('QA approver role required to sign off.')->send();
            return;
        }
        $q = Qualification::with('personnel')->find($id);
        if (! $q) return;
        $q->workflow_stage = WorkflowStage::QaSignoff;
        $q->stage_changed_at = now();
        $q->status = 'qualified';
        if (! $q->qualified_date) $q->qualified_date = now();
        if (! $q->due_date) $q->due_date = now()->addMonths((int) \App\Models\Setting::get('cycle_months', 12));
        $q->save();
        Notification::make()->success()->title('Signed off')
            ->body(($q->personnel?->full_name ?? 'Qualification') . ' is now Qualified.')->send();
    }

    public function markFailed(int $id): void
    {
        if (! $this->canApprove()) {
            Notification::make()->danger()->title('Not authorized')->send();
            return;
        }
        $q = Qualification::find($id);
        if (! $q) return;
        $q->workflow_stage = WorkflowStage::Failed;
        $q->stage_changed_at = now();
        $q->save();
        Notification::make()->warning()->title('Marked for determination')->send();
    }
}

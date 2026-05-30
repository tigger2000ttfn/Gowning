<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Models\User;
use App\Models\Qualification;
use App\Models\Setting;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class QaTeamView extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return false; } // in Manage
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'QA Team View';
    protected static ?string $title = 'QA Team View';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qa-team-view';

    public string $tab = 'overview';   // overview | table | unassigned | calendar
    public ?int $assignQualId = null;
    public ?int $assignOwnerId = null;
    public bool $showAssign = false;

    protected function teamMembers()
    {
        $byTeam = User::where('is_active', true)->where('team', 'qa')->get();
        if ($byTeam->isNotEmpty()) return $byTeam;
        return User::where('is_active', true)->get()
            ->filter(fn ($u) => $u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove))
            ->values();
    }

    public function manager(): ?User
    {
        $id = Setting::get('qa_manager_id');
        return $id ? User::find($id) : null;
    }

    /** Pending approvals (the QA Sign-off queue), with their owner. */
    protected function pendingApprovals()
    {
        return Qualification::with('personnel', 'qaOwner')
            ->whereIn('workflow_stage', [WorkflowStage::QaReview->value, WorkflowStage::ResultsReleased->value])
            ->orderBy('stage_changed_at')->get();
    }

    public function getReviewers()
    {
        $pending = $this->pendingApprovals();
        return $this->teamMembers()->map(function ($u) use ($pending) {
            $owned = $pending->where('qa_owner_id', $u->id);
            return (object) [
                'id' => $u->id,
                'name' => $u->name,
                'is_manager' => (bool) $u->is_team_manager,
                'owned' => $owned->values(),
                'load' => $owned->count(),
            ];
        })->sortByDesc('load')->values();
    }

    public function getUnassigned()
    {
        return $this->pendingApprovals()->whereNull('qa_owner_id')->values();
    }

    public function totalPending(): int { return $this->pendingApprovals()->count(); }

    /** Sign-off forecast: qualifications coming due over the next 6 weeks, grouped by date. */
    public function getCalendar(): array
    {
        $today = now()->toDateString();
        $quals = Qualification::with('personnel', 'qaOwner')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', now()->addDays(42)->toDateString())
            ->orderBy('due_date')->get();

        return $quals->groupBy(fn ($q) => $q->due_date->format('Y-m-d'))
            ->map(fn ($group, $day) => [
                'date' => \Illuminate\Support\Carbon::parse($day)->gmpDDM(),
                'rows' => $group->map(fn ($q) => [
                    'qual_id' => $q->id,
                    'name' => $q->personnel?->full_name,
                    'employee_id' => $q->personnel?->employee_id,
                    'owner' => $q->qaOwner?->name,
                ])->all(),
            ])->values()->all();
    }

    public function reviewerOptions(): array
    {
        return $this->teamMembers()->mapWithKeys(fn ($u) => [$u->id => $u->name])->all();
    }

    public function openAssign(int $qualId): void
    {
        $this->assignQualId = $qualId;
        $this->assignOwnerId = Qualification::find($qualId)?->qa_owner_id;
        $this->showAssign = true;
    }

    public function saveAssign(): void
    {
        $q = Qualification::find($this->assignQualId);
        if ($q) {
            $q->qa_owner_id = $this->assignOwnerId ?: null;
            $q->save();
            \Filament\Notifications\Notification::make()->success()->title('Approval owner assigned')->send();
        }
        $this->showAssign = false;
    }
}

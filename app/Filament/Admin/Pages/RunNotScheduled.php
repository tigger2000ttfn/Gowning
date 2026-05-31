<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\Reservation;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class RunNotScheduled extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling)
            || $u->hasCapability(Capability::ViewQualifications)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return false; } // absorbed into Run Scheduler > Overview tab
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Run Not Scheduled';
    protected static string|\UnitEnum|null $navigationGroup = 'Sessions';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Run Not Scheduled';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.run-not-scheduled';

    public function mount(): void
    {
        // keep current: lapse overdue, then auto-book anyone ready into the next available day
        app(\App\Services\LifecycleAdvancer::class)->run();
        app(\App\Services\AutoScheduler::class)->run();
    }

    /** People who are Class Complete (ready) but have no active reservation yet. */
    public function getWaiting()
    {
        // personnel_ids with an open/active reservation
        $booked = Reservation::whereIn('status', ['requested', 'approved'])
            ->pluck('personnel_id')->filter()->unique()->all();

        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::ClassComplete->value)
            ->whereNotIn('personnel_id', $booked)
            ->get()
            ->map(fn ($q) => (object) [
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
                'runs_required' => $q->runs_required,
                'class_date' => $q->class_on_file_date,
                'since' => $q->stage_changed_at,
                'is_requal' => $q->status === 'lapsed' || $q->qa_recommendation !== null,
            ]);
    }
}

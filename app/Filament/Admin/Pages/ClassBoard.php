<?php

namespace App\Filament\Admin\Pages;

use App\Models\ClassEnrollment;
use App\Models\Qualification;
use App\Enums\Capability;
use App\Enums\WorkflowStage;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ClassBoard extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageClasses)
            || $u->hasCapability(Capability::ManageAttendance)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Class Board';
    protected static string|\UnitEnum|null $navigationGroup = 'Classroom';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Gowning Class Board';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.class-board';

    public array $lanes = [
        'signed_up' => ['label' => 'Signed Up', 'color' => '#1F6FB2'],
        'attended'  => ['label' => 'Attended (Trainer)',  'color' => '#C79A2E'],
        'completed' => ['label' => 'Completed (QA Approved)', 'color' => '#2E7D5B'],
        'no_show'   => ['label' => 'No-Show',    'color' => '#C8102E'],
    ];

    /** Historical (archived completed) enrollments, shown in a collapsed far-right lane.
     *  An automation will move Completed -> Historical after a retention period. */
    public function getArchive(): array
    {
        $cards = ClassEnrollment::with(['personnel', 'classSession.trainingClass'])
            ->where('status', 'historical')
            ->latest('updated_at')->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->personnel?->full_name ?? $e->employee_id ?? 'Unknown',
                'employee_id' => $e->employee_id,
                'class' => $e->classSession?->trainingClass?->name,
                'date' => $e->classSession?->session_date?->format('M j'),
            ])->values()->all();
        return [
            'label' => \App\Models\WorkflowStatus::labelFor('class', 'historical', 'Historical'),
            'color' => \App\Models\WorkflowStatus::colorFor('class', 'historical', '#5A5A62'),
            'cards' => $cards,
        ];
    }

    /** People who still need the gowning class and aren't signed up to any session yet. */
    public function getNeedsClass(): array
    {
        $enrolled = ClassEnrollment::whereIn('status', ['signed_up', 'attended'])
            ->pluck('personnel_id')->filter()->unique()->all();
        return \App\Models\Qualification::with('personnel')
            ->where('workflow_stage', \App\Enums\WorkflowStage::ClassPending->value)
            ->whereNotIn('personnel_id', $enrolled)
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'personnel_id' => $q->personnel_id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
            ])->values()->all();
    }

    public function getColumns(): array
    {
        $out = [];
        $byStatus = ClassEnrollment::with(['personnel', 'classSession.trainingClass'])
            ->whereNotIn('status', ['historical', 'cancelled'])
            ->get()->groupBy('status');
        foreach ($this->lanes as $key => $meta) {
            $label = \App\Models\WorkflowStatus::labelFor('class', $key, $meta['label']);
            $color = \App\Models\WorkflowStatus::colorFor('class', $key, $meta['color']);
            $cards = ($byStatus[$key] ?? collect())->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->personnel?->full_name ?? $e->employee_id ?? 'Unknown',
                'employee_id' => $e->employee_id,
                'department' => $e->personnel?->department,
                'class' => $e->classSession?->trainingClass?->name,
                'date' => $e->classSession?->session_date?->format('M j'),
                'status_label' => $label,
                'status_color' => $color,
            ])->values()->all();
            $out[$key] = [
                'label' => $label,
                'color' => $color,
                'cards' => $cards,
            ];
        }
        return $out;
    }

    /** Drag an enrollment to a new status. 'completed' advances the person's workflow stage. */
    public ?array $detail = null;

    public function showDetail(int $id): void
    {
        $e = ClassEnrollment::with(['personnel', 'classSession.trainingClass', 'classSession.instructorUser'])->find($id);
        if (! $e) { $this->detail = null; return; }
        $p = $e->personnel;
        $q = $p ? \App\Models\Qualification::where('personnel_id', $p->id)->first() : null;
        $statusVal = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;
        $qStatusVal = $q ? ($q->status instanceof \BackedEnum ? $q->status->value : $q->status) : null;
        $this->detail = [
            'id' => $e->id,
            'name' => $p?->full_name ?? $e->employee_id ?? 'Unknown',
            'employee_id' => $e->employee_id,
            'email' => $p?->email,
            'department' => $p?->department,
            'job_title' => $p?->job_title,
            'class' => $e->classSession?->trainingClass?->name,
            'session_date' => $e->classSession?->session_date?->format('l, M j, Y'),
            'session_time' => $e->classSession?->start_time ? \Illuminate\Support\Carbon::parse($e->classSession->start_time)->format('g:i A') : null,
            'instructor' => $e->classSession?->instructorUser?->name ?? $e->classSession?->instructor,
            'location' => $e->classSession?->location,
            'status' => ucwords(str_replace('_', ' ', (string) $statusVal)),
            'status_color' => \App\Models\WorkflowStatus::colorFor('class', $statusVal, '#6A6A72'),
            'signed_up_at' => $e->signed_up_at?->format('M j, Y'),
            'attended_at' => $e->attended_at?->format('M j, Y'),
            'completed_at' => $e->completed_at?->format('M j, Y'),
            'qual_status' => $qStatusVal ? ucwords(str_replace('_', ' ', $qStatusVal)) : null,
            'qual_stage' => $q?->workflow_stage ? \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage->value, $q->workflow_stage->label()) : null,
            'qual_runs' => $q ? ((int) $q->runs_completed . ' / ' . (int) $q->runs_required) : null,
            'qual_due' => $q?->due_date?->format('M j, Y'),
            'class_on_file' => (bool) ($q?->class_on_file),
            'edit_url' => $e->personnel_id
                ? \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $e->personnel_id])
                : null,
        ];
    }

    public function closeDetail(): void { $this->detail = null; }

    // Manual add-enrollment (analyst books a person into a class session)
    public bool $showAdd = false;
    public ?int $addSessionId = null;
    public ?int $addPersonnelId = null;

    public function openSessions(): array
    {
        return \App\Models\ClassSession::with('trainingClass')
            ->where('status', 'open')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')->get()
            ->mapWithKeys(fn ($s) => [$s->id => ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->format('M j, Y')])
            ->all();
    }

    public function bookablePersonnel(): array
    {
        return \App\Models\Personnel::where('is_active', true)
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->full_name . ' (' . $p->employee_id . ')'])
            ->all();
    }

    public function addEnrollment(): void
    {
        if (! $this->addSessionId || ! $this->addPersonnelId) {
            \Filament\Notifications\Notification::make()->danger()->title('Pick a person and a session')->send();
            return;
        }
        $person = \App\Models\Personnel::find($this->addPersonnelId);
        ClassEnrollment::firstOrCreate(
            ['class_session_id' => $this->addSessionId, 'personnel_id' => $this->addPersonnelId],
            [
                'name' => $person?->full_name,
                'email' => $person?->email,
                'employee_id' => $person?->employee_id,
                'status' => 'signed_up',
                'signed_up_at' => now(),
            ]
        );
        $this->showAdd = false;
        $this->addPersonnelId = null;
        \Filament\Notifications\Notification::make()->success()->title('Enrollment added')->send();
    }

    public function moveCard(int $id, string $toStatus): void
    {
        // active lanes + the Archive (completed) lane
        if (! array_key_exists($toStatus, $this->lanes) && $toStatus !== 'historical') {
            return;
        }
        $e = ClassEnrollment::with('personnel')->find($id);
        if (! $e) {
            return;
        }
        // centralized: stamps who/when and advances the qualification consistently
        $e->markStatus($toStatus, \Illuminate\Support\Facades\Auth::id());
    }
}

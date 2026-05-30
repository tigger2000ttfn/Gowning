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
        'attended'  => ['label' => 'Attended',  'color' => '#C79A2E'],
        'no_show'   => ['label' => 'No-Show',    'color' => '#C8102E'],
    ];

    /** Completed enrollments, shown in a collapsed far-right Archive lane. */
    public function getArchive(): array
    {
        $cards = ClassEnrollment::with(['personnel', 'classSession.trainingClass'])
            ->where('status', 'completed')
            ->latest('updated_at')->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->personnel?->full_name ?? $e->employee_id ?? 'Unknown',
                'employee_id' => $e->employee_id,
                'class' => $e->classSession?->trainingClass?->name,
                'date' => $e->classSession?->session_date?->format('M j'),
            ])->values()->all();
        return [
            'label' => \App\Models\WorkflowStatus::labelFor('class', 'completed', 'Completed'),
            'color' => \App\Models\WorkflowStatus::colorFor('class', 'completed', '#2E7D5B'),
            'cards' => $cards,
        ];
    }

    public function getColumns(): array
    {
        $out = [];
        $byStatus = ClassEnrollment::with(['personnel', 'classSession.trainingClass'])
            ->get()->groupBy('status');
        foreach ($this->lanes as $key => $meta) {
            $cards = ($byStatus[$key] ?? collect())->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->personnel?->full_name ?? $e->employee_id ?? 'Unknown',
                'employee_id' => $e->employee_id,
                'class' => $e->classSession?->trainingClass?->name,
                'date' => $e->classSession?->session_date?->format('M j'),
            ])->values()->all();
            $out[$key] = [
                'label' => \App\Models\WorkflowStatus::labelFor('class', $key, $meta['label']),
                'color' => \App\Models\WorkflowStatus::colorFor('class', $key, $meta['color']),
                'cards' => $cards,
            ];
        }
        return $out;
    }

    /** Drag an enrollment to a new status. 'completed' advances the person's workflow stage. */
    public ?array $detail = null;

    public function showDetail(int $id): void
    {
        $e = ClassEnrollment::with(['personnel', 'classSession.trainingClass'])->find($id);
        if (! $e) { $this->detail = null; return; }
        $this->detail = [
            'id' => $e->id,
            'name' => $e->personnel?->full_name ?? $e->employee_id ?? 'Unknown',
            'employee_id' => $e->employee_id,
            'class' => $e->classSession?->trainingClass?->name,
            'session_date' => $e->classSession?->session_date?->format('l, M j, Y'),
            'status' => ucfirst(str_replace('_', ' ', (string) $e->status)),
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
        if (! array_key_exists($toStatus, $this->lanes) && $toStatus !== 'completed') {
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

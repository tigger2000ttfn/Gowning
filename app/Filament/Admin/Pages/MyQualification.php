<?php

namespace App\Filament\Admin\Pages;

use App\Models\ClassEnrollment;
use App\Models\Personnel;
use App\Models\Reservation;
use App\Models\RunSlot;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class MyQualification extends Page
{
    public function getHeading(): string { return ''; }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'My Qualification';
    protected static ?int $navigationSort = -10; // top of sidebar
    protected static ?string $title = 'My Qualification';

    protected string $view = 'filament.pages.my-qualification';

    public ?Personnel $person = null;

    public function mount(): void
    {
        // Find the personnel record linked to the logged-in user (by user_id or email).
        $user = Auth::user();
        $this->person = Personnel::where('user_id', $user?->id)
            ->orWhere('email', $user?->email)
            ->with(['qualification.comments.user', 'runs' => fn ($q) => $q->latest('run_date'), 'classCompletions'])
            ->first();
    }

    /** Whether this operator may self-request a run right now (class on file, no active booking). */
    public function canRequestRun(): bool
    {
        if (! $this->person) return false;
        if (! (bool) \App\Models\Setting::get('allow_self_request_run', true)) return false;
        if ($this->myActiveReservation() !== null) return false;
        // SOP prereq: the gowning class must be on file before a run can be requested.
        return (bool) $this->person->qualification?->class_on_file;
    }

    /** Self-serve: request the next available run day (or a specific open day). */
    public function requestRunAction(): Action
    {
        return Action::make('requestRun')
            ->label('Request A Run')
            ->icon('heroicon-m-plus-circle')
            ->color('primary')
            ->visible(fn () => $this->canRequestRun())
            ->modalHeading('Request A Qualification Run')
            ->modalDescription('Book yourself onto a cleanroom run day. Choose the next available day or pick a specific open day.')
            ->form([
                \Filament\Forms\Components\Radio::make('mode')
                    ->label('Choose')
                    ->options([
                        'next' => 'Next Available Run Day (Soonest With Space)',
                        'pick' => 'Pick A Specific Open Day',
                    ])->default('next')->live()->required(),
                Select::make('run_slot_id')
                    ->label('Pick A Day')
                    ->visible(fn ($get) => $get('mode') === 'pick')
                    ->required(fn ($get) => $get('mode') === 'pick')
                    ->options(function () {
                        $scheduler = app(\App\Services\AutoScheduler::class);
                        return RunSlot::query()
                            ->where('status', 'open')
                            ->whereDate('slot_date', '>=', now()->toDateString())
                            ->orderBy('slot_date')->get()
                            ->filter(fn ($s) => $scheduler->seatsLeft($s) > 0)
                            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->gmp() . ', ' . $s->cleanroom
                                . ($s->start_time ? ' (' . \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') . ')' : '')]);
                    }),
            ])
            ->action(function (array $data) {
                if (! $this->canRequestRun()) {
                    Notification::make()->warning()->title('Cannot Request A Run')
                        ->body('You may already be booked, or the gowning class is not yet on file.')->send();
                    return;
                }
                $scheduler = app(\App\Services\AutoScheduler::class);
                if (($data['mode'] ?? 'next') === 'next') {
                    $slot = $scheduler->nextAvailableSlot();
                } else {
                    $slot = RunSlot::find($data['run_slot_id'] ?? null);
                    if ($slot && $scheduler->seatsLeft($slot) <= 0) $slot = null;
                }
                if (! $slot) {
                    Notification::make()->warning()->title('No Open Day Available')
                        ->body('No run day with space was found. Please try again later or contact scheduling.')->send();
                    return;
                }
                Reservation::create([
                    'run_slot_id' => $slot->id,
                    'personnel_id' => $this->person->id,
                    'status' => 'approved',
                    'requested_at' => now(),
                    'decided_at' => now(),
                    'notes' => 'Self-requested',
                ]);
                // advance the card to Run Scheduled so the booking shows on the boards and Active Runs
                $q = \App\Models\Qualification::currentFor($this->person->id);
                if ($q) {
                    \App\Services\AutoScheduler::markScheduled($q);
                }
                Notification::make()->success()->title('Run Requested')
                    ->body('You are booked for ' . $slot->slot_date->gmpL() . '.')->send();
            });
    }

    /** Operator self-reschedule: move their own reservation to another open slot with capacity. */
    public function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule My Run')
            ->icon('heroicon-m-arrows-right-left')
            ->color('primary')
            ->visible(fn () => $this->myActiveReservation() !== null && (bool) \App\Models\Setting::get('allow_self_reschedule', true))
            ->modalHeading('Reschedule My Qualification Run')
            ->modalDescription('Move to the next available day, or pick a specific open day.')
            ->form([
                \Filament\Forms\Components\Radio::make('mode')
                    ->label('Choose')
                    ->options([
                        'next' => 'Next available run day (soonest with space)',
                        'pick' => 'Pick a specific open day',
                    ])->default('next')->live()->required(),
                Select::make('run_slot_id')
                    ->label('Pick A New Day')
                    ->visible(fn ($get) => $get('mode') === 'pick')
                    ->required(fn ($get) => $get('mode') === 'pick')
                    ->options(function () {
                        $scheduler = app(\App\Services\AutoScheduler::class);
                        return RunSlot::query()
                            ->where('status', 'open')
                            ->whereDate('slot_date', '>=', now()->toDateString())
                            ->orderBy('slot_date')->get()
                            ->filter(fn ($s) => $scheduler->seatsLeft($s) > 0)
                            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->gmp() . ', ' . $s->cleanroom
                                . ($s->start_time ? ' (' . \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') . ')' : '')]);
                    }),
            ])
            ->action(function (array $data) {
                $res = $this->myActiveReservation();
                if (! $res) {
                    Notification::make()->danger()->title('No active reservation to reschedule')->send();
                    return;
                }
                $scheduler = app(\App\Services\AutoScheduler::class);

                if (($data['mode'] ?? 'next') === 'next') {
                    $slot = $scheduler->nextAvailableSlot();
                    if (! $slot) {
                        Notification::make()->warning()->title('No open day available')
                            ->body('No run day with space was found. Please try again later or contact scheduling.')->send();
                        return;
                    }
                    $res->update(['run_slot_id' => $slot->id, 'status' => 'approved', 'notes' => 'Self-rescheduled']);
                    Notification::make()->success()->title('Run rescheduled')
                        ->body('You are now booked for ' . $slot->slot_date->gmp() . '.')->send();
                    return;
                }

                // specific pick: verify capacity still available
                $slot = RunSlot::find($data['run_slot_id'] ?? null);
                if (! $slot || $scheduler->seatsLeft($slot) <= 0) {
                    Notification::make()->warning()->title('That day just filled up')
                        ->body('Please pick another day or choose next available.')->send();
                    return;
                }
                $res->update(['run_slot_id' => $slot->id, 'status' => 'approved', 'notes' => 'Self-rescheduled']);
                Notification::make()->success()->title('Run rescheduled')
                    ->body('You are now booked for ' . $slot->slot_date->gmp() . '.')->send();
            });
    }

    protected function myActiveReservation(): ?Reservation
    {
        if (! $this->person) {
            return null;
        }
        return Reservation::where('personnel_id', $this->person->id)
            ->whereIn('status', ['requested', 'approved'])
            ->latest()
            ->first();
    }

    public function getViewData(): array
    {
        $upcoming = ClassEnrollment::query()
            ->with('classSession.trainingClass')
            ->where(function ($q) {
                $q->where('personnel_id', $this->person?->id)
                  ->orWhere('email', Auth::user()?->email);
            })
            ->whereHas('classSession', fn ($q) => $q->whereDate('session_date', '>=', now()->toDateString()))
            ->get();

        return [
            'person' => $this->person,
            'qualification' => $this->person?->qualification,
            'runs' => $this->person?->runs ?? collect(),
            'classes' => $this->person?->classCompletions ?? collect(),
            'enrollments' => $upcoming,
        ];
    }
}

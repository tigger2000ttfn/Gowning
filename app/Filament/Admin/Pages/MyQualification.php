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
            ->modalIcon('heroicon-o-calendar-days')
            ->modalIconColor('primary')
            ->modalWidth('lg')
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
                            ->where('is_special', false)
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

    /** Whether this operator should book a class (no class on file, no active class enrollment). */
    public function canBookClass(): bool
    {
        if (! $this->person) return false;
        if (! (bool) \App\Models\Setting::get('allow_self_book_class', true)) return false;
        if ((bool) $this->person->qualification?->class_on_file) return false; // already has class on file
        $hasActive = \App\Models\ClassEnrollment::where('personnel_id', $this->person->id)
            ->whereIn('status', \App\Models\ClassEnrollment::ACTIVE_STATUSES)->exists();
        return ! $hasActive;
    }

    /** Self-serve: sign up for an open gowning class session. */
    public function bookClassAction(): Action
    {
        return Action::make('bookClass')
            ->label('Book A Class')
            ->icon('heroicon-m-academic-cap')
            ->color('gray')
            ->visible(fn () => $this->canBookClass())
            ->modalIcon('heroicon-o-academic-cap')
            ->modalIconColor('warning')
            ->modalWidth('lg')
            ->modalHeading('Book A Gowning Class')
            ->modalDescription('Sign up for an upcoming gowning class. The gowning class must be completed before a qualification run.')
            ->form([
                Select::make('class_session_id')
                    ->label('Class Session')
                    ->required()
                    ->options(function () {
                        return \App\Models\ClassSession::with('trainingClass')
                            ->where('status', 'open')
                            ->whereDate('session_date', '>=', now()->toDateString())
                            ->orderBy('session_date')->get()
                            ->filter(fn ($s) => $s->seatsLeft() > 0)
                            ->mapWithKeys(fn ($s) => [$s->id => ($s->trainingClass?->name ?: 'Gowning Class') . ' - ' . $s->session_date->gmp()
                                . ($s->start_time ? ' (' . \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') . ')' : '')
                                . ($s->location ? ' - ' . $s->location : '')]);
                    })
                    ->helperText('Only open, upcoming sessions with space are shown.'),
            ])
            ->action(function (array $data) {
                if (! $this->canBookClass()) {
                    Notification::make()->warning()->title('Cannot Book A Class')
                        ->body('You may already be enrolled or already have a class on file.')->send();
                    return;
                }
                $session = \App\Models\ClassSession::find($data['class_session_id'] ?? null);
                if (! $session || $session->seatsLeft() <= 0) {
                    Notification::make()->warning()->title('Class Not Available')
                        ->body('That session is full or no longer open. Please pick another.')->send();
                    return;
                }
                \App\Models\ClassEnrollment::create([
                    'class_session_id' => $session->id,
                    'personnel_id' => $this->person->id,
                    'name' => $this->person->full_name,
                    'email' => $this->person->email,
                    'employee_id' => $this->person->employee_id,
                    'status' => 'signed_up',
                    'signed_up_at' => now(),
                ]);
                Notification::make()->success()->title('Class Booked')
                    ->body('You are signed up for ' . $session->session_date->gmpL() . '.')->send();
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
                            ->where('is_special', false)
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

    /** Cancel my own upcoming run booking (self-service). */
    public function cancelMyRun(int $reservationId): void
    {
        $res = Reservation::where('id', $reservationId)
            ->where('personnel_id', $this->person?->id)
            ->whereIn('status', ['requested', 'approved'])
            ->first();
        if (! $res) { Notification::make()->warning()->title('Booking Not Found')->send(); return; }
        $res->update(['status' => 'cancelled', 'notes' => trim(($res->notes ? $res->notes . ' · ' : '') . 'Cancelled by operator')]);
        Notification::make()->success()->title('Run Booking Cancelled')
            ->body('You can request a new run day whenever you are ready.')->send();
    }

    /** Cancel my own upcoming class enrollment (self-service). */
    public function cancelMyClass(int $enrollmentId): void
    {
        $e = ClassEnrollment::where('id', $enrollmentId)
            ->where(fn ($q) => $q->where('personnel_id', $this->person?->id)->orWhere('email', Auth::user()?->email))
            ->whereIn('status', ['signed_up', 'attended'])
            ->first();
        if (! $e) { Notification::make()->warning()->title('Enrollment Not Found')->send(); return; }
        $e->markStatus('cancelled', Auth::id());
        Notification::make()->success()->title('Class Cancelled')
            ->body('You can rebook a class whenever you are ready.')->send();
    }

    /** Dismiss a cancelled enrollment from my view (soft acknowledge - hides the alert). */
    public function dismissEnrollment(int $enrollmentId): void
    {
        $e = ClassEnrollment::where('id', $enrollmentId)
            ->where(fn ($q) => $q->where('personnel_id', $this->person?->id)->orWhere('email', Auth::user()?->email))
            ->first();
        if (! $e) return;
        $e->update(['acknowledged_at' => now()]);
        Notification::make()->success()->title('Dismissed')->body('Removed from your view.')->send();
    }

    /** Acknowledge a booking that was made/changed for me by an admin or the system. */
    public function acknowledgeReservation(int $reservationId): void
    {
        $res = Reservation::where('id', $reservationId)
            ->where('personnel_id', $this->person?->id)->first();
        if (! $res) return;
        $res->update(['acknowledged_at' => now()]);
        Notification::make()->success()->title('Acknowledged')
            ->body('Thanks - this booking is confirmed on your end.')->send();
    }

    public function getViewData(): array
    {
        // Upcoming class enrollments (active + recently cancelled that the operator has not yet dismissed).
        $enrollments = ClassEnrollment::query()
            ->with('classSession.trainingClass')
            ->where(function ($q) {
                $q->where('personnel_id', $this->person?->id)
                  ->orWhere('email', Auth::user()?->email);
            })
            ->where(function ($q) {
                $q->whereHas('classSession', fn ($s) => $s->whereDate('session_date', '>=', now()->subDays(1)->toDateString()))
                  ->orWhere(fn ($x) => $x->where('status', 'cancelled')->whereNull('acknowledged_at'));
            })
            ->get()
            ->sortBy(fn ($e) => $e->classSession?->session_date?->toDateString() ?? '9999')
            ->values();

        // Upcoming run bookings (active) + any unacknowledged bookings made for the operator.
        $bookings = Reservation::query()
            ->with('runSlot')
            ->where('personnel_id', $this->person?->id)
            ->whereIn('status', ['requested', 'approved'])
            ->whereHas('runSlot', fn ($s) => $s->whereDate('slot_date', '>=', now()->subDays(1)->toDateString()))
            ->get()
            ->sortBy(fn ($r) => $r->runSlot?->slot_date?->toDateString() ?? '9999')
            ->values();

        return [
            'person' => $this->person,
            'qualification' => $this->person?->qualification,
            'runs' => $this->person?->runs ?? collect(),
            'classes' => $this->person?->classCompletions ?? collect(),
            'enrollments' => $enrollments,
            'bookings' => $bookings,
        ];
    }
}

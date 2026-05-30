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

    /** Operator self-reschedule: move their own reservation to another open slot with capacity. */
    public function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule My Run')
            ->icon('heroicon-m-arrows-right-left')
            ->color('primary')
            ->visible(fn () => $this->myActiveReservation() !== null)
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
                            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->format('M j, Y') . ', ' . $s->cleanroom
                                . ($s->start_time ? ' (' . \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') . ')' : '')]);
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
                        ->body('You are now booked for ' . $slot->slot_date->format('M j, Y') . '.')->send();
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
                    ->body('You are now booked for ' . $slot->slot_date->format('M j, Y') . '.')->send();
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

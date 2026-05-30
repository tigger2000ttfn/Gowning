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

    /** Operator self-reschedule: move their own pending/approved reservation to another open slot. */
    public function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule My Run')
            ->icon('heroicon-m-arrows-right-left')
            ->color('primary')
            ->visible(fn () => $this->myActiveReservation() !== null)
            ->form([
                Select::make('run_slot_id')
                    ->label('Pick A New Slot')
                    ->required()
                    ->options(fn () => RunSlot::query()
                        ->whereDate('slot_date', '>=', now()->toDateString())
                        ->orderBy('slot_date')
                        ->get()
                        ->filter(fn ($s) => $s->hasCapacity())
                        ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->format('M j, Y') . ' — ' . $s->cleanroom . ' (' . $s->start_time . ')'])),
            ])
            ->action(function (array $data) {
                $res = $this->myActiveReservation();
                if (! $res) {
                    Notification::make()->danger()->title('No active reservation to reschedule')->send();
                    return;
                }
                $res->update(['run_slot_id' => $data['run_slot_id'], 'status' => 'requested']);
                Notification::make()->success()->title('Run rescheduled')
                    ->body('Your request has been moved and is pending approval.')->send();
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

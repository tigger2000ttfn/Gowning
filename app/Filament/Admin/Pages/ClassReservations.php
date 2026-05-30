<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\ClassSession;
use App\Models\ClassEnrollment;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ClassReservations extends Page
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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Class Reservations';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 11;
    protected static ?string $title = 'Gowning Class Reservations';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.class-reservations';

    /** Enrollments grouped by class session, for the per-session list view. */
    public function getGroupedBySession(): array
    {
        return ClassSession::with(['trainingClass', 'enrollments.personnel'])
            ->orderBy('session_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->format('l, M j, Y')
                    . ($s->start_time ? ' · ' . \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') : ''),
                'location' => $s->location,
                'seats' => $s->seatsLeft(),
                'capacity' => $s->capacity,
                'rows' => $s->enrollments->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->personnel?->full_name ?? $e->name ?? 'Unknown',
                    'employee_id' => $e->employee_id,
                    'status' => $e->status,
                ])->values()->all(),
            ])->values()->all();
    }

    public function setStatus(int $id, string $status): void
    {
        $e = ClassEnrollment::find($id);
        if (! $e) return;
        if (! in_array($status, ['signed_up', 'attended', 'completed', 'no_show'], true)) return;
        $e->status = $status;
        $e->save();
        Notification::make()->success()->title('Enrollment updated')->send();
    }
}

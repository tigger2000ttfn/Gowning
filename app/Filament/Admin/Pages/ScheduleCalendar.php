<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\RunSlot;
use App\Models\ClassSession;
use App\Models\Qualification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ScheduleCalendar extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling) || $u->hasCapability(Capability::ViewReports)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Schedule Calendar';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Schedule Calendar';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.schedule-calendar';

    /** FullCalendar-shaped events: run days, class sessions, due dates. */
    public function events(): array
    {
        $events = [];

        foreach (RunSlot::with('analyst')->where('status', '!=', 'cancelled')->get() as $s) {
            $events[] = [
                'title' => 'Run: ' . $s->cleanroom . ($s->analyst ? ' (' . $s->analyst->name . ')' : ''),
                'start' => $s->slot_date->toDateString() . ($s->start_time ? 'T' . $s->start_time : ''),
                'end' => $s->end_time ? $s->slot_date->toDateString() . 'T' . $s->end_time : null,
                'color' => '#A4123F',
            ];
        }

        foreach (ClassSession::with('trainingClass')->where('status', '!=', 'cancelled')->get() as $s) {
            $events[] = [
                'title' => 'Class: ' . ($s->trainingClass?->name ?? 'Gowning'),
                'start' => $s->session_date->toDateString() . ($s->start_time ? 'T' . $s->start_time : ''),
                'end' => $s->end_time ? $s->session_date->toDateString() . 'T' . $s->end_time : null,
                'color' => '#2E7D5B',
            ];
        }

        foreach (Qualification::with('personnel')->where('status', 'qualified')->whereNotNull('due_date')->get() as $q) {
            $events[] = [
                'title' => 'Due: ' . ($q->personnel?->full_name ?? 'Unknown'),
                'start' => $q->due_date->toDateString(),
                'allDay' => true,
                'color' => '#C79A2E',
            ];
        }

        return $events;
    }
}

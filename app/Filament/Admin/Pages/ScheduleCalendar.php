<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\RunSlot;
use App\Models\ClassSession;
use App\Models\Qualification;
use Carbon\CarbonImmutable;
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

    public int $year;
    public int $month;

    // toggles
    public bool $showRuns = true;
    public bool $showClasses = true;
    public bool $showDue = true;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    public function prevMonth(): void
    {
        $d = CarbonImmutable::create($this->year, $this->month, 1)->subMonth();
        $this->year = $d->year; $this->month = $d->month;
    }

    public function nextMonth(): void
    {
        $d = CarbonImmutable::create($this->year, $this->month, 1)->addMonth();
        $this->year = $d->year; $this->month = $d->month;
    }

    public function today(): void
    {
        $this->year = (int) now()->year; $this->month = (int) now()->month;
    }

    public function monthLabel(): string
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->format('F Y');
    }

    /** Build the calendar grid: array of weeks, each 7 day-cells with events. */
    public function getGrid(): array
    {
        $first = CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
        $last = $first->endOfMonth();
        $start = $first->startOfWeek(CarbonImmutable::SUNDAY);
        $end = $last->endOfWeek(CarbonImmutable::SATURDAY);

        // gather events for the visible range
        $events = [];

        if ($this->showRuns) {
            foreach (RunSlot::with('analyst')->whereBetween('slot_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', '!=', 'cancelled')->get() as $s) {
                $events[$s->slot_date->format('Y-m-d')][] = [
                    'type' => 'run', 'color' => '#A4123F',
                    'label' => 'Run: ' . $s->cleanroom . ($s->analyst ? ' (' . $s->analyst->name . ')' : ''),
                ];
            }
        }
        if ($this->showClasses) {
            foreach (ClassSession::with('trainingClass')->whereBetween('session_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', '!=', 'cancelled')->get() as $s) {
                $events[$s->session_date->format('Y-m-d')][] = [
                    'type' => 'class', 'color' => '#2E7D5B',
                    'label' => 'Class: ' . ($s->trainingClass?->name ?? 'Gowning'),
                ];
            }
        }
        if ($this->showDue) {
            foreach (Qualification::with('personnel')->where('status', 'qualified')
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])->get() as $q) {
                $events[$q->due_date->format('Y-m-d')][] = [
                    'type' => 'due', 'color' => '#C79A2E',
                    'label' => 'Due: ' . ($q->personnel?->full_name ?? 'Unknown'),
                ];
            }
        }

        $weeks = [];
        $cursor = $start;
        while ($cursor->lessThanOrEqualTo($end)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $cursor,
                    'day' => $cursor->day,
                    'in_month' => $cursor->month === $this->month,
                    'is_today' => $cursor->isToday(),
                    'events' => $events[$key] ?? [],
                ];
                $cursor = $cursor->addDay();
            }
            $weeks[] = $week;
        }
        return $weeks;
    }
}

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
    protected static string|\UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Schedule Calendar';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.schedule-calendar';

    public string $tab = 'calendar';   // calendar | list

    public ?string $calMonth = null;   // YYYY-MM anchor for the month grid

    public function mount(): void
    {
        $this->calMonth = $this->calMonth ?: now()->format('Y-m');
    }

    protected function anchor(): \Illuminate\Support\Carbon
    {
        try { return \Illuminate\Support\Carbon::createFromFormat('Y-m', $this->calMonth)->startOfMonth(); }
        catch (\Throwable $e) { return now()->startOfMonth(); }
    }

    public function prevMonth(): void { $this->calMonth = $this->anchor()->subMonth()->format('Y-m'); }
    public function nextMonth(): void { $this->calMonth = $this->anchor()->addMonth()->format('Y-m'); }
    public function thisMonth(): void { $this->calMonth = now()->format('Y-m'); }

    public function monthLabel(): string { return $this->anchor()->gmpMY(); }

    /** Events bucketed by Y-m-d for the visible month, each: type,color,title,time. */
    protected function eventsByDate(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to): array
    {
        $b = [];
        $push = function ($date, $row) use (&$b) {
            $k = $date->toDateString();
            $b[$k] ??= [];
            $b[$k][] = $row;
        };
        foreach (RunSlot::with('analyst')->where('status', '!=', 'cancelled')
            ->whereBetween('slot_date', [$from->toDateString(), $to->toDateString()])->get() as $s) {
            $push($s->slot_date, ['color' => '#A4123F', 'title' => 'Run: ' . ($s->cleanroom ?: 'Run Day'),
                'time' => $s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') : '']);
        }
        foreach (ClassSession::with('trainingClass')->where('status', '!=', 'cancelled')
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])->get() as $s) {
            $push($s->session_date, ['color' => '#2E7D5B', 'title' => 'Class: ' . ($s->trainingClass?->name ?? 'Gowning'),
                'time' => $s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') : '']);
        }
        foreach (Qualification::with('personnel')->where('status', 'qualified')->whereNotNull('due_date')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->get() as $q) {
            $push($q->due_date, ['color' => '#C79A2E', 'title' => 'Due: ' . ($q->personnel?->full_name ?? 'Unknown'), 'time' => '']);
        }
        return $b;
    }

    /** A 6-week grid (Sun-start) of days for the visible month. */
    public function monthGrid(): array
    {
        $start = $this->anchor();
        $gridStart = $start->copy()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
        $gridEnd = $start->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);
        $events = $this->eventsByDate($gridStart, $gridEnd);
        $today = now()->toDateString();
        $weeks = [];
        $cur = $gridStart->copy();
        while ($cur <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $k = $cur->toDateString();
                $week[] = [
                    'day' => $cur->day,
                    'date' => $k,
                    'inMonth' => $cur->month === $start->month,
                    'isToday' => $k === $today,
                    'events' => $events[$k] ?? [],
                ];
                $cur->addDay();
            }
            $weeks[] = $week;
        }
        return $weeks;
    }

    /** Upcoming schedule as a flat, date-sorted list (run days + class sessions). */
    public function listItems(): array
    {
        $items = [];
        foreach (RunSlot::with('analyst')->where('status', '!=', 'cancelled')
            ->whereDate('slot_date', '>=', now()->subDay()->toDateString())->get() as $s) {
            $items[] = [
                'type' => 'Run Day', 'color' => '#A4123F',
                'date' => $s->slot_date, 'sort' => $s->slot_date->toDateString() . ($s->start_time ?? '00:00'),
                'title' => $s->cleanroom ?: 'Run Day',
                'sub' => ($s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') : 'All day')
                    . ($s->analyst ? ' · ' . $s->analyst->name : ''),
            ];
        }
        foreach (ClassSession::with('trainingClass')->where('status', '!=', 'cancelled')
            ->whereDate('session_date', '>=', now()->subDay()->toDateString())->get() as $s) {
            $items[] = [
                'type' => 'Class', 'color' => '#2E7D5B',
                'date' => $s->session_date, 'sort' => $s->session_date->toDateString() . ($s->start_time ?? '00:00'),
                'title' => $s->trainingClass?->name ?? 'Gowning Class',
                'sub' => $s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') : 'All day',
            ];
        }
        usort($items, fn ($a, $b) => strcmp($a['sort'], $b['sort']));
        return $items;
    }

    /** FullCalendar-shaped events: run days, class sessions, due dates. */
    public function events(): array
    {
        $events = [];
        $canEdit = (bool) Auth::user()?->hasCapability(Capability::ManageScheduling);

        foreach (RunSlot::with('analyst')->where('status', '!=', 'cancelled')->get() as $s) {
            $events[] = [
                'id' => 'slot:' . $s->id,
                'title' => 'Run: ' . $s->cleanroom . ($s->analyst ? ' (' . $s->analyst->name . ')' : ''),
                'start' => $s->slot_date->toDateString() . ($s->start_time ? 'T' . $s->start_time : ''),
                'end' => $s->end_time ? $s->slot_date->toDateString() . 'T' . $s->end_time : null,
                'color' => '#A4123F',
                'editable' => $canEdit,
            ];
        }

        foreach (ClassSession::with('trainingClass')->where('status', '!=', 'cancelled')->get() as $s) {
            $events[] = [
                'id' => 'class:' . $s->id,
                'title' => 'Class: ' . ($s->trainingClass?->name ?? 'Gowning'),
                'start' => $s->session_date->toDateString() . ($s->start_time ? 'T' . $s->start_time : ''),
                'end' => $s->end_time ? $s->session_date->toDateString() . 'T' . $s->end_time : null,
                'color' => '#2E7D5B',
                'editable' => $canEdit,
            ];
        }

        foreach (Qualification::with('personnel')->where('status', 'qualified')->whereNotNull('due_date')->get() as $q) {
            $events[] = [
                'id' => 'due:' . $q->id,
                'title' => 'Due: ' . ($q->personnel?->full_name ?? 'Unknown'),
                'start' => $q->due_date->toDateString(),
                'allDay' => true,
                'color' => '#C79A2E',
                'editable' => false, // due dates are computed, not draggable
            ];
        }

        return $events;
    }

    public function canDrag(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::ManageScheduling);
    }

    /** Drag-to-reschedule handler: move a run day or class session to a new date. */
    public function moveEvent(string $eventId, string $newDate): void
    {
        if (! $this->canDrag()) {
            \Filament\Notifications\Notification::make()->danger()->title('Not authorized')
                ->body('You need scheduling permission to reschedule.')->send();
            return;
        }

        [$type, $id] = array_pad(explode(':', $eventId, 2), 2, null);
        $date = \Illuminate\Support\Carbon::parse($newDate)->toDateString();

        if ($type === 'slot') {
            $slot = RunSlot::with('reservations.personnel')->find((int) $id);
            if (! $slot) { return; }
            $old = $slot->slot_date?->toDateString();
            $slot->slot_date = $date;
            $slot->save();

            // notify booked operators of the move
            $notifier = app(\App\Services\Notifier::class);
            foreach ($slot->reservations as $res) {
                if (in_array($res->status, ['approved', 'requested'], true) && $res->personnel) {
                    $notifier->toPersonnel(
                        $res->personnel,
                        'Run day rescheduled',
                        'Your qualification run was moved from ' . $old . ' to ' . $date . '.',
                        \App\Enums\NotificationEvent::RunScheduled
                    );
                }
            }
            \Filament\Notifications\Notification::make()->success()->title('Run day moved')
                ->body('Moved to ' . $date . '. Booked operators were notified.')->send();
        } elseif ($type === 'class') {
            $session = ClassSession::find((int) $id);
            if (! $session) { return; }
            $session->session_date = $date;
            $session->save();
            \Filament\Notifications\Notification::make()->success()->title('Class session moved')
                ->body('Moved to ' . $date . '.')->send();
        }
    }
}

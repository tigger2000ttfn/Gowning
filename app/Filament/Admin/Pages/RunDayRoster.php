<?php

namespace App\Filament\Admin\Pages;

use App\Models\RunSlot;
use Filament\Pages\Page;

class RunDayRoster extends Page
{
    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $r = \Illuminate\Support\Facades\Auth::user()?->role;
        return $r && $r->canManageScheduling();
    }
    public static function shouldRegisterNavigation(): bool
    {
        $r = \Illuminate\Support\Facades\Auth::user()?->role;
        return (bool) ($r && $r->canManageScheduling());
    }
    public static function canViewAny(): bool
    {
        $r = \Illuminate\Support\Facades\Auth::user()?->role;
        return (bool) ($r && $r->canManageScheduling());
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Qual Run Day';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Qualification Run Day Roster';

    protected string $view = 'filament.pages.run-day-roster';

    public ?string $date = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function getSlotsProperty()
    {
        return RunSlot::with(['reservations' => function ($q) {
                $q->whereIn('status', ['approved', 'completed'])->with('personnel');
            }])
            ->whereDate('slot_date', $this->date ?: now()->toDateString())
            ->orderBy('start_time')
            ->get();
    }
}

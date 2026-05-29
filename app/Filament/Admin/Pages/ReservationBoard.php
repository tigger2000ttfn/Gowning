<?php

namespace App\Filament\Admin\Pages;

use App\Models\Reservation;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ReservationBoard extends Page
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function shouldRegisterNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationLabel = 'Reservation Board';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Reservation Board';

    protected string $view = 'filament.pages.reservation-board';

    public array $lanes = [
        'requested' => 'Requested',
        'approved'  => 'Approved',
        'completed' => 'Completed',
        'no_show'   => 'No-Show',
    ];

    public function getColumns(): array
    {
        $out = [];
        foreach ($this->lanes as $key => $label) {
            $out[$key] = [
                'label' => $label,
                'cards' => Reservation::with(['personnel', 'runSlot'])
                    ->where('status', $key)
                    ->latest('updated_at')
                    ->get()
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'name' => $r->personnel?->full_name ?? 'Unknown',
                        'employee_id' => $r->personnel?->employee_id,
                        'slot' => $r->runSlot?->cleanroom,
                        'date' => $r->runSlot?->slot_date?->format('M j, Y'),
                    ])->all(),
            ];
        }
        return $out;
    }

    /** Called by drag-drop to move a card to a new lane (status). */
    public function moveCard(int $id, string $toStatus): void
    {
        if (! array_key_exists($toStatus, $this->lanes)) {
            return;
        }
        $r = Reservation::find($id);
        if (! $r) {
            return;
        }
        $r->status = $toStatus;
        if (in_array($toStatus, ['approved', 'completed', 'no_show'])) {
            $r->decided_by = Auth::id();
            $r->decided_at = now();
        }
        $r->save();
    }
}

<?php

namespace App\Filament\Admin\Resources;

use App\Enums\ReservationStatus;
use App\Filament\Admin\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use App\Models\RunSlot;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class ReservationResource extends Resource
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    protected static ?string $model = Reservation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reservation Request')->icon('heroicon-o-ticket')->columns(2)->schema([
                Select::make('personnel_id')->label('Person')
                    ->relationship('personnel', 'employee_id')
                    ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->employee_id} · {$r->full_name}")
                    ->searchable()->preload()->required(),
                Select::make('run_slot_id')->label('Open Slot')
                    ->options(function () {
                        return RunSlot::query()
                            ->where('status', 'open')
                            ->whereDate('slot_date', '>=', now()->toDateString())
                            ->orderBy('slot_date')
                            ->get()
                            ->filter(fn ($s) => $s->hasCapacity())
                            ->mapWithKeys(fn ($s) => [
                                $s->id => "{$s->slot_date->gmp()} · {$s->cleanroom} ({$s->approvedCount()}/{$s->capacity} filled)",
                            ]);
                    })
                    ->searchable()->required()
                    ->helperText('Only open, future slots with remaining capacity are listed.'),
                Textarea::make('notes')->columnSpanFull()
                    ->placeholder('Optional note for QC Micro'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('personnel.employee_id')->label('Employee ID')->searchable(),
                TextColumn::make('personnel.full_name')->label('Name')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('runSlot.slot_date')->label('Slot Date')->date()->sortable(),
                TextColumn::make('runSlot.cleanroom')->label('Cleanroom'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => match ($s) {
                        ReservationStatus::Approved, ReservationStatus::Completed => 'success',
                        ReservationStatus::Rejected, ReservationStatus::NoShow => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('decidedBy.name')->label('Decided By')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(ReservationStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
            ])
            ->recordActions([
                Action::make('approve')->icon('heroicon-m-check')->color('success')
                    ->visible(fn (Reservation $r) => $r->status === ReservationStatus::Requested)
                    ->requiresConfirmation()
                    ->action(function (Reservation $r) {
                        // capacity guard at approval time
                        if (! $r->runSlot?->hasCapacity()) {
                            Notification::make()->danger()->title('Slot is full')
                                ->body('This slot has no remaining capacity.')->send();
                            return;
                        }
                        $r->update([
                            'status' => ReservationStatus::Approved,
                            'decided_by' => Auth::id(), 'decided_at' => now(),
                        ]);
                        Notification::make()->success()->title('Reservation approved')->send();
                    }),
                Action::make('reject')->icon('heroicon-m-x-mark')->color('danger')
                    ->visible(fn (Reservation $r) => $r->status === ReservationStatus::Requested)
                    ->requiresConfirmation()
                    ->action(function (Reservation $r) {
                        $r->update([
                            'status' => ReservationStatus::Rejected,
                            'decided_by' => Auth::id(), 'decided_at' => now(),
                        ]);
                        Notification::make()->title('Reservation rejected')->send();
                    }),
                Action::make('complete')->icon('heroicon-m-check-badge')->color('success')
                    ->label('Mark Completed')
                    ->visible(fn (Reservation $r) => $r->status === ReservationStatus::Approved)
                    ->action(fn (Reservation $r) => $r->update(['status' => ReservationStatus::Completed])),
                Action::make('no_show')->icon('heroicon-m-user-minus')->color('danger')
                    ->label('No-show')
                    ->visible(fn (Reservation $r) => $r->status === ReservationStatus::Approved)
                    ->requiresConfirmation()
                    ->action(fn (Reservation $r) => app(\App\Services\AutoScheduler::class)->handleNoShow($r)),
                Action::make('reschedule')->icon('heroicon-m-arrows-right-left')->color('warning')
                    ->label('Reschedule')
                    ->visible(fn (Reservation $r) => in_array($r->status, [ReservationStatus::Requested, ReservationStatus::Approved]))
                    ->modalHeading('Move To Another Run Slot')
                    ->schema([
                        Select::make('run_slot_id')->label('New Open Slot')
                            ->options(function () {
                                return RunSlot::query()
                                    ->where('status', 'open')
                                    ->whereDate('slot_date', '>=', now()->toDateString())
                                    ->orderBy('slot_date')->get()
                                    ->filter(fn ($s) => $s->hasCapacity())
                                    ->mapWithKeys(fn ($s) => [$s->id => "{$s->slot_date->gmp()} · {$s->cleanroom} ({$s->approvedCount()}/{$s->capacity})"]);
                            })->searchable()->required(),
                    ])
                    ->action(function (Reservation $r, array $data) {
                        $r->update(['run_slot_id' => $data['run_slot_id']]);
                        Notification::make()->success()->title('Reservation rescheduled')->send();
                    }),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
        ];
    }
}

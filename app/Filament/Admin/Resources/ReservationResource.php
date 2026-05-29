<?php

namespace App\Filament\Admin\Resources;

use App\Enums\ReservationStatus;
use App\Filament\Admin\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Reservation')->columns(2)->schema([
                Select::make('personnel_id')->label('Person')
                    ->relationship('personnel', 'employee_id')
                    ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->employee_id} — {$r->full_name}")
                    ->searchable()->preload()->required(),
                Select::make('run_slot_id')->label('Slot')
                    ->relationship('runSlot', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->slot_date?->format('Y-m-d')} — {$r->cleanroom}")
                    ->searchable()->preload()->required(),
                Select::make('status')->options(
                    collect(ReservationStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                )->default('requested')->required(),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('personnel.employee_id')->label('Employee ID')->searchable(),
                TextColumn::make('personnel.full_name')->label('Name')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('runSlot.slot_date')->label('Slot date')->date()->sortable(),
                TextColumn::make('runSlot.cleanroom')->label('Cleanroom'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => match ($s) {
                        ReservationStatus::Approved, ReservationStatus::Completed => 'success',
                        ReservationStatus::Rejected, ReservationStatus::NoShow => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(ReservationStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
            ])
            ->actions([
                Action::make('approve')->icon('heroicon-m-check')->color('success')
                    ->visible(fn (Reservation $r) => $r->status === ReservationStatus::Requested)
                    ->action(fn (Reservation $r) => $r->update([
                        'status' => ReservationStatus::Approved,
                        'decided_by' => Auth::id(), 'decided_at' => now(),
                    ])),
                Action::make('reject')->icon('heroicon-m-x-mark')->color('danger')
                    ->visible(fn (Reservation $r) => $r->status === ReservationStatus::Requested)
                    ->requiresConfirmation()
                    ->action(fn (Reservation $r) => $r->update([
                        'status' => ReservationStatus::Rejected,
                        'decided_by' => Auth::id(), 'decided_at' => now(),
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
        ];
    }
}

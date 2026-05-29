<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Models\RunSlot;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RunSlotResource extends Resource
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
    protected static ?string $model = RunSlot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Run Slot';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Weekly Run Slot')->icon('heroicon-o-calendar-days')->columns(2)->schema([
                TextInput::make('cleanroom')->required(),
                Select::make('status')->options(['open' => 'Open', 'closed' => 'Closed'])->default('open')->required(),
                DatePicker::make('slot_date')->label('Date')->required(),
                TextInput::make('capacity')->numeric()->minValue(1)->default(1)->required(),
                TimePicker::make('start_time'),
                TimePicker::make('end_time'),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot_date')->icon('heroicon-m-calendar')->date()->sortable(),
                TextColumn::make('cleanroom')->icon('heroicon-m-home-modern')->searchable(),
                TextColumn::make('start_time')->time('H:i')->placeholder('—'),
                TextColumn::make('capacity'),
                TextColumn::make('reservations_count')->counts('reservations')->label('Requests'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->value === 'open' ? 'success' : 'gray'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('slot_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRunSlots::route('/'),
        ];
    }
}

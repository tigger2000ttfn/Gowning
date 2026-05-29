<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Models\RunSlot;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RunSlotResource extends Resource
{
    protected static ?string $model = RunSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'run slot';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Weekly run slot')->columns(2)->schema([
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
                TextColumn::make('slot_date')->date()->sortable(),
                TextColumn::make('cleanroom')->searchable(),
                TextColumn::make('start_time')->time('H:i')->placeholder('—'),
                TextColumn::make('capacity'),
                TextColumn::make('reservations_count')->counts('reservations')->label('Requests'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->value === 'open' ? 'success' : 'gray'),
            ])
            ->defaultSort('slot_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRunSlots::route('/'),
            'create' => Pages\CreateRunSlot::route('/create'),
            'edit' => Pages\EditRunSlot::route('/{record}/edit'),
        ];
    }
}

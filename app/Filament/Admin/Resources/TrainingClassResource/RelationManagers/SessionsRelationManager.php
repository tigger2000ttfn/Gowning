<?php

namespace App\Filament\Admin\Resources\TrainingClassResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';
    protected static ?string $title = 'Scheduled Sessions';
    protected static string|\BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Session')->columns(2)->schema([
                DatePicker::make('session_date')->required()->native(false),
                Select::make('status')->options([
                    'open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled',
                ])->default('open')->required(),
                TimePicker::make('start_time')->seconds(false),
                TimePicker::make('end_time')->seconds(false),
                TextInput::make('location')->maxLength(255),
                TextInput::make('instructor')->maxLength(255),
                TextInput::make('capacity')->numeric()->default(20)->required()->minValue(1),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_date')->date()->sortable(),
                TextColumn::make('start_time')->time('g:i A')->placeholder('—'),
                TextColumn::make('location')->placeholder('—'),
                TextColumn::make('instructor')->placeholder('—'),
                TextColumn::make('capacity')->badge(),
                TextColumn::make('enrollments_count')->counts('enrollments')->label('Enrolled')->badge()->color('success'),
                TextColumn::make('status')->badge()->color(fn ($s) => match($s) {
                    'open' => 'success', 'cancelled' => 'danger', default => 'gray',
                }),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Session')->modalHeading('Schedule a Session'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('session_date', 'desc');
    }
}

<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TrainingClassResource\Pages;
use App\Filament\Admin\Resources\TrainingClassResource\RelationManagers;
use App\Models\TrainingClass;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class TrainingClassResource extends Resource
{
    protected static ?string $model = TrainingClass::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 0;
    protected static ?string $modelLabel = 'Gowning Class';
    protected static ?string $pluralModelLabel = 'Gowning Classes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Class Details')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('code')->label('Class Code')->maxLength(50),
                Toggle::make('is_gowning_prerequisite')->label('Counts as gowning prerequisite')->default(true),
                Toggle::make('is_published')->label('Published (visible on public site)')->default(true),
                Textarea::make('description')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('code')->label('Code')->badge()->color('gray'),
                TextColumn::make('sessions_count')->counts('sessions')->label('Sessions')->badge(),
                IconColumn::make('is_gowning_prerequisite')->label('Prereq')->boolean(),
                IconColumn::make('is_published')->label('Published')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\SessionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingClasses::route('/'),
        ];
    }
}

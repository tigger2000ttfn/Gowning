<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Models\Personnel;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PersonnelResource extends Resource
{
    protected static ?string $model = Personnel::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Personnel & Qualifications';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'employee_id';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('employee_id')->label('Employee ID')->required()->unique(ignoreRecord: true),
                    Toggle::make('is_active')->label('Active')->default(true),
                    TextInput::make('first_name')->required(),
                    TextInput::make('last_name')->required(),
                    TextInput::make('email')->email(),
                ]),
            Section::make('Assignment')
                ->columns(2)
                ->schema([
                    TextInput::make('department'),
                    TextInput::make('job_title')->label('Job title'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_id')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('full_name')->label('Name')->searchable(['first_name', 'last_name']),
                TextColumn::make('department')->searchable()->toggleable(),
                TextColumn::make('qualification.status')
                    ->label('Qualification')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'None')
                    ->color(fn ($state) => $state?->color() ?? 'gray'),
                TextColumn::make('qualification.due_date')->label('Due')->date()->sortable()->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('last_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonnel::route('/'),
            'create' => Pages\CreatePersonnel::route('/create'),
            'edit' => Pages\EditPersonnel::route('/{record}/edit'),
        ];
    }
}

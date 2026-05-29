<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ClassCompletionResource\Pages;
use App\Models\ClassCompletion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassCompletionResource extends Resource
{
    protected static ?string $model = ClassCompletion::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Data Import';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'class completion';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Gowning class completion')->columns(2)->schema([
                TextInput::make('employee_id')->label('Employee ID')->required(),
                Select::make('personnel_id')->label('Linked person')
                    ->relationship('personnel', 'employee_id')
                    ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->employee_id} — {$r->full_name}")
                    ->searchable()->preload(),
                TextInput::make('class_name')->required(),
                DatePicker::make('completion_date')->required(),
                Select::make('source')->options(['lms' => 'LMS import', 'manual' => 'Manual'])->default('manual')->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_id')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('personnel.full_name')->label('Name')->placeholder('Unmatched')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('class_name')->label('Class')->searchable(),
                TextColumn::make('completion_date')->date()->sortable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('importBatch.filename')->label('Import file')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('completion_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassCompletions::route('/'),
            'create' => Pages\CreateClassCompletion::route('/create'),
        ];
    }
}

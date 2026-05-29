<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ClassCompletionResource\Pages;
use App\Models\ClassCompletion;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassCompletionResource extends Resource
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
    protected static ?string $model = ClassCompletion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|\UnitEnum|null $navigationGroup = 'Data Import';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Class Completion';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gowning Class Completion')->columns(2)->schema([
                TextInput::make('employee_id')->label('Employee ID')->required(),
                Select::make('personnel_id')->label('Linked Person')
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
                TextColumn::make('importBatch.filename')->label('Import File')->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('completion_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassCompletions::route('/'),
        ];
    }
}

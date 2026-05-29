<?php

namespace App\Filament\Admin\Resources;

use App\Enums\RunResult;
use App\Filament\Admin\Resources\QualificationRunResource\Pages;
use App\Models\Personnel;
use App\Models\QualificationRun;
use App\Services\QualificationEngine;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QualificationRunResource extends Resource
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
    protected static ?string $model = QualificationRun::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Personnel & Qualifications';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Qualification Run';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Run')
                ->columns(2)
                ->schema([
                    Select::make('personnel_id')->label('Person')
                        ->relationship('personnel', 'employee_id')
                        ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->employee_id} — {$r->full_name}")
                        ->searchable()->preload()->required(),
                    DatePicker::make('run_date')->required()->default(now()),
                    Select::make('result')->options([
                        RunResult::Pass->value => 'Pass',
                        RunResult::Fail->value => 'Fail',
                    ])->required(),
                    Textarea::make('notes')->columnSpanFull(),
                ]),
            Section::make('Electronic Signature (21 CFR Part 11)')
                ->description('Recording a run is an electronic signature: it attributes this result to you.')
                ->schema([
                    Textarea::make('signature_meaning')
                        ->label('Meaning Of Signature')
                        ->default('Recorded and verified cleanroom qualification run result')
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('personnel.employee_id')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('personnel.full_name')->label('Name')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('run_date')->date()->sortable(),
                TextColumn::make('result')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s === RunResult::Pass ? 'success' : 'danger'),
                TextColumn::make('cycle_type')->label('Cycle')->formatStateUsing(fn ($s) => $s?->label())->toggleable(),
                TextColumn::make('recordedBy.name')->label('Recorded By')->toggleable(),
                TextColumn::make('signed_at')->dateTime()->label('Signed')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')->options(['pass' => 'Pass', 'fail' => 'Fail']),
            ])
            ->defaultSort('run_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQualificationRuns::route('/'),
            'create' => Pages\CreateQualificationRun::route('/create'),
        ];
    }
}

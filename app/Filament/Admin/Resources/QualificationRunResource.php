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
use Filament\Forms\Components\TextInput;
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
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 7;
    protected static ?string $modelLabel = 'Qualification Run';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Run')->icon('heroicon-o-beaker')
                ->columns(2)
                ->schema([
                    Select::make('personnel_id')->label('Person')
                        ->relationship('personnel', 'employee_id')
                        ->getOptionLabelFromRecordUsing(fn ($r) => $r ? "{$r->employee_id} · {$r->full_name}" : '')
                        ->searchable()->preload()->required(),
                    DatePicker::make('run_date')->required()->default(now()),
                    Select::make('result')->options([
                        RunResult::Pass->value => 'Pass',
                        RunResult::Fail->value => 'Fail',
                        RunResult::Pending->value => 'Pending (Awaiting Results)',
                    ])->required(),
                    TextInput::make('lims_worklist_id')->label('LIMS Worklist ID')
                        ->placeholder('Worklist / batch reference from LIMS'),
                    TextInput::make('veeva_doc_number')->label('Veeva Document Number')
                        ->placeholder('Veeva report / doc number'),
                    TextInput::make('veeva_url')->label('Veeva Link')->url()
                        ->placeholder('https://...')->helperText('Direct link for QA to review in Veeva.'),
                    Textarea::make('notes')->columnSpanFull(),
                ]),
            Section::make('Electronic Signature (21 CFR Part 11)')->icon('heroicon-o-finger-print')
                ->description('Recording a run is an electronic signature: it attributes this result to you.')
                ->schema([
                    Textarea::make('signature_meaning')
                        ->label('Meaning Of Signature')
                        ->default('Recorded and verified cleanroom qualification run result')
                        ->required(),
                ]),
            Section::make('Attachments')->icon('heroicon-o-paper-clip')
                ->description('Plate photos, signed records, or supporting documents for this run.')
                ->schema([
                    \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('records')
                        ->collection('records')->multiple()->downloadable()->reorderable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('personnel.employee_id')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('personnel.full_name')->label('Name')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('run_date')->icon('heroicon-m-beaker')->date()->sortable(),
                TextColumn::make('lims_worklist_id')->label('Worklist')->placeholder('-')->toggleable(),
                TextColumn::make('veeva_doc_number')->label('Veeva')->placeholder('-')->toggleable()
                    ->url(fn ($record) => $record->veeva_url)->openUrlInNewTab(),
                TextColumn::make('result')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => match ($s) {
                        RunResult::Pass => 'success',
                        RunResult::Fail => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('cycle_type')->label('Cycle')->formatStateUsing(fn ($s) => $s?->label())->toggleable(),
                TextColumn::make('recordedBy.name')->label('Recorded By')->toggleable(),
                TextColumn::make('signed_at')->dateTime()->label('Signed')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')->options(['pass' => 'Pass', 'fail' => 'Fail', 'pending' => 'Pending']),
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

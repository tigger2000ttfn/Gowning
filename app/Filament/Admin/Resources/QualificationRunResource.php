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
    protected static ?string $navigationLabel = 'Run Completions';
    protected static ?string $slug = 'run-completions';
    protected static ?string $modelLabel = 'Run Completion';
    protected static ?string $pluralModelLabel = 'Run Completions';

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
                    DatePicker::make('run_date')->native(false)->displayFormat('d-M-Y')->required()->default(now()),
                    Select::make('result')->options([
                        RunResult::Pass->value => 'Pass',
                        RunResult::Fail->value => 'Fail',
                        RunResult::Pending->value => 'Pending (Awaiting Results)',
                    ])->required(),
                    TextInput::make('lims_worklist_id')->label('LIMS Worklist ID')
                        ->placeholder('Worklist / batch reference from LIMS'),
                    TextInput::make('veeva_doc_number')->label('Veeva Document Number')
                        ->prefix('RPT-AST-')
                        ->formatStateUsing(fn ($state) => $state ? preg_replace('/^RPT-AST-/i', '', (string) $state) : $state)
                        ->dehydrateStateUsing(fn ($state) => $state ? 'RPT-AST-' . ltrim(preg_replace('/^RPT-AST[-\s]*/i', '', (string) $state), '-') : $state)
                        ->placeholder('numbers only'),
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
            ->modifyQueryUsing(fn ($query) => $query
                // Run COMPLETIONS = runs whose qualification has been QA-approved (signed off). A run with no
                // QA approval is still in flight and belongs on the boards, not here.
                ->whereHas('qualification', fn ($q) => $q->where('workflow_stage', \App\Enums\WorkflowStage::QaSignoff->value))
                ->with(['personnel', 'qaSignedBy', 'qualification']))
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
                // TrackWise / NC number - shown for failed runs (excursions open an NC in TrackWise).
                TextColumn::make('lims_nc_number')->label('TrackWise NC')
                    ->placeholder('-')
                    ->badge()->color('danger')
                    ->url(fn ($record) => $record->lims_nc_url)->openUrlInNewTab()
                    ->visible(fn () => true)
                    ->toggleable(),
                TextColumn::make('cycle_type')->label('Cycle')->formatStateUsing(fn ($s) => $s?->label())->toggleable(),
                // The QA approver who signed this qualification off (the official record owner).
                TextColumn::make('qaSignedBy.name')->label('QA Approved By')->placeholder('-')->toggleable(),
                TextColumn::make('qa_signed_at')->dateTime()->label('QA Approved')->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('result')->options(['pass' => 'Pass', 'fail' => 'Fail', 'pending' => 'Pending']),
            ])
            ->recordAction('viewRun')
            ->recordActions([
                \Filament\Actions\Action::make('viewRun')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading('Run Completion')
                    ->modalIcon('heroicon-o-clipboard-document-check')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions(function (\App\Models\QualificationRun $record) {
                        $u = \Illuminate\Support\Facades\Auth::user();
                        $canEdit = $u && ($u->hasCapability(\App\Enums\Capability::RecordRuns)
                            || $u->hasCapability(\App\Enums\Capability::QaReview)
                            || $u->hasCapability(\App\Enums\Capability::QaApprove));
                        return $canEdit
                            ? [\Filament\Actions\EditAction::make('editFromRun')->label('Edit Details')->icon('heroicon-m-pencil-square')->record($record)]
                            : [];
                    })
                    ->modalContent(fn (\App\Models\QualificationRun $record) => view('filament.run-completion-card', ['r' => $record])),
                \Filament\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->visible(fn () => (bool) \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageUsers))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Run Record')
                    ->modalDescription('Permanently delete this run record. This cannot be undone. Super-user only.')
                    ->after(fn ($record) => $record?->qualification ? app(\App\Services\QualificationEngine::class)->recompute($record->qualification->fresh()) : null),
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

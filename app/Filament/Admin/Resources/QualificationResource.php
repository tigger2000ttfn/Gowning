<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Filament\Admin\Resources\QualificationResource\RelationManagers;
use App\Models\Qualification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Enums\QualificationStatus;

class QualificationResource extends Resource
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManagePersonnel));
    }
    public static function shouldRegisterNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManagePersonnel));
    }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManagePersonnel));
    }
    protected static ?string $model = Qualification::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Active Runs';
    protected static ?string $pluralModelLabel = 'Active Runs';
    protected static ?string $modelLabel = 'Qualification';

    /** This page is the live qualification picture: people due, in progress, lapsed, or
     *  mid-pipeline. Completed/historic run records live under Run Completions, not here. */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('workflow_stage', '!=', \App\Enums\WorkflowStage::Archived->value);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('personnel.employee_id')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('personnel.full_name')->label('Name')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($s) => $s?->label()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->icon(fn ($s) => match($s?->value) {
                        'qualified' => 'heroicon-m-shield-check',
                        'in_progress' => 'heroicon-m-arrow-path',
                        'pending' => 'heroicon-m-clock',
                        'lapsed' => 'heroicon-m-exclamation-triangle',
                        default => null,
                    })
                    ->color(fn ($s) => $s?->color() ?? 'gray'),
                TextColumn::make('runs_completed')->label('Passes')->icon('heroicon-m-check-circle')
                    ->formatStateUsing(fn ($state, $record) => "{$state} / {$record->runs_required}"),
                TextColumn::make('qualified_date')->date()->placeholder('—')->sortable(),
                TextColumn::make('due_date')->icon('heroicon-m-calendar-days')->label('Due')->date()->placeholder('—')->sortable()
                    ->color(fn ($record) => $record->isPastDue() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('type')->label('Type')->options(
                    collect(\App\Enums\QualificationType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
                SelectFilter::make('status')->options(
                    collect(QualificationStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
                SelectFilter::make('workflow_stage')->label('Stage')->options(
                    collect(\App\Enums\WorkflowStage::cases())->mapWithKeys(fn ($c) => [$c->value => \App\Models\WorkflowStatus::labelFor('run', $c->value, $c->label())])->all()
                ),
                SelectFilter::make('department')->label('Department')
                    ->options(fn () => \App\Models\Personnel::query()->whereNotNull('department')->where('department', '!=', '')
                        ->distinct()->orderBy('department')->pluck('department', 'department')->all())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('personnel', fn ($q) => $q->where('department', $data['value']))
                        : $query),
                Filter::make('overdue')->label('Overdue')->toggle()
                    ->query(fn ($query) => $query->whereNotNull('due_date')->whereDate('due_date', '<', today())),
                Filter::make('due_soon')->label('Due Within 30 Days')->toggle()
                    ->query(fn ($query) => $query->whereNotNull('due_date')
                        ->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', today()->addDays(30))),
                Filter::make('class_on_file')->label('Class On File')->toggle()
                    ->query(fn ($query) => $query->where('class_on_file', true)),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                \Filament\Actions\Action::make('viewDetails')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => $record->personnel?->full_name ?? 'Qualification')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        \Filament\Schemas\Components\Section::make('Qualification')->columns(2)->schema([
                            \Filament\Infolists\Components\TextEntry::make('personnel.employee_id')->label('Employee ID'),
                            \Filament\Infolists\Components\TextEntry::make('personnel.department')->label('Department')->placeholder('—'),
                            \Filament\Infolists\Components\TextEntry::make('type')->label('Type')
                                ->formatStateUsing(fn ($s) => $s?->label())->badge(),
                            \Filament\Infolists\Components\TextEntry::make('status')->label('Status')
                                ->formatStateUsing(fn ($s) => $s?->label())->badge()
                                ->color(fn ($s) => $s?->color() ?? 'gray'),
                            \Filament\Infolists\Components\TextEntry::make('workflow_stage')->label('Workflow Stage')
                                ->formatStateUsing(fn ($s) => $s?->label())->badge(),
                            \Filament\Infolists\Components\TextEntry::make('runs')->label('Passes This Cycle')
                                ->state(fn ($record) => $record->runs_completed . ' / ' . $record->runs_required),
                            \Filament\Infolists\Components\TextEntry::make('qualified_date')->label('Last Qualified')->date()->placeholder('—'),
                            \Filament\Infolists\Components\TextEntry::make('due_date')->label('Due')->date()->placeholder('—')
                                ->color(fn ($record) => $record->isPastDue() ? 'danger' : null),
                            \Filament\Infolists\Components\IconEntry::make('class_on_file')->label('Class On File')->boolean(),
                            \Filament\Infolists\Components\TextEntry::make('qa_recommendation')->label('Last QA Determination')->placeholder('—')
                                ->formatStateUsing(fn ($s) => $s ? \Illuminate\Support\Str::title(str_replace('_', ' ', $s)) : null),
                        ]),
                        \Filament\Schemas\Components\Section::make('Recent Runs')->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('recentRuns')
                                ->label('')
                                ->state(fn ($record) => \App\Models\QualificationRun::where('personnel_id', $record->personnel_id)
                                    ->orderByDesc('run_date')->orderByDesc('id')->limit(6)->get()
                                    ->map(fn ($r) => [
                                        'date' => $r->run_date?->gmp(),
                                        'result' => ucfirst($r->result?->value ?? (string) $r->result),
                                        'worklist' => $r->lims_worklist_id ?: '—',
                                    ])->all())
                                ->columns(3)
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('date')->label('Date'),
                                    \Filament\Infolists\Components\TextEntry::make('result')->label('Result')->badge()
                                        ->color(fn ($s) => match (strtolower($s)) { 'pass' => 'success', 'fail' => 'danger', default => 'warning' }),
                                    \Filament\Infolists\Components\TextEntry::make('worklist')->label('LIMS Worklist'),
                                ]),
                        ])->collapsible(),
                    ]),
            ])
            ->defaultSort('due_date');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQualifications::route('/'),
            'view' => Pages\ViewQualification::route('/{record}'),
        ];
    }

    public static function canCreate(): bool { return false; } // engine-managed
}

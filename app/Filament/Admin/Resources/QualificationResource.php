<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Filament\Admin\Resources\QualificationResource\RelationManagers;
use App\Models\Qualification;
use App\Models\QualificationRun;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\Str;
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
    protected static ?string $slug = 'active-runs';
    protected static ?string $pluralModelLabel = 'Active Runs';
    protected static ?string $modelLabel = 'Qualification';

    /** This page is the live qualification picture: people due, in progress, lapsed, or
     *  mid-pipeline. Completed/historic run records live under Run Completions, not here. */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('workflow_stage', '!=', \App\Enums\WorkflowStage::Archived->value);
    }

    /** Shared rich detail used by both the View page and the Active Runs row modal. */
    public static function detailSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Person')->columns(3)->schema([
                TextEntry::make('personnel.full_name')->label('Name')->weight('bold'),
                TextEntry::make('personnel.employee_id')->label('Employee ID')->placeholder('—'),
                TextEntry::make('personnel.department')->label('Department')->placeholder('—'),
                TextEntry::make('personnel.job_title')->label('Job Title')->placeholder('—'),
                TextEntry::make('personnel.email')->label('Email')->placeholder('—')->copyable(),
                IconEntry::make('class_on_file')->label('Class On File')->boolean(),
            ]),

            Section::make('Qualification')->columns(3)->schema([
                TextEntry::make('type')->label('Session Type')->badge()
                    ->state(fn ($record) => $record->sessionLabel())
                    ->color(fn ($record) => match ($record->qa_recommendation) {
                        'requal_three' => 'danger',
                        'requal_one' => 'warning',
                        default => $record->type?->value === 'initial' ? 'info' : 'success',
                    }),
                TextEntry::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->color() ?? 'gray'),
                TextEntry::make('workflow_stage')->label('Workflow Stage')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->color() ?? 'gray'),
                TextEntry::make('passes')->label('Passes This Cycle')
                    ->state(fn ($record) => $record->runs_completed . ' / ' . $record->runs_required)
                    ->badge()->color('warning'),
                TextEntry::make('qualified_date')->label('Last Qualified')
                    ->state(fn ($record) => $record->qualified_date?->gmp() ?? '—'),
                TextEntry::make('due_date')->label('Next Due')
                    ->state(fn ($record) => $record->due_date?->gmp() ?? '—')
                    ->badge()->color(fn ($record) => $record->isPastDue() ? 'danger' : 'gray'),
                TextEntry::make('class_on_file_date')->label('Class Date')
                    ->state(fn ($record) => $record->class_on_file_date?->gmp() ?? '—'),
                TextEntry::make('cycle_started_at')->label('Cycle Started')
                    ->state(fn ($record) => $record->cycle_started_at?->gmp() ?? '—'),
                TextEntry::make('qa_recommendation')->label('Last QA Determination')->placeholder('—')
                    ->formatStateUsing(fn ($s) => $s ? Str::title(str_replace('_', ' ', $s)) : null),
            ]),

            Section::make('Cycle & Lineage')->columns(3)->schema([
                TextEntry::make('cycle_number')->label('Cycle')->badge()
                    ->state(fn ($record) => 'Cycle ' . ($record->cycle_number ?: 1))
                    ->color(fn ($record) => $record->superseded_at ? 'gray' : 'success'),
                TextEntry::make('cycle_state')->label('Cycle Status')->badge()
                    ->state(fn ($record) => $record->superseded_at ? 'Superseded (History)' : 'Active Cycle')
                    ->color(fn ($record) => $record->superseded_at ? 'gray' : 'success'),
                TextEntry::make('retraining')->label('Retraining')->badge()
                    ->state(fn ($record) => $record->needsRetrainingFirst() ? 'Required First' : '—')
                    ->color(fn ($record) => $record->needsRetrainingFirst() ? 'danger' : 'gray'),
                TextEntry::make('parent_link')->label('Requalification Of')->placeholder('—')
                    ->state(fn ($record) => $record->parent_qualification_id
                        ? ('Cycle ' . max(1, (int) ($record->cycle_number ?: 2) - 1) . ' (failed)') : null)
                    ->url(fn ($record) => $record->parent_qualification_id
                        ? static::getUrl('view', ['record' => $record->parent_qualification_id]) : null)
                    ->color('primary'),
                TextEntry::make('child_link')->label('Superseded By')->placeholder('—')
                    ->state(fn ($record) => $record->children->first()
                        ? ('Cycle ' . $record->children->first()->cycle_number . ' · ' . $record->children->first()->sessionLabel()) : null)
                    ->url(fn ($record) => $record->children->first()
                        ? static::getUrl('view', ['record' => $record->children->first()->id]) : null)
                    ->color('primary'),
            ]),

            Section::make('LIMS & Incubation')->columns(3)
                ->description('Live LIMS data on the active run (synced nightly).')
                ->schema([
                    TextEntry::make('lims_worklist')->label('Worklist')
                        ->state(fn ($record) => optional($record->runs()->whereNotNull('lims_worklist_id')->latest('id')->first())->lims_worklist_id
                            ?? $record->lims_worklist_id ?? '—'),
                    TextEntry::make('lims_eval')->label('LIMS Evaluation')->badge()
                        ->state(fn ($record) => ($r = $record->runs()->whereNotNull('lims_evaluation')->latest('id')->first()) ? ($r->lims_evaluation ?: '—') : '—')
                        ->color(fn ($state) => match (strtolower((string) $state)) { 'pass' => 'success', 'fail' => 'danger', default => 'gray' }),
                    TextEntry::make('lims_inc_status')->label('Incubation Status')->badge()
                        ->state(fn ($record) => ($r = $record->runs()->whereNotNull('lims_inc_status')->latest('id')->first()) ? \App\Models\LimsWorklist::statusLabel($r->lims_inc_status) : '—'),
                    TextEntry::make('inc1')->label('1st Incubation (30-35C)')
                        ->state(function ($record) {
                            $r = $record->runs()->whereNotNull('lims_inc1_start')->latest('id')->first();
                            if (! $r) return '—';
                            return trim(($r->lims_inc1_incubator ? $r->lims_inc1_incubator . ': ' : '') . ($r->lims_inc1_start ?: '?') . ($r->lims_inc1_end ? ' -> ' . $r->lims_inc1_end : ''));
                        }),
                    TextEntry::make('inc2')->label('2nd Incubation (20-25C)')
                        ->state(function ($record) {
                            $r = $record->runs()->whereNotNull('lims_inc2_start')->latest('id')->first();
                            if (! $r) return '—';
                            return trim(($r->lims_inc2_incubator ? $r->lims_inc2_incubator . ': ' : '') . ($r->lims_inc2_start ?: '?') . ($r->lims_inc2_end ? ' -> ' . $r->lims_inc2_end : ''));
                        }),
                    TextEntry::make('inc_due')->label('Incubation')->badge()
                        ->state(function ($record) {
                            $r = $record->runs()->whereNotNull('lims_worklist_id')->latest('id')->first();
                            if (! $r) return '—';
                            if ($r->lims_inc2_end) return 'Complete';
                            $due = $r->lims_inc_due;
                            if (! $due) return $r->lims_inc1_start ? 'Incubating' : '—';
                            try {
                                $d = \Illuminate\Support\Carbon::parse($due);
                                $days = (int) ceil(now()->floatDiffInDays($d, false));
                                return $days >= 0 ? $days . 'd left' : abs($days) . 'd overdue';
                            } catch (\Throwable $e) { return 'Incubating'; }
                        })
                        ->color(fn ($state) => str_contains((string) $state, 'overdue') ? 'danger' : (str_contains((string) $state, 'Complete') ? 'success' : 'warning')),
                ]),

            Section::make('Run History')->schema([
                RepeatableEntry::make('runHistory')
                    ->label('')
                    ->state(fn ($record) => QualificationRun::where('personnel_id', $record->personnel_id)
                        ->orderByDesc('run_date')->orderByDesc('id')->limit(20)->get()
                        ->map(fn ($r) => [
                            'date' => $r->run_date?->gmp() ?? '—',
                            'cycle' => $r->cycle_type instanceof \BackedEnum ? ucfirst($r->cycle_type->value) : (string) $r->cycle_type,
                            'result' => ucfirst($r->result?->value ?? (string) $r->result),
                            'worklist' => $r->lims_worklist_id ?: '—',
                            'veeva' => $r->veeva_doc_number ?: '—',
                        ])->all())
                    ->columns(5)
                    ->schema([
                        TextEntry::make('date')->label('Date'),
                        TextEntry::make('cycle')->label('Cycle'),
                        TextEntry::make('result')->label('Result')->badge()
                            ->color(fn ($s) => match (strtolower($s)) { 'pass' => 'success', 'fail' => 'danger', default => 'warning' }),
                        TextEntry::make('worklist')->label('LIMS Worklist'),
                        TextEntry::make('veeva')->label('Veeva'),
                    ]),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('personnel.employee_id')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('personnel.full_name')->label('Name')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('type')->label('Session Type')->badge()->sortable()
                    ->formatStateUsing(fn ($state, $record) => $record->sessionLabel())
                    ->color(fn ($state, $record) => match ($record->qa_recommendation) {
                        'requal_three' => 'danger',
                        'requal_one' => 'warning',
                        default => $record->type?->value === 'initial' ? 'info' : 'success',
                    }),
                TextColumn::make('status')->badge()->sortable()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->icon(fn ($s) => match($s?->value) {
                        'qualified' => 'heroicon-m-shield-check',
                        'in_progress' => 'heroicon-m-arrow-path',
                        'pending' => 'heroicon-m-clock',
                        'lapsed' => 'heroicon-m-exclamation-triangle',
                        default => null,
                    })
                    ->color(fn ($s) => $s?->color() ?? 'gray'),
                TextColumn::make('workflow_stage')->label('Stage')->badge()->sortable()
                    ->formatStateUsing(fn ($s) => $s ? \App\Models\WorkflowStatus::labelFor('run', $s->value, $s->label()) : '—')
                    ->color(fn ($s) => $s ? \Filament\Support\Colors\Color::hex($s->color()) : 'gray'),
                TextColumn::make('runs_completed')->label('Passes')->icon('heroicon-m-check-circle')
                    ->formatStateUsing(fn ($state, $record) => "{$state} / {$record->runs_required}"),
                TextColumn::make('qualified_date')->date()->placeholder('—')->sortable(),
                TextColumn::make('due_date')->icon('heroicon-m-calendar-days')->label('Due')->date()->placeholder('—')->sortable()
                    ->color(fn ($record) => $record->isPastDue() ? 'danger' : null),
                TextColumn::make('cycle_number')->label('Cycle')->badge()->toggleable()
                    ->formatStateUsing(fn ($state, $record) => 'Cycle ' . ($state ?: 1) . ($record->superseded_at ? ' · history' : ''))
                    ->color(fn ($record) => $record->superseded_at ? 'gray' : 'success'),
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
                SelectFilter::make('cycle_state')->label('Cycle')
                    ->options(['active' => 'Active Cycle', 'history' => 'Superseded (History)'])
                    ->default('active')
                    ->query(function ($query, array $data) {
                        $v = $data['value'] ?? null;
                        if ($v === 'history') return $query->whereNotNull('superseded_at');
                        if ($v === 'active') return $query->whereNull('superseded_at');
                        return $query;
                    }),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                \Filament\Actions\ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => $record->personnel?->full_name . ' · ' . $record->sessionLabel())
                    ->modalWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
                    ->schema(fn (Schema $schema) => static::detailSchema($schema))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions([
                        \Filament\Actions\Action::make('openFull')
                            ->label('Open Full Page')
                            ->icon('heroicon-m-arrow-top-right-on-square')
                            ->color('gray')
                            ->url(fn ($record) => QualificationResource::getUrl('view', ['record' => $record]))
                            ->openUrlInNewTab(),
                    ]),
            ])
            ->recordAction('view')
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

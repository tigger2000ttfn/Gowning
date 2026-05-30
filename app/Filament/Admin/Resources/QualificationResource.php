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
            ->recordUrl(fn ($record) => QualificationResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                \Filament\Actions\Action::make('viewDetails')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn ($record) => QualificationResource::getUrl('view', ['record' => $record])),
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

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
            \Filament\Schemas\Components\View::make('filament.qualification-detail'),
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
                    ->formatStateUsing(function ($state, $record) {
                        $s = $record->status;
                        if ($s instanceof \BackedEnum) return $s->label();
                        $e = \App\Enums\QualificationStatus::tryFrom((string) $s);
                        return $e?->label() ?? \Illuminate\Support\Str::title(str_replace('_', ' ', (string) $s));
                    })
                    ->icon(function ($state, $record) {
                        $v = $record->status instanceof \BackedEnum ? $record->status->value : (string) $record->status;
                        return match ($v) {
                            'qualified' => 'heroicon-m-shield-check',
                            'in_progress' => 'heroicon-m-arrow-path',
                            'pending' => 'heroicon-m-clock',
                            'lapsed' => 'heroicon-m-exclamation-triangle',
                            default => null,
                        };
                    })
                    ->color(function ($state, $record) {
                        $s = $record->status;
                        if ($s instanceof \BackedEnum) return $s->color();
                        return \App\Enums\QualificationStatus::tryFrom((string) $s)?->color() ?? 'gray';
                    }),
                TextColumn::make('workflow_stage')->label('Stage')->badge()->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $s = $record->workflow_stage;
                        if (! $s) return '—';
                        $val = $s instanceof \BackedEnum ? $s->value : (string) $s;
                        $enum = $s instanceof \BackedEnum ? $s : \App\Enums\WorkflowStage::tryFrom($val);
                        return \App\Models\WorkflowStatus::labelFor('run', $val, $enum?->label() ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $val)));
                    })
                    ->color(function ($state, $record) {
                        $s = $record->workflow_stage;
                        if (! $s) return 'gray';
                        $enum = $s instanceof \BackedEnum ? $s : \App\Enums\WorkflowStage::tryFrom((string) $s);
                        return $enum ? \Filament\Support\Colors\Color::hex($enum->color()) : 'gray';
                    }),
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
                \Filament\Actions\Action::make('removeFromPipeline')
                    ->label('Remove From Active Runs')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn () => (bool) (\Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageUsers)
                        || \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::SystemSettings)))
                    ->requiresConfirmation()
                    ->modalHeading('Remove From Active Runs')
                    ->modalDescription('Removes this qualification cycle and its run records, taking the person out of the run pipeline / Status Board. The person record and their class history are kept. If they have an earlier cycle, that earlier cycle becomes their current one again. Use for people accidentally added to the queue.')
                    ->modalSubmitActionLabel('Remove From Pipeline')
                    ->action(function ($record) {
                        $personId = $record->personnel_id;
                        $parentId = $record->parent_qualification_id;
                        // delete this cycle's runs then the cycle itself
                        \App\Models\QualificationRun::where('qualification_id', $record->id)->delete();
                        $record->delete();
                        // restore the parent cycle as active if there was one
                        if ($parentId) {
                            $parent = \App\Models\Qualification::find($parentId);
                            if ($parent) { $parent->forceFill(['superseded_at' => null])->saveQuietly(); }
                        }
                        \Filament\Notifications\Notification::make()->success()->title('Removed From Active Runs')
                            ->body('The qualification cycle was removed from the pipeline.')->send();
                    }),
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

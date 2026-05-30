<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Filament\Admin\Resources\QualificationResource\RelationManagers;
use App\Models\Qualification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
    protected static ?int $navigationSort = 2;

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
                SelectFilter::make('status')->options(
                    collect(QualificationStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('override_due')
                    ->label('Override Due Date')
                    ->icon('heroicon-m-calendar')
                    ->color('warning')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('due_date')->label('New Due Date')->required()->native(false),
                        \Filament\Forms\Components\Textarea::make('reason')->label('Reason (Recorded For Audit)')->required()->rows(2),
                    ])
                    ->fillForm(fn ($record) => ['due_date' => $record->due_date])
                    ->action(function ($record, array $data) {
                        $old = $record->due_date?->toDateString();
                        $record->update(['due_date' => $data['due_date']]);
                        $record->comments()->create([
                            'user_id' => \Illuminate\Support\Facades\Auth::id(),
                            'author_name' => \Illuminate\Support\Facades\Auth::user()?->name,
                            'body' => "QA overrode due date ({$old} → {$data['due_date']}). Reason: {$data['reason']}",
                        ]);
                        \Filament\Notifications\Notification::make()->success()->title('Due date updated')->send();
                    }),
                \Filament\Actions\Action::make('determination')
                    ->label('QA Determination')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('info')
                    ->schema([
                        \Filament\Forms\Components\Select::make('outcome')->label('Determination')->options([
                            'retrain' => 'Require retraining (gowning class again)',
                            'requalify' => 'Requalify (reset to 3 initial runs)',
                            'continue' => 'Continue current cycle (no change)',
                        ])->required(),
                        \Filament\Forms\Components\Textarea::make('note')->label('QA Note')->required()->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->comments()->create([
                            'user_id' => \Illuminate\Support\Facades\Auth::id(),
                            'author_name' => \Illuminate\Support\Facades\Auth::user()?->name,
                            'body' => "QA determination: {$data['outcome']}. {$data['note']}",
                        ]);
                        if ($data['outcome'] === 'requalify') {
                            $record->update(['status' => 'pending', 'runs_completed' => 0, 'runs_required' => (int) \App\Models\Setting::get('initial_runs_required', 3)]);
                        }
                        \Filament\Notifications\Notification::make()->success()->title('Determination recorded')->send();
                    }),
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

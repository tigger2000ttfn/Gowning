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

    /** Editing a recorded class completion (already-approved info) requires QA-or-higher. */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && ($u->hasCapability(\App\Enums\Capability::QaApprove) || $u->hasCapability(\App\Enums\Capability::QaReview)));
    }

    public static function canCreate(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && ($u->hasCapability(\App\Enums\Capability::QaApprove) || $u->hasCapability(\App\Enums\Capability::QaReview)));
    }
    protected static ?string $model = ClassCompletion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|\UnitEnum|null $navigationGroup = 'Classroom';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Class Completion';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Class Completion Details')->icon('heroicon-o-check-badge')
                ->description('The official record that this person completed the gowning class.')
                ->columns(2)->schema([
                    Select::make('personnel_id')->label('Person')
                        ->relationship('personnel', 'employee_id')
                        ->getOptionLabelFromRecordUsing(fn ($r) => $r ? trim(($r->employee_id ?? '') . ' - ' . ($r->full_name ?? '')) : '-')
                        ->searchable()->preload()
                        ->helperText('Link to the personnel record so this completion shows on their qualification.')
                        ->afterStateUpdated(function ($state, callable $set) {
                            $p = $state ? \App\Models\Personnel::find($state) : null;
                            if ($p) $set('employee_id', $p->employee_id);
                        })->live(),
                    TextInput::make('employee_id')->label('Employee ID')->required()
                        ->helperText('Auto-fills from the linked person; editable for unmatched records.'),
                    TextInput::make('class_name')->label('Class Name')->required()
                        ->placeholder('e.g. Gowning Qualification Class')->columnSpanFull(),
                    DatePicker::make('completion_date')->label('Completion Date')
                        ->native(false)->displayFormat('d-M-Y')->required(),
                    Select::make('source')->label('Source')
                        ->options(['lms' => 'LMS Import', 'manual' => 'Manual Entry', 'self' => 'Self-Service', 'import' => 'File Import', 'inferred' => 'Inferred (From Backfill)'])
                        ->default('manual')->required()
                        ->helperText('Inferred entries were auto-created from a historic backfill. Change to Manual once verified.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_id')->icon('heroicon-m-identification')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('personnel.full_name')->label('Name')->placeholder('Unmatched')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('class_name')->icon('heroicon-m-academic-cap')->label('Class')->searchable(),
                TextColumn::make('completion_date')->icon('heroicon-m-check-badge')->date()->sortable(),
                TextColumn::make('source')->label('Source')->badge()
                    ->formatStateUsing(fn ($state) => match (strtolower((string) $state)) {
                        'lms' => 'LMS',
                        'manual' => 'Manual',
                        'self' => 'Self-Service',
                        'import' => 'Import',
                        'inferred' => 'Inferred (Confirm)',
                        default => ucwords(str_replace(['_', '-'], ' ', (string) $state)),
                    })
                    ->color(fn ($state) => strtolower((string) $state) === 'inferred' ? 'warning' : 'gray'),
            ])
            ->recordAction('viewCert')
            ->recordActions([
                \Filament\Actions\ViewAction::make('viewCert')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->modalHeading('')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions(fn (ClassCompletion $record) => \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageClasses)
                        ? [\Filament\Actions\EditAction::make('editFromCert')->label('Edit Details')->icon('heroicon-m-pencil-square')->record($record)]
                        : [])
                    ->modalContent(fn (ClassCompletion $record) => view('filament.class-completion-certificate', ['c' => $record])),
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\EditAction::make()->label('Edit Details')->icon('heroicon-m-pencil-square')
                        ->visible(fn () => (bool) \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageClasses)),
                    \Filament\Actions\Action::make('undoRebook')
                        ->label('Undo (Rebook)')
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn () => (bool) \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageUsers))
                        ->requiresConfirmation()
                        ->modalHeading('Undo Class Completion')
                        ->modalDescription('This removes the class completion and pushes the person back to "needs class" so they can rebook. Their qualification class-on-file is cleared and the card returns to Class Pending. Use when a class was marked complete in error.')
                        ->modalSubmitActionLabel('Undo and Require Rebook')
                        ->action(function (ClassCompletion $record) {
                            $person = $record->personnel;
                            if ($person) {
                                $q = \App\Models\Qualification::currentFor($person->id);
                                if ($q) {
                                    $q->class_on_file = false;
                                    $q->class_on_file_date = null;
                                    if (in_array($q->workflow_stage?->value, ['class_complete', 'run_scheduled'], true)) {
                                        $q->workflow_stage = \App\Enums\WorkflowStage::ClassPending;
                                        $q->stage_changed_at = now();
                                    }
                                    $q->save();
                                }
                                $enroll = \App\Models\ClassEnrollment::where('personnel_id', $person->id)
                                    ->whereIn('status', ['completed', 'pending_qa', 'qcm_reviewed', 'attended'])
                                    ->latest('id')->first();
                                if ($enroll) { $enroll->markStatus('cancelled', \Illuminate\Support\Facades\Auth::id()); }
                            }
                            $record->delete();
                            \Filament\Notifications\Notification::make()->success()->title('Class Completion Undone')
                                ->body(($person?->full_name ?? 'Person') . ' must rebook the gowning class.')->send();
                        }),
                    \Filament\Actions\DeleteAction::make()
                        ->label('Delete Entry')
                        ->icon('heroicon-m-trash')
                        ->visible(fn () => (bool) \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageUsers))
                        ->modalHeading('Delete Class Completion')
                        ->modalDescription('Permanently remove this class completion record. This does NOT change the person\'s qualification status - use Undo (Rebook) for that. Use Delete only to remove a stray/duplicate entry.'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->button()
                    ->color('gray'),
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

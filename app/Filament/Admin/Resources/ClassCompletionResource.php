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
            Section::make('Gowning Class Completion')->icon('heroicon-o-check-badge')->columns(2)->schema([
                TextInput::make('employee_id')->label('Employee ID')->required(),
                Select::make('personnel_id')->label('Linked Person')
                    ->relationship('personnel', 'employee_id')
                    ->getOptionLabelFromRecordUsing(fn ($r) => $r ? trim(($r->employee_id ?? '') . ' · ' . ($r->full_name ?? '')) : '—')
                    ->searchable()->preload(),
                TextInput::make('class_name')->required(),
                DatePicker::make('completion_date')->native(false)->displayFormat('d-M-Y')->required(),
                Select::make('source')->options(['lms' => 'LMS import', 'manual' => 'Manual'])->default('manual')->required(),
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
                        default => ucwords(str_replace(['_', '-'], ' ', (string) $state)),
                    }),
                TextColumn::make('importBatch.filename')->label('Import File')->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
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
                        // Reverse the qualification: clear class on file, send back to Class Pending.
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
                            // Reopen the latest finished class enrollment so they appear as needing a class.
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
                    ->visible(fn () => (bool) \Illuminate\Support\Facades\Auth::user()?->hasCapability(\App\Enums\Capability::ManageUsers))
                    ->modalHeading('Delete Class Completion')
                    ->modalDescription('Permanently remove this class completion record. This does NOT change the person\'s qualification status - use Undo (Rebook) for that. Use Delete only to remove a stray/duplicate entry.'),
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

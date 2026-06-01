<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Filament\Admin\Resources\PersonnelResource;
use App\Enums\Capability;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPersonnel extends EditRecord
{
    protected static string $resource = PersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startQualification')
                ->label('Start Qualification')
                ->icon('heroicon-m-play')
                ->color('primary')
                ->visible(fn () => $this->record && ! $this->record->qualification()->exists())
                ->modalHeading('Start A Qualification')
                ->modalDescription('This puts the person into the gowning pipeline. Pick the type and the due date. They start at the class step (or, for a transfer who is already qualified, you can mark the class on file).')
                ->form([
                    \Filament\Forms\Components\Select::make('type')->label('Qualification Type')
                        ->options(['initial' => 'Initial Gowning Qualification (3 runs)', 'annual' => 'Annual Requalification (1 run)'])
                        ->default('initial')->required(),
                    \Filament\Forms\Components\DatePicker::make('due_date')->native(false)->displayFormat('d-M-Y')
                        ->label('Qualification Due Date')->required(),
                ])
                ->action(function (array $data) {
                    $person = $this->record;
                    $type = ($data['type'] ?? 'initial') === 'annual' ? \App\Enums\QualificationType::Annual : \App\Enums\QualificationType::Initial;
                    \App\Models\Qualification::create([
                        'personnel_id' => $person->id,
                        'type' => $type,
                        'status' => \App\Enums\QualificationStatus::Pending,
                        'runs_required' => $type->runsRequired(),
                        'runs_completed' => 0,
                        'due_date' => \Illuminate\Support\Carbon::parse($data['due_date'])->toDateString(),
                        'workflow_stage' => \App\Enums\WorkflowStage::ClassPending,
                        'stage_changed_at' => now(),
                    ]);
                    \Filament\Notifications\Notification::make()->success()->title('Qualification Started')
                        ->body(trim($person->first_name . ' ' . $person->last_name) . ' is now in the pipeline.')->send();
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $person]));
                }),
            Action::make('backToActiveRuns')
                ->label('Back To Active Runs')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(fn () => \App\Filament\Admin\Resources\QualificationResource::getUrl('index')),
            DeleteAction::make(),
            Action::make('purge')
                ->label('Purge (Permanent)')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => (bool) Auth::user()?->hasCapability(Capability::ManageUsers))
                ->requiresConfirmation()
                ->modalHeading('Permanently Delete This Person')
                ->modalDescription('This wipes the person and ALL their data (bookings, reservations, qualifications, runs, class enrollments, and completions). This cannot be undone. Use only for test people or true mistakes.')
                ->modalSubmitActionLabel('Yes, permanently delete')
                ->action(function () {
                    $name = trim($this->record->first_name . ' ' . $this->record->last_name);
                    $this->record->forceDelete();
                    \Filament\Notifications\Notification::make()->success()
                        ->title('Permanently Deleted')->body($name . ' and all their data were removed.')->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function afterSave(): void
    {
        $q = $this->record->qualification;
        if ($q) {
            app(\App\Services\QualificationSeeder::class)->reconcile($q);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

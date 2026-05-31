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

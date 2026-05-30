<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Filament\Admin\Resources\PersonnelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPersonnel extends EditRecord
{
    protected static string $resource = PersonnelResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }

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

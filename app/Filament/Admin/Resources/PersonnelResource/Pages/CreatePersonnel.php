<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Filament\Admin\Resources\PersonnelResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonnel extends CreateRecord
{
    protected static string $resource = PersonnelResource::class;

    protected function afterCreate(): void
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

<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Filament\Admin\Resources\PersonnelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditPersonnel extends EditRecord
{
    protected static string $resource = PersonnelResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}

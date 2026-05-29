<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Filament\Admin\Resources\PersonnelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListPersonnel extends ListRecords
{
    protected static string $resource = PersonnelResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}

<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Filament\Admin\Resources\PersonnelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListPersonnel extends ListRecords
{
    protected static string $resource = PersonnelResource::class;
    public function getSubheading(): ?string { return 'The cleanroom workforce and their qualification records.'; }
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}

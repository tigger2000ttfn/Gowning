<?php
namespace App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Filament\Admin\Resources\RunSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditRunSlot extends EditRecord
{
    protected static string $resource = RunSlotResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}

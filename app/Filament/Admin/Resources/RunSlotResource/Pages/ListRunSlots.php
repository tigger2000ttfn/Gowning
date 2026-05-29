<?php
namespace App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Filament\Admin\Resources\RunSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListRunSlots extends ListRecords
{
    protected static string $resource = RunSlotResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Publish slot')]; }
}

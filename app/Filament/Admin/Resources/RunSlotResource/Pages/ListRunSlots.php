<?php
namespace App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Filament\Admin\Resources\RunSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
class ListRunSlots extends ListRecords
{
    protected static string $resource = RunSlotResource::class;
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Publish slot')
                ->modalHeading('Publish a run slot')
                ->mutateDataUsing(function (array $data): array {
                    $data['created_by'] = Auth::id();
                    return $data;
                }),
        ];
    }
}

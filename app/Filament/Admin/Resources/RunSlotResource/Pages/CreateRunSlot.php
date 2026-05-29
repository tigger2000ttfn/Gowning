<?php
namespace App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Filament\Admin\Resources\RunSlotResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
class CreateRunSlot extends CreateRecord
{
    protected static string $resource = RunSlotResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    { $data['created_by'] = Auth::id(); return $data; }
}

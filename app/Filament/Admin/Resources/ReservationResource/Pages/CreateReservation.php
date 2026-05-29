<?php
namespace App\Filament\Admin\Resources\ReservationResource\Pages;
use App\Filament\Admin\Resources\ReservationResource;
use Filament\Resources\Pages\CreateRecord;
class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    { $data['requested_at'] = $data['requested_at'] ?? now(); return $data; }
}

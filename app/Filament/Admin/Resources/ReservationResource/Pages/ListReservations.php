<?php
namespace App\Filament\Admin\Resources\ReservationResource\Pages;
use App\Filament\Admin\Resources\ReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Reservation')
                ->modalHeading('Request A Run Slot')
                ->mutateDataUsing(function (array $data): array {
                    $data['status'] = 'requested';
                    $data['requested_at'] = now();
                    return $data;
                }),
        ];
    }
}

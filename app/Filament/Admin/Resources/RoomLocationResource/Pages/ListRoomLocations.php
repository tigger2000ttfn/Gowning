<?php

namespace App\Filament\Admin\Resources\RoomLocationResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;

use App\Filament\Admin\Resources\RoomLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoomLocations extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Room Locations';
    public ?string $gqsSubtitle = 'Classroom / office room locations used for class scheduling.';
    public ?string $gqsIcon = 'heroicon-o-building-office';
    protected static string $resource = RoomLocationResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add')];
    }
}

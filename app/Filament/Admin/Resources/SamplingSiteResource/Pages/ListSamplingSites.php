<?php

namespace App\Filament\Admin\Resources\SamplingSiteResource\Pages;

use App\Filament\Admin\Resources\SamplingSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSamplingSites extends ListRecords
{
    protected static string $resource = SamplingSiteResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add')];
    }
}

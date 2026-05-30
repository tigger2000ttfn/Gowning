<?php

namespace App\Filament\Admin\Resources\CleanroomResource\Pages;

use App\Filament\Admin\Resources\CleanroomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCleanrooms extends ListRecords
{
    protected static string $resource = CleanroomResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add')];
    }
}

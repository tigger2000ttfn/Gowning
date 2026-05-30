<?php

namespace App\Filament\Admin\Resources\NonConformanceResource\Pages;

use App\Filament\Admin\Resources\NonConformanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNonConformances extends ListRecords
{
    protected static string $resource = NonConformanceResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Log NC')];
    }
}

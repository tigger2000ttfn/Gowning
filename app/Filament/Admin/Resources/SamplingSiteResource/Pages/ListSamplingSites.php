<?php

namespace App\Filament\Admin\Resources\SamplingSiteResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;

use App\Filament\Admin\Resources\SamplingSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSamplingSites extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Sampling Sites';
    public ?string $gqsSubtitle = 'Microbial sampling sites used on run days.';
    public ?string $gqsIcon = 'heroicon-o-hand-raised';
    protected static string $resource = SamplingSiteResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add')];
    }
}

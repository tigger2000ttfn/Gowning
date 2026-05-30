<?php

namespace App\Filament\Admin\Resources\NonConformanceResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;

use App\Filament\Admin\Resources\NonConformanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNonConformances extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Non-Conformances';
    public ?string $gqsSubtitle = 'Failed runs, mold and bacteria hits, TrackWise links.';
    public ?string $gqsIcon = 'heroicon-o-exclamation-triangle';
    protected static string $resource = NonConformanceResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Log NC')];
    }
}

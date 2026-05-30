<?php

namespace App\Filament\Admin\Resources\JobTitleResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;

use App\Filament\Admin\Resources\JobTitleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobTitles extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Job Titles';
    public ?string $gqsSubtitle = 'Job titles for personnel assignment.';
    public ?string $gqsIcon = 'heroicon-o-briefcase';
    protected static string $resource = JobTitleResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add')];
    }
}

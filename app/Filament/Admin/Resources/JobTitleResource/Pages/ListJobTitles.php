<?php

namespace App\Filament\Admin\Resources\JobTitleResource\Pages;

use App\Filament\Admin\Resources\JobTitleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobTitles extends ListRecords
{
    protected static string $resource = JobTitleResource::class;
    public function getSubheading(): ?string { return 'Job titles for personnel assignment.'; }
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add')];
    }
}

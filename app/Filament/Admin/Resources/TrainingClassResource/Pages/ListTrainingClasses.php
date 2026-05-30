<?php
namespace App\Filament\Admin\Resources\TrainingClassResource\Pages;
use App\Filament\Admin\Resources\TrainingClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListTrainingClasses extends ListRecords
{
    protected static string $resource = TrainingClassResource::class;
    public function getSubheading(): ?string { return 'Class catalog, sessions, and prerequisites.'; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Class')->modalHeading('Create A Gowning Class')];
    }
}

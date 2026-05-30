<?php
namespace App\Filament\Admin\Resources\TrainingClassResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\TrainingClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListTrainingClasses extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Gowning Classes';
    public ?string $gqsSubtitle = 'Class catalog, sessions, and prerequisites.';
    public ?string $gqsIcon = 'heroicon-o-academic-cap';
    protected static string $resource = TrainingClassResource::class;
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Class')->modalHeading('Create A Gowning Class')];
    }
}

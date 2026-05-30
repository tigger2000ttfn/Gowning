<?php
namespace App\Filament\Admin\Resources\ClassCompletionResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\ClassCompletionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListClassCompletions extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Class Completions';
    public ?string $gqsSubtitle = 'Recorded gowning class completions on file.';
    public ?string $gqsIcon = 'heroicon-o-clipboard-document-check';
    protected static string $resource = ClassCompletionResource::class;
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add Completion')->modalHeading('Record A Class Completion')];
    }
}

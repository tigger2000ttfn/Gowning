<?php
namespace App\Filament\Admin\Resources\ClassCompletionResource\Pages;
use App\Filament\Admin\Resources\ClassCompletionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListClassCompletions extends ListRecords
{
    protected static string $resource = ClassCompletionResource::class;
    public function getSubheading(): ?string { return 'Recorded gowning class completions on file.'; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add Completion')->modalHeading('Record A Class Completion')];
    }
}

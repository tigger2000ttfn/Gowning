<?php
namespace App\Filament\Admin\Resources\ClassCompletionResource\Pages;
use App\Filament\Admin\Resources\ClassCompletionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListClassCompletions extends ListRecords
{
    protected static string $resource = ClassCompletionResource::class;
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add completion')->modalHeading('Record a class completion')];
    }
}

<?php

namespace App\Filament\Admin\Resources\DepartmentResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;

use App\Filament\Admin\Resources\DepartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Departments';
    public ?string $gqsSubtitle = 'Departments for personnel assignment.';
    public ?string $gqsIcon = 'heroicon-o-building-office';
    protected static string $resource = DepartmentResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add')];
    }
}

<?php
namespace App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\PersonnelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListPersonnel extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Personnel';
    public ?string $gqsSubtitle = 'The cleanroom workforce and their qualification records.';
    public ?string $gqsIcon = 'heroicon-o-users';
    protected static string $resource = PersonnelResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}

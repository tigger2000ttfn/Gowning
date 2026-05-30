<?php
namespace App\Filament\Admin\Resources\UserResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListUsers extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Users & Approvals';
    public ?string $gqsSubtitle = 'Staff accounts, roles, and approval status.';
    public ?string $gqsIcon = 'heroicon-o-user-group';
    protected static string $resource = UserResource::class;
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New User')->modalHeading('Create A User')];
    }
}

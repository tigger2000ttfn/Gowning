<?php

namespace App\Filament\Admin\Resources\EmailTemplateResource\Pages;

use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\EmailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Email Templates';
    public ?string $gqsSubtitle = 'Edit the emails the system sends. Tokens are filled in automatically.';
    public ?string $gqsIcon = 'heroicon-o-envelope';
    protected static string $resource = EmailTemplateResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array { return [CreateAction::make()->label('New Template')]; }
}

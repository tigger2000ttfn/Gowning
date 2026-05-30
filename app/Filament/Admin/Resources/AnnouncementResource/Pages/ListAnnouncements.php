<?php

namespace App\Filament\Admin\Resources\AnnouncementResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;

use App\Filament\Admin\Resources\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListAnnouncements extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Announcements';
    public ?string $gqsSubtitle = 'Broadcast messages to staff and operators.';
    public ?string $gqsIcon = 'heroicon-o-megaphone';
    protected static string $resource = AnnouncementResource::class;

    public function getHeading(): string { return ''; }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Post Announcement')
                ->mutateDataUsing(function (array $data) {
                    $data['author_id'] = Auth::id();
                    $data['author_name'] = Auth::user()?->name;
                    return $data;
                }),
        ];
    }
}

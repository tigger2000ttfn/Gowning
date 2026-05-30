<?php

namespace App\Filament\Admin\Resources\AnnouncementResource\Pages;

use App\Filament\Admin\Resources\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;
    public function getSubheading(): ?string { return 'Broadcast messages to staff and operators.'; }

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

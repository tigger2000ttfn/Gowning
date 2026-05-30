<?php
namespace App\Filament\Admin\Resources\RunSlotResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\RunSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
class ListRunSlots extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Run Slots';
    public ?string $gqsSubtitle = 'Run day slots, capacity, and analyst assignment.';
    public ?string $gqsIcon = 'heroicon-o-calendar-days';
    protected static string $resource = RunSlotResource::class;
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Publish Slot')
                ->modalHeading('Publish A Run Slot')
                ->mutateDataUsing(function (array $data): array {
                    $data['created_by'] = Auth::id();
                    return $data;
                }),
        ];
    }
}

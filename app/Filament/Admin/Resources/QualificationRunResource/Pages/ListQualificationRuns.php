<?php
namespace App\Filament\Admin\Resources\QualificationRunResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\QualificationRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListQualificationRuns extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Qualification Runs';
    public ?string $gqsSubtitle = 'Recorded cleanroom run results and LIMS / Veeva links.';
    public ?string $gqsIcon = 'heroicon-o-beaker';
    protected static string $resource = QualificationRunResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Record Run')]; }
}

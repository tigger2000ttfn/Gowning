<?php
namespace App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\QualificationResource;
use Filament\Resources\Pages\ListRecords;
class ListQualifications extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Qualifications';
    public ?string $gqsSubtitle = 'Engine-driven status, runs, and due dates.';
    public ?string $gqsIcon = 'heroicon-o-shield-check';
    protected static string $resource = QualificationResource::class;
}

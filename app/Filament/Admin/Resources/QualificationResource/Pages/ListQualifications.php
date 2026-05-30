<?php
namespace App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\QualificationResource;
use Filament\Resources\Pages\ListRecords;
class ListQualifications extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Active Runs';
    public ?string $gqsSubtitle = 'Current qualification status for active people: due, in progress, lapsed, or mid-pipeline. Completed run history lives under Run Completions.';
    public ?string $gqsIcon = 'heroicon-o-shield-check';
    protected static string $resource = QualificationResource::class;
}

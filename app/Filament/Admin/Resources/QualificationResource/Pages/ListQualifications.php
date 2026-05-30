<?php
namespace App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Filament\Admin\Resources\QualificationResource;
use Filament\Resources\Pages\ListRecords;
class ListQualifications extends ListRecords
{
    protected static string $resource = QualificationResource::class;
    public function getSubheading(): ?string { return 'Engine-driven status, runs, and due dates.'; }
}

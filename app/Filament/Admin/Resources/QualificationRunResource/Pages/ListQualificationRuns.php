<?php
namespace App\Filament\Admin\Resources\QualificationRunResource\Pages;
use App\Filament\Admin\Resources\QualificationRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListQualificationRuns extends ListRecords
{
    protected static string $resource = QualificationRunResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Record run')]; }
}

<?php

namespace App\Filament\Admin\Resources\QualificationResource\Pages;

use App\Filament\Admin\Resources\QualificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewQualification extends ViewRecord
{
    protected static string $resource = QualificationResource::class;

    /** Render our own clean record view (with a back link + header), not Filament's wrapped infolist. */
    public function getView(): string { return 'filament.pages.qualification-record'; }
    public function getHeader(): ?\Illuminate\Contracts\View\View { return view('filament.empty-header'); }
    public function getHeading(): string { return ''; }
    public function getSubheading(): ?string { return null; }
    public function getBreadcrumbs(): array { return []; }
}

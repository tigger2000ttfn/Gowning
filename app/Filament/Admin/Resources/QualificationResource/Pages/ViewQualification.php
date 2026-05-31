<?php

namespace App\Filament\Admin\Resources\QualificationResource\Pages;

use App\Enums\Capability;
use App\Filament\Admin\Resources\QualificationResource;
use App\Models\QualificationRun;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ViewQualification extends ViewRecord
{
    protected static string $resource = QualificationResource::class;

    public function getTitle(): string
    {
        return $this->record->personnel?->full_name ?? 'Qualification';
    }

    public function infolist(Schema $schema): Schema
    {
        return \App\Filament\Admin\Resources\QualificationResource::detailSchema($schema);
    }

    protected function getHeaderActions(): array
    {
        // QA determination and due-date overrides are NOT done here. Determinations belong to the
        // QA Review pipeline (Lab Review -> QCM sign-off -> QA Review), and due-date overrides are an
        // admin function handled elsewhere. Active Runs is a read-only view of the live cycle.
        return [];
    }
}

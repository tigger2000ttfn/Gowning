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
        return $schema->components([
            Section::make('Person')->columns(3)->schema([
                TextEntry::make('personnel.full_name')->label('Name')->weight('bold'),
                TextEntry::make('personnel.employee_id')->label('Employee ID')->placeholder('—'),
                TextEntry::make('personnel.department')->label('Department')->placeholder('—'),
                TextEntry::make('personnel.job_title')->label('Job Title')->placeholder('—'),
                TextEntry::make('personnel.email')->label('Email')->placeholder('—')->copyable(),
                IconEntry::make('class_on_file')->label('Class On File')->boolean(),
            ]),

            Section::make('Qualification')->columns(3)->schema([
                TextEntry::make('type')->label('Session Type')->badge()
                    ->state(fn ($record) => $record->sessionLabel())
                    ->color(fn ($record) => match ($record->qa_recommendation) {
                        'requal_three' => 'danger',
                        'requal_one' => 'warning',
                        default => $record->type?->value === 'initial' ? 'info' : 'success',
                    }),
                TextEntry::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->color() ?? 'gray'),
                TextEntry::make('workflow_stage')->label('Workflow Stage')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->color() ?? 'gray'),
                TextEntry::make('passes')->label('Passes This Cycle')
                    ->state(fn ($record) => $record->runs_completed . ' / ' . $record->runs_required)
                    ->badge()->color('warning'),
                TextEntry::make('qualified_date')->label('Last Qualified')
                    ->state(fn ($record) => $record->qualified_date?->gmp() ?? '—'),
                TextEntry::make('due_date')->label('Next Due')
                    ->state(fn ($record) => $record->due_date?->gmp() ?? '—')
                    ->badge()->color(fn ($record) => $record->isPastDue() ? 'danger' : 'gray'),
                TextEntry::make('class_on_file_date')->label('Class Date')
                    ->state(fn ($record) => $record->class_on_file_date?->gmp() ?? '—'),
                TextEntry::make('cycle_started_at')->label('Cycle Started')
                    ->state(fn ($record) => $record->cycle_started_at?->gmp() ?? '—'),
                TextEntry::make('qa_recommendation')->label('Last QA Determination')->placeholder('—')
                    ->formatStateUsing(fn ($s) => $s ? Str::title(str_replace('_', ' ', $s)) : null),
            ]),

            Section::make('Cycle & Lineage')->columns(3)->schema([
                TextEntry::make('cycle_number')->label('Cycle')->badge()
                    ->state(fn ($record) => 'Cycle ' . ($record->cycle_number ?: 1))
                    ->color(fn ($record) => $record->superseded_at ? 'gray' : 'success'),
                TextEntry::make('cycle_state')->label('Cycle Status')->badge()
                    ->state(fn ($record) => $record->superseded_at ? 'Superseded (History)' : 'Active Cycle')
                    ->color(fn ($record) => $record->superseded_at ? 'gray' : 'success'),
                TextEntry::make('retraining')->label('Retraining')->badge()
                    ->state(fn ($record) => $record->needsRetrainingFirst() ? 'Required First' : '—')
                    ->color(fn ($record) => $record->needsRetrainingFirst() ? 'danger' : 'gray'),
                TextEntry::make('parent_link')->label('Requalification Of')->placeholder('—')
                    ->state(fn ($record) => $record->parent_qualification_id
                        ? ('Cycle ' . max(1, (int) ($record->cycle_number ?: 2) - 1) . ' (failed)') : null)
                    ->url(fn ($record) => $record->parent_qualification_id
                        ? \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $record->parent_qualification_id]) : null)
                    ->color('primary'),
                TextEntry::make('child_link')->label('Superseded By')->placeholder('—')
                    ->state(fn ($record) => $record->children->first()
                        ? ('Cycle ' . $record->children->first()->cycle_number . ' · ' . $record->children->first()->sessionLabel()) : null)
                    ->url(fn ($record) => $record->children->first()
                        ? \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $record->children->first()->id]) : null)
                    ->color('primary'),
            ]),

            Section::make('Run History')->schema([
                RepeatableEntry::make('runHistory')
                    ->label('')
                    ->state(fn ($record) => QualificationRun::where('personnel_id', $record->personnel_id)
                        ->orderByDesc('run_date')->orderByDesc('id')->limit(20)->get()
                        ->map(fn ($r) => [
                            'date' => $r->run_date?->gmp() ?? '—',
                            'cycle' => $r->cycle_type instanceof \BackedEnum ? ucfirst($r->cycle_type->value) : (string) $r->cycle_type,
                            'result' => ucfirst($r->result?->value ?? (string) $r->result),
                            'worklist' => $r->lims_worklist_id ?: '—',
                            'veeva' => $r->veeva_doc_number ?: '—',
                        ])->all())
                    ->columns(5)
                    ->schema([
                        TextEntry::make('date')->label('Date'),
                        TextEntry::make('cycle')->label('Cycle'),
                        TextEntry::make('result')->label('Result')->badge()
                            ->color(fn ($s) => match (strtolower($s)) { 'pass' => 'success', 'fail' => 'danger', default => 'warning' }),
                        TextEntry::make('worklist')->label('LIMS Worklist'),
                        TextEntry::make('veeva')->label('Veeva'),
                    ]),
            ])->collapsible(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        // QA determination and due-date overrides are NOT done here. Determinations belong to the
        // QA Review pipeline (Lab Review -> QCM sign-off -> QA Review), and due-date overrides are an
        // admin function handled elsewhere. Active Runs is a read-only view of the live cycle.
        return [];
    }
}

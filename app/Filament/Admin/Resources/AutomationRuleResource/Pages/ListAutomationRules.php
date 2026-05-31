<?php

namespace App\Filament\Admin\Resources\AutomationRuleResource\Pages;

use App\Filament\Admin\Concerns\GqsListHero;
use App\Filament\Admin\Resources\AutomationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAutomationRules extends ListRecords
{
    use GqsListHero;
    public ?string $gqsTitle = 'Automation Rules';
    public ?string $gqsSubtitle = 'When something happens, do something automatically. Build your own without code.';
    public ?string $gqsIcon = 'heroicon-o-bolt';
    protected static string $resource = AutomationRuleResource::class;
    public function getHeading(): string { return ''; }
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('history')
                ->label('Run History')
                ->icon('heroicon-m-clock')
                ->color('gray')
                ->modalHeading('Automation Run History')
                ->modalIcon('heroicon-o-clock')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth('5xl')
                ->modalContent(fn () => view('filament.automation-history', [
                    'runs' => \App\Models\AutomationRun::with('rule')->latest()->limit(200)->get(),
                ])),
            CreateAction::make()->label('New Rule'),
        ];
    }
}

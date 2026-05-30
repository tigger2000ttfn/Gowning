<?php

namespace App\Filament\Admin\Resources;

use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Filament\Admin\Resources\AutomationRuleResource\Pages;
use App\Models\AutomationRule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AutomationRuleResource extends Resource
{
    protected static ?string $model = AutomationRule::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Automation Rules';
    protected static ?string $modelLabel = 'Automation Rule';
    protected static string|\UnitEnum|null $navigationGroup = null;

    public static function shouldRegisterNavigation(): bool { return false; } // surfaced in Manage
    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::SystemSettings);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rule')->columns(2)->schema([
                TextInput::make('name')->label('Rule Name')->required()->columnSpanFull()
                    ->placeholder('e.g. Alert QA when a run fails'),
                Toggle::make('is_enabled')->label('Enabled')->default(true)->inline(false),
            ]),
            Section::make('When this happens (trigger)')->columns(2)->schema([
                Select::make('trigger')->label('Trigger Event')
                    ->options(AutomationTrigger::options())->required()->live()
                    ->helperText('What event starts this rule.'),
                Select::make('trigger_stage')->label('Only For Stage (optional)')
                    ->options(collect(WorkflowStage::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all())
                    ->visible(fn ($get) => $get('trigger') === AutomationTrigger::StageChanged->value)
                    ->helperText('Limit to one workflow stage.'),
            ]),
            Section::make('Do this (action)')->columns(2)->schema([
                Select::make('action')->label('Action')
                    ->options(AutomationAction::options())->required()->live(),
                Select::make('action_config.capability')->label('Notify Which Role')
                    ->options(collect(Capability::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
                    ->visible(fn ($get) => $get('action') === AutomationAction::NotifyCapability->value)
                    ->helperText('Everyone holding this capability gets an in-app notification.'),
                TextInput::make('action_config.title')->label('Title / Subject')
                    ->visible(fn ($get) => in_array($get('action'), [
                        AutomationAction::PostAnnouncement->value,
                        AutomationAction::QueueEmail->value,
                        AutomationAction::NotifyCapability->value,
                        AutomationAction::NotifyPerson->value,
                    ]))->columnSpanFull(),
                Textarea::make('action_config.message')->label('Message')->rows(2)->columnSpanFull()
                    ->helperText('Tokens: {name}, {employee_id}, {stage} are replaced when the rule fires.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_enabled')->label('On')->boolean(),
                TextColumn::make('name')->label('Rule')->searchable()->weight('bold')->wrap(),
                TextColumn::make('trigger')->label('When')
                    ->formatStateUsing(fn ($s) => AutomationTrigger::tryFrom($s)?->label() ?? $s)->wrap(),
                TextColumn::make('action')->label('Do')
                    ->formatStateUsing(fn ($s) => AutomationAction::tryFrom($s)?->label() ?? $s)->wrap(),
                TextColumn::make('run_count')->label('Fired')->badge()->color('gray'),
                TextColumn::make('last_fired_at')->label('Last Fired')->since()->placeholder('Never'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomationRules::route('/'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }
}

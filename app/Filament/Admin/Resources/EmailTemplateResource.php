<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Capability;
use App\Filament\Admin\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Email Templates';
    protected static ?string $modelLabel = 'Email Template';

    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::SystemSettings);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template')->columns(2)->schema([
                TextInput::make('name')->label('Template Name')->required(),
                Toggle::make('is_enabled')->label('Enabled')->default(true)->inline(false),
                TextInput::make('key')->label('Event Key')->required()->disabled(fn ($record) => $record !== null)
                    ->helperText('Internal event key, e.g. run_scheduled. Do not change once set.')->columnSpanFull(),
                TextInput::make('subject')->label('Subject Line')->required()->columnSpanFull(),
                Textarea::make('body_html')->label('Body (HTML)')->rows(10)->required()->columnSpanFull()
                    ->helperText('HTML allowed. Tokens: {name}, {employee_id}, {due_date}, {date} are replaced when sent. Wrapped automatically in the branded layout.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_enabled')->label('On')->boolean(),
                TextColumn::make('name')->label('Template')->searchable()->weight('bold'),
                TextColumn::make('key')->label('Event Key')->badge()->color('gray'),
                TextColumn::make('subject')->label('Subject')->limit(50)->wrap(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEmailTemplates::route('/')];
    }
}

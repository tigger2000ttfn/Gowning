<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkflowStatusResource\Pages;
use App\Models\WorkflowStatus;
use App\Enums\Capability;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WorkflowStatusResource extends Resource
{
    protected static ?string $model = WorkflowStatus::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';
    protected static ?string $modelLabel = 'Status';
    protected static ?string $pluralModelLabel = 'Statuses';
    public static function shouldRegisterNavigation(): bool { return false; } // in Manage menu

    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && $u->hasCapability(Capability::SystemSettings));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Status')->columns(2)->schema([
                Select::make('domain')->label('Workflow')->options([
                    'run' => 'Run Pipeline (Qualification)',
                    'class' => 'Classroom',
                ])->required()
                    ->helperText('Which workflow this status belongs to. Run and Class can share names but are tracked separately.')
                    ->disabled(fn ($record) => $record?->is_system),

                TextInput::make('label')->label('Display Name')->required()->maxLength(60)
                    ->helperText('What users see on boards and badges. Safe to rename anytime.'),

                ColorPicker::make('color')->label('Color')->required()->hex(),

                TextInput::make('key')->label('System Key')
                    ->helperText('Stable identifier used in code. Auto-generated for new statuses; not editable for built-in ones.')
                    ->disabled(fn ($record) => $record?->is_system)
                    ->dehydrated()
                    ->maxLength(50),

                TextInput::make('sort')->label('Order')->numeric()->default(0),
                Toggle::make('is_active')->label('Active')->default(true)
                    ->helperText('Inactive statuses stay on existing records but are hidden from pickers.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->badge()
                    ->formatStateUsing(fn ($s) => $s === 'run' ? 'Run Pipeline' : 'Classroom')
                    ->color(fn ($s) => $s === 'run' ? 'primary' : 'success')->sortable(),
                ColorColumn::make('color'),
                TextColumn::make('label')->label('Display Name')->searchable()->sortable(),
                TextColumn::make('key')->label('Key')->color('gray')->toggleable(),
                IconColumn::make('is_system')->label('Built-in')->boolean()
                    ->trueIcon('heroicon-o-lock-closed')->falseIcon('heroicon-o-pencil')
                    ->trueColor('gray')->falseColor('warning'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('sort')->label('Order')->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('domain')->options([
                    'run' => 'Run Pipeline', 'class' => 'Classroom',
                ]),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->visible(fn (WorkflowStatus $r) => ! $r->is_system), // built-ins can't be deleted (engine relies on them)
            ])
            ->defaultSort('domain')
            ->defaultGroup('domain');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowStatuses::route('/'),
        ];
    }
}

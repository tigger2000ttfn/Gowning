<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CleanroomResource\Pages;
use App\Models\Cleanroom;
use App\Enums\Capability;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CleanroomResource extends Resource
{
    protected static ?string $model = Cleanroom::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $modelLabel = 'Cleanroom';
    public static function shouldRegisterNavigation(): bool { return false; }

    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && $u->hasCapability(Capability::ManageScheduling));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cleanroom')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('code')->label('Code (optional)')->maxLength(40),
                TextInput::make('sort')->label('Sort Order')->numeric()->default(0),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->placeholder('—'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('sort')->label('Sort')->sortable(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCleanrooms::route('/')];
    }
}

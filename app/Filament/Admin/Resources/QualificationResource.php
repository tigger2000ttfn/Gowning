<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\QualificationResource\Pages;
use App\Models\Qualification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Enums\QualificationStatus;

class QualificationResource extends Resource
{
    protected static ?string $model = Qualification::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Personnel & Qualifications';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('personnel.employee_id')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('personnel.full_name')->label('Name')->searchable(['personnel.first_name', 'personnel.last_name']),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($s) => $s?->label()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($s) => $s?->label())
                    ->color(fn ($s) => $s?->color() ?? 'gray'),
                TextColumn::make('runs_completed')->label('Passes')
                    ->formatStateUsing(fn ($state, $record) => "{$state} / {$record->runs_required}"),
                TextColumn::make('qualified_date')->date()->placeholder('—')->sortable(),
                TextColumn::make('due_date')->label('Due')->date()->placeholder('—')->sortable()
                    ->color(fn ($record) => $record->isPastDue() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(QualificationStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
            ])
            ->defaultSort('due_date');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQualifications::route('/'),
            'view' => Pages\ViewQualification::route('/{record}'),
        ];
    }

    public static function canCreate(): bool { return false; } // engine-managed
}

<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\NonConformanceResource\Pages;
use App\Models\NonConformance;
use App\Models\Personnel;
use App\Enums\Capability;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class NonConformanceResource extends Resource
{
    protected static ?string $model = NonConformance::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $modelLabel = 'Non-Conformance';
    protected static ?string $pluralModelLabel = 'Non-Conformances';
    protected static ?string $navigationLabel = 'Non-Conformances';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 5;

    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return false; } // moved to Manage menu
    public static function canViewAny(): bool { return static::allowed(); }
    public static function canCreate(): bool { return static::allowed(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Non-Conformance')->columns(2)->schema([
                TextInput::make('nc_number')->label('NC Number')
                    ->helperText('Auto-generated on creation. Editable if you need to align it with TrackWise.'),
                Select::make('personnel_id')->label('Person')
                    ->options(fn () => Personnel::orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->full_name])->all())
                    ->searchable(),
                TextInput::make('trackwise_id')->label('TrackWise ID')
                    ->placeholder('TrackWise NC reference')
                    ->helperText('Link/reference only, the record lives in TrackWise.'),
                Select::make('nc_type')->label('Type')->options([
                    'failed_run' => 'Failed Run',
                    'mold_hit' => 'Mold Hit',
                    'bacteria_hit' => 'Bacteria Hit',
                    'other' => 'Other',
                ])->default('failed_run')->required()->live(),
                Select::make('status')->options([
                    'open' => 'Open', 'investigating' => 'Investigating', 'closed' => 'Closed',
                ])->default('open')->required(),
            ]),
            Section::make('Trending Detail (for mold / bacteria)')->columns(3)->schema([
                TextInput::make('organism')->label('Organism')->placeholder('e.g. Aspergillus, Staph'),
                TextInput::make('site')->label('Sampling Site'),
                TextInput::make('cfu_count')->label('CFU Count')->numeric()->minValue(0)
                    ->helperText('Record even if sub-threshold, for trending.'),
                Toggle::make('over_threshold')->label('Over Action Limit'),
                DatePicker::make('observed_date')->native(false)->displayFormat('d-M-Y')->label('Observed Date')->default(now()),
                Select::make('assigned_to')->label('Assigned To')
                    ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id')->all())->searchable(),
            ]),
            Section::make('Summary')->schema([
                Textarea::make('summary')->label('Brief Summary')->rows(2)
                    ->helperText('Short note only, not a transcription of the TrackWise record.')->columnSpanFull(),
            ]),
            Section::make('Attachments')->icon('heroicon-o-paper-clip')->schema([
                \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('evidence')
                    ->collection('evidence')
                    ->multiple()
                    ->downloadable()
                    ->reorderable()
                    ->helperText('Plate photos, lab reports, or supporting documents. Stored as part of the GMP record.')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nc_number')->label('NC #')->weight('bold')->searchable()->sortable(),
                TextColumn::make('trackwise_id')->label('TrackWise')->placeholder('-')->searchable(),
                TextColumn::make('personnel.full_name')->label('Person')->searchable(),
                TextColumn::make('nc_type')->label('Type')->badge()->formatStateUsing(fn ($state) => \Illuminate\Support\Str::title(str_replace('_', ' ', $state)))
                    ->color(fn ($state) => match ($state) { 'failed_run' => 'danger', 'mold_hit' => 'warning', 'bacteria_hit' => 'warning', default => 'gray' }),
                TextColumn::make('organism')->placeholder('-')->toggleable(),
                TextColumn::make('cfu_count')->label('CFU')->placeholder('-'),
                IconColumn::make('over_threshold')->label('Over Limit')->boolean(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'open' => 'danger', 'investigating' => 'warning', 'closed' => 'success', default => 'gray' }),
                TextColumn::make('observed_date')->date()->sortable()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('nc_type')->options([
                    'failed_run' => 'Failed Run', 'mold_hit' => 'Mold Hit', 'bacteria_hit' => 'Bacteria Hit', 'other' => 'Other',
                ]),
                SelectFilter::make('status')->options(['open' => 'Open', 'investigating' => 'Investigating', 'closed' => 'Closed']),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('observed_date', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNonConformances::route('/')];
    }
}

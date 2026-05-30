<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TrainingClassResource\Pages;
use App\Filament\Admin\Resources\TrainingClassResource\RelationManagers;
use App\Models\TrainingClass;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput as FormTextInput;
use App\Models\ClassSession;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class TrainingClassResource extends Resource
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function shouldRegisterNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    protected static ?string $model = TrainingClass::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|\UnitEnum|null $navigationGroup = 'Classroom';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Gowning Class';
    protected static ?string $pluralModelLabel = 'Gowning Classes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Class Details')->icon('heroicon-o-academic-cap')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('code')->label('Class Code')->maxLength(50),
                TextInput::make('category')->label('Category')->maxLength(80)
                    ->placeholder('e.g. Aseptic Gowning'),
                Toggle::make('is_gowning_prerequisite')->label('Counts As Gowning Prerequisite')->default(true),
                Toggle::make('is_published')->label('Published (Visible On Public Site)')->default(true),
                Textarea::make('description')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Defaults & Validity')->icon('heroicon-o-cog-6-tooth')->columns(2)->schema([
                FormTextInput::make('default_capacity')->label('Default Capacity')->numeric()->minValue(1)->default(20),
                FormTextInput::make('duration_minutes')->label('Duration (Minutes)')->numeric()->minValue(1),
                FormTextInput::make('default_location')->label('Default Location')->maxLength(255),
                FormTextInput::make('default_instructor')->label('Default Instructor')->maxLength(255),
                FormTextInput::make('validity_months')->label('Completion Valid For (Months)')->numeric()->minValue(1)
                    ->helperText('Leave blank if the class completion does not expire.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->icon('heroicon-m-user')->searchable()->sortable()->weight('bold'),
                TextColumn::make('code')->label('Code')->badge()->color('gray'),
                TextColumn::make('sessions_count')->counts('sessions')->label('Sessions')->badge(),
                IconColumn::make('is_gowning_prerequisite')->label('Prereq')->boolean(),
                IconColumn::make('is_published')->label('Published')->boolean(),
            ])
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                Action::make('generate')
                    ->label('Generate Sessions')
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->modalHeading('Generate Recurring Sessions')
                    ->modalWidth('lg')
                    ->schema([
                        Select::make('pattern')->label('Repeat')->options([
                            'weekly' => 'Weekly', 'biweekly' => 'Bi-weekly (every 2 weeks)', 'monthly' => 'Monthly',
                        ])->default('weekly')->required(),
                        Select::make('weekday')->label('Day Of Week')->options([
                            1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',0=>'Sunday',
                        ])->default(2)->required()
                            ->helperText('For monthly, uses this weekday of the first matching week each month.'),
                        DatePicker::make('start_date')->label('Start Date')->required()->native(false),
                        DatePicker::make('end_date')->label('End Date')->required()->native(false),
                        TimePicker::make('start_time')->seconds(false),
                        TimePicker::make('end_time')->seconds(false),
                        FormTextInput::make('location')->maxLength(255),
                        FormTextInput::make('instructor')->maxLength(255),
                        FormTextInput::make('capacity')->numeric()->default(20)->minValue(1)->required(),
                    ])
                    ->action(function (TrainingClass $record, array $data) {
                        $start = Carbon::parse($data['start_date']);
                        $end = Carbon::parse($data['end_date']);
                        $weekday = (int) $data['weekday'];
                        // advance start to the first matching weekday
                        $cursor = $start->copy();
                        while ($cursor->dayOfWeek !== $weekday) { $cursor->addDay(); }
                        $created = 0; $guard = 0;
                        while ($cursor->lte($end) && $guard < 400) {
                            $guard++;
                            ClassSession::firstOrCreate(
                                ['training_class_id' => $record->id, 'session_date' => $cursor->toDateString()],
                                [
                                    'start_time' => $data['start_time'] ?? null,
                                    'end_time' => $data['end_time'] ?? null,
                                    'location' => $data['location'] ?? null,
                                    'instructor' => $data['instructor'] ?? null,
                                    'capacity' => $data['capacity'],
                                    'status' => 'open',
                                ],
                            );
                            $created++;
                            $cursor = match ($data['pattern']) {
                                'weekly' => $cursor->addWeek(),
                                'biweekly' => $cursor->addWeeks(2),
                                'monthly' => $cursor->addMonthNoOverflow()->startOfMonth()->next($weekday),
                                default => $cursor->addWeek(),
                            };
                        }
                        Notification::make()->success()
                            ->title('Sessions generated')
                            ->body("{$created} sessions created for {$record->name}.")
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\SessionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingClasses::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PersonnelResource\Pages;
use App\Models\Personnel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PersonnelResource extends Resource
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManagePersonnel));
    }
    public static function shouldRegisterNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManagePersonnel));
    }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManagePersonnel));
    }
    protected static ?string $model = Personnel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 8;
    protected static ?string $recordTitleAttribute = 'employee_id';
    protected static ?string $modelLabel = 'Person';
    protected static ?string $pluralModelLabel = 'Personnel';
    protected static ?string $navigationLabel = 'Personnel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Identity')->icon('heroicon-o-identification')
                    ->description('Who they are')
                    ->columns(2)
                    ->schema([
                        TextInput::make('employee_id')->label('Employee ID')->required()->unique(ignoreRecord: true),
                        Toggle::make('is_active')->label('Active')->default(true),
                        TextInput::make('first_name')->required(),
                        TextInput::make('last_name')->required(),
                        TextInput::make('email')->email(),
                        TextInput::make('phone')->tel(),
                        TextInput::make('badge_id')->label('Badge ID'),
                        DatePicker::make('hire_date')->label('Hire Date'),
                    ]),
                Step::make('Assignment')->icon('heroicon-o-link')
                    ->description('Department, role, shift')
                    ->columns(2)
                    ->schema([
                        Select::make('department')->label('Department')
                            ->options(fn () => \App\Models\Department::where('is_active', true)->orderBy('sort')->pluck('name', 'name')->all())
                            ->searchable()->createOptionForm([TextInput::make('name')->required()])
                            ->createOptionUsing(fn (array $data) => \App\Models\Department::create($data + ['is_active' => true])->name),
                        Select::make('job_title')->label('Job Title')
                            ->options(fn () => \App\Models\JobTitle::where('is_active', true)->orderBy('sort')->pluck('name', 'name')->all())
                            ->searchable()->createOptionForm([TextInput::make('name')->required()])
                            ->createOptionUsing(fn (array $data) => \App\Models\JobTitle::create($data + ['is_active' => true])->name),
                        TextInput::make('shift'),
                        TextInput::make('supervisor'),
                        Textarea::make('notes')->rows(2)->columnSpanFull(),
                    ]),
                Step::make('Qualification Setup')->icon('heroicon-o-shield-check')
                    ->description('Seed current status (manual first-time setup before import)')
                    ->schema([
                        Section::make()
                            ->columns(2)
                            ->relationship('qualification')
                            ->schema([
                                Select::make('type')->label('Qualification Type')
                                    ->options(['initial' => 'Initial', 'annual' => 'Annual'])->default('initial'),
                                Select::make('status')->label('Current Status')
                                    ->options([
                                        'pending' => 'Pending', 'in_progress' => 'In Progress',
                                        'qualified' => 'Qualified', 'lapsed' => 'Lapsed',
                                    ])->default('pending'),
                                Select::make('workflow_stage')->label('Workflow Stage')
                                    ->options(collect(\App\Enums\WorkflowStage::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all())
                                    ->default('class_pending')
                                    ->helperText('Where they currently sit in the pipeline.'),
                                DatePicker::make('due_date')->label('Qualification Due Date')
                                    ->helperText('Calculated from the last qualifying run (Date Last Qualified + cycle). Set Date Last Qualified to drive this.'),
                                TextInput::make('runs_required')->label('Runs Required')->numeric()->default(3),
                                TextInput::make('runs_completed')->label('Runs Already Completed')->numeric()->default(0)
                                    ->helperText('Successful runs on file for the current cycle. Saving creates that many run-history entries dated within the last year, so you do not back-enter older runs. Entering anyone as qualified/in-progress marks their gowning class as on file.'),
                                DatePicker::make('qualified_date')->label('Date Last Qualified'),
                                Toggle::make('class_on_file')->label('Gowning Class Already Completed')
                                    ->helperText('On = class is on file (will not be flagged to retake). Set for anyone who already took it.')
                                    ->live(),
                                DatePicker::make('class_on_file_date')->label('Class Completion Date')
                                    ->visible(fn ($get) => $get('class_on_file')),
                            ]),
                    ]),
            ])->columnSpanFull()->skippable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_id')->icon('heroicon-m-identification')->label('Employee ID')->searchable()->sortable(),
                TextColumn::make('full_name')->label('Name')->searchable(['first_name', 'last_name']),
                TextColumn::make('department')->icon('heroicon-m-building-office-2')->searchable()->toggleable(),
                TextColumn::make('qualification.status')
                    ->label('Qualification')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'None')
                    ->color(fn ($state) => $state?->color() ?? 'gray'),
                TextColumn::make('qualification.due_date')->label('Due')->date()->sortable()->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                \Filament\Tables\Filters\SelectFilter::make('department')
                    ->options(fn () => \App\Models\Department::orderBy('name')->pluck('name', 'name')->all())
                    ->searchable()->label('Department'),
            ])
            ->defaultSort('last_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonnel::route('/'),
            'create' => Pages\CreatePersonnel::route('/create'),
            'edit' => Pages\EditPersonnel::route('/{record}/edit'),
        ];
    }
}

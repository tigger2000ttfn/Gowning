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
use Filament\Forms\Components\Repeater;
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
                    ->columns(2)
                    ->schema([
                        TextInput::make('employee_id')->label('Employee ID')->required()->unique(ignoreRecord: true),
                        Toggle::make('is_active')->label('Active')->default(true),
                        TextInput::make('first_name')->label('First Name')->required(),
                        TextInput::make('last_name')->label('Last Name')->required(),
                        TextInput::make('email')->label('Email Address')->email(),
                        TextInput::make('phone')->label('Phone')->tel(),
                        TextInput::make('lims_username')->label('LIMS Username'),
                        TextInput::make('badge_id')->label('Badge ID'),
                        DatePicker::make('hire_date')->native(false)->displayFormat('d-M-Y')->label('Hire Date'),
                    ]),
                Step::make('Assignment')->icon('heroicon-o-link')
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
                        Select::make('user_id')->label('Linked System User')
                            ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->preload()->placeholder('Not linked'),
                        Textarea::make('notes')->rows(2)->columnSpanFull(),
                    ]),
                Step::make('Onboarding')->icon('heroicon-o-rocket-launch')
                    ->description('Set the qualification target so this person enters the workflow.')
                    ->visibleOn('create')
                    ->schema([
                        Section::make('Gowning Qualification Target')
                            ->columns(2)
                            ->schema([
                                Select::make('onboard_type')->label('Qualification Type')
                                    ->options(['initial' => 'Initial Gowning Qualification (3 runs)', 'annual' => 'Requalification Transfer (already qualified)'])
                                    ->default('initial')->required()->live()
                                    ->helperText('Initial = starts at the class. Transfer = already qualified elsewhere, tracked toward their next requal.')
                                    ->columnSpanFull(),
                                DatePicker::make('onboard_due_date')->native(false)->displayFormat('d-M-Y')
                                    ->label(fn ($get) => $get('onboard_type') === 'annual' ? 'Next Requalification Due Date' : 'Initial Qualification Must Be Completed By')
                                    ->required()
                                    ->helperText(fn ($get) => $get('onboard_type') === 'annual'
                                        ? 'When their next requal is due.'
                                        : 'The date their initial gowning qualification needs to be done by.')
                                    ->columnSpanFull(),
                                Toggle::make('onboard_class_done')->label('Already Took The Gowning Class')
                                    ->visible(fn ($get) => $get('onboard_type') === 'annual')
                                    ->live()->columnSpanFull(),
                                DatePicker::make('onboard_class_date')->native(false)->displayFormat('d-M-Y')->label('Class Completion Date')
                                    ->visible(fn ($get) => $get('onboard_type') === 'annual' && $get('onboard_class_done'))
                                    ->required(fn ($get) => $get('onboard_type') === 'annual' && $get('onboard_class_done'))
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Step::make('Qualification Setup')->icon('heroicon-o-shield-check')
                    ->visibleOn('edit')
                    ->description('Record the classroom approval, then build the run history. The next due date is driven off the QA approval date.')
                    ->schema([
                        Section::make('Classroom History')
                            ->columns(2)
                            ->relationship('qualification')
                            ->disabled(function ($record) {
                                $isQualified = ($record?->qualification?->status?->value ?? $record?->qualification?->status) === 'qualified';
                                $u = \Illuminate\Support\Facades\Auth::user();
                                $isAdmin = $u && ($u->hasCapability(\App\Enums\Capability::ManageUsers) || $u->hasCapability(\App\Enums\Capability::SystemSettings));
                                return $isQualified && ! $isAdmin;
                            })
                            ->schema([
                                Toggle::make('class_on_file')->label('Classroom Status Approved')
                                    ->helperText('On = Gowning Class / OJT Is QA-Approved And On File.')
                                    ->live()->columnSpanFull(),
                                DatePicker::make('class_on_file_date')->native(false)->displayFormat('d-M-Y')->label('Class Date')
                                    ->visible(fn ($get) => $get('class_on_file')),
                            ]),
                        Section::make('Run History')
                            ->description('The qualification record plus every run. The next due date always calculates from the final QA qualification approval date, not the run date.')
                            ->schema([
                                Section::make('Qualification Record')
                                    ->columns(2)
                                    ->relationship('qualification')
                                    ->schema([
                                        Select::make('type')->label('Qualification Type')
                                            ->options(['initial' => 'Initial', 'annual' => 'Annual (Requalification)'])->default('initial')
                                            ->helperText('Initial = 3 Runs · Annual = 1 Run.'),
                                        Select::make('status')->label('Current Status')->live()
                                            ->options([
                                                'pending' => 'Pending', 'in_progress' => 'In Progress',
                                                'qualified' => 'Qualified', 'lapsed' => 'Lapsed',
                                            ])->default('pending'),
                                        Select::make('workflow_stage')->label('Workflow Stage')
                                            ->options(collect(\App\Enums\WorkflowStage::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all())
                                            ->default('class_pending'),
                                        DatePicker::make('qualified_date')->native(false)->displayFormat('d-M-Y')->label('Date Last Qualified (QA Approval)')
                                            ->live()
                                            ->visible(fn ($get) => $get('status') === 'qualified')
                                            ->afterStateUpdated(function ($state, $set) {
                                                if (! $state) return;
                                                $cycle = (int) \App\Models\Setting::get('cycle_months', 12);
                                                $set('due_date', \Illuminate\Support\Carbon::parse($state)->addMonths($cycle)->toDateString());
                                            })
                                            ->helperText('Set Only When QA Has Qualified This Person · Drives The Next Due Date.'),
                                        DatePicker::make('due_date')->native(false)->displayFormat('d-M-Y')->label('Next Qualification Due Date')
                                            ->visible(fn ($get) => $get('status') === 'qualified')
                                            ->helperText('Auto-Calculates From The QA Approval Date + Cycle Length.'),
                                    ]),
                                Repeater::make('runs')
                                    ->label('Runs')
                                    ->relationship('runs')
                                    ->columns(3)
                                    ->itemLabel(fn (array $state): ?string => ($state['run_date'] ?? 'New Run') . ' · ' . ucfirst($state['result'] ?? 'pending') . ' · ' . ucfirst($state['cycle_type'] ?? 'initial'))
                                    ->addActionLabel('Add A Run')
                                    ->collapsible()
                                    ->collapsed()
                                    ->defaultItems(0)
                                    ->reorderable(false)
                                    ->schema([
                                        DatePicker::make('run_date')->native(false)->displayFormat('d-M-Y')->label('Run Date')->required(),
                                        Select::make('cycle_type')->label('Cycle')
                                            ->options(['initial' => 'Initial', 'annual' => 'Annual'])
                                            ->default('initial'),
                                        Select::make('result')->label('Result / Status')->live()
                                            ->options(['pending' => 'Pending (Not Complete)', 'pass' => 'Pass', 'fail' => 'Fail'])
                                            ->default('pending')->required()
                                            ->helperText('Leave Pending Until The Run Is Complete (QA Approved). Set Pass/Fail Once Results Are In.'),
                                        TextInput::make('lims_worklist_id')->label('LIMS Worklist')->placeholder('EM-...'),
                                        TextInput::make('notes')->label('Notes')->columnSpan(2),
                                    ]),
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
                TextColumn::make('lims_username')->label('LIMS Username')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('qualification.status')
                    ->label('Qualification')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'None')
                    ->color(fn ($state) => $state?->color() ?? 'gray'),
                TextColumn::make('qualification.qualified_date')->label('Last Qualified')->date()->sortable()->placeholder('—'),
                TextColumn::make('qualification.due_date')->label('Next Due')->date()->sortable()->placeholder('—')
                    ->color(fn ($record) => $record->qualification?->isPastDue() ? 'danger' : null),
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

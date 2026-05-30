<?php

namespace App\Filament\Admin\Pages;

use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class Settings extends Page implements HasForms
{
    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageUsers));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageUsers));
    }
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 9;
    protected static ?string $title = 'Qualification Settings';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'initial_runs_required' => (int) Setting::get('initial_runs_required', 3),
            'annual_runs_required'  => (int) Setting::get('annual_runs_required', 1),
            'cycle_months'          => (int) Setting::get('cycle_months', 12),
            'grace_days'            => (int) Setting::get('grace_days', 0),
            'class_required'        => (bool) Setting::get('class_required', true),
            'class_repeats_annually'=> (bool) Setting::get('class_repeats_annually', false),
            'self_register_open'    => (bool) Setting::get('self_register_open', true),
            'auto_approve'          => (bool) Setting::get('auto_approve', false),
            'incubation_days'       => (int) Setting::get('incubation_days', 8),
            'runs_per_day_capacity' => (int) Setting::get('runs_per_day_capacity', 6),
            'auto_schedule'         => (bool) Setting::get('auto_schedule', true),
            'auto_schedule_weeks_out' => (int) Setting::get('auto_schedule_weeks_out', 2),
            'lapsed_runs_required'  => (int) Setting::get('lapsed_runs_required', 3),
            'allow_self_reschedule' => (bool) Setting::get('allow_self_reschedule', true),
            'sampling_sites'        => Setting::get('sampling_sites', 'Fingertips, Chest, Forearms'),
            'require_qa_signoff'    => (bool) Setting::get('require_qa_signoff', true),
            'esig_required'         => (bool) Setting::get('esig_required', true),
            'notify_days_before'    => Setting::get('notify_days_before', '60,30,7'),
            'email_enabled'         => (bool) Setting::get('email_enabled', false),
            'mail_from_address'     => Setting::get('mail_from_address', ''),
            'mail_from_name'        => Setting::get('mail_from_name', 'MATC Gowning Qualification'),
            'mail_host'             => Setting::get('mail_host', ''),
            'mail_port'             => Setting::get('mail_port', ''),
            'mail_username'         => Setting::get('mail_username', ''),
            'org_name'              => Setting::get('org_name', 'MATC, Astellas'),
            'site_name'             => Setting::get('site_name', 'Manufacturing Technology Center'),
            'qcm_manager_id'        => Setting::get('qcm_manager_id'),
            'qa_manager_id'         => Setting::get('qa_manager_id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Qualification Rules')->icon('heroicon-o-adjustments-horizontal')->columns(2)->schema([
                    TextInput::make('initial_runs_required')->label('Runs Required For Initial Qualification')
                        ->numeric()->minValue(1)->required(),
                    TextInput::make('annual_runs_required')->label('Runs Required For Annual Requalification')
                        ->numeric()->minValue(1)->required(),
                    TextInput::make('cycle_months')->label('Qualification Valid For (Months)')
                        ->numeric()->minValue(1)->required(),
                    TextInput::make('grace_days')->label('Grace Window After Due Date (Days)')
                        ->numeric()->minValue(0)->required()
                        ->helperText('Days past due before a qualification is treated as lapsed.'),
                ]),
                Section::make('Class & Access')->icon('heroicon-o-lock-closed')->columns(2)->schema([
                    Toggle::make('class_required')->label('Gowning Class Required Before Initial Runs'),
                    Toggle::make('class_repeats_annually')->label('Gowning Class Repeats Annually')
                        ->helperText('Off = taken once at initial qualification.'),
                    Toggle::make('self_register_open')->label('Public Self-registration Open'),
                    Toggle::make('auto_approve')->label('Auto-approve New Registrations')
                        ->helperText('Off is recommended for Part 11 (admin approves each account).'),
                ]),
                Section::make('Sampling & Incubation')->icon('heroicon-o-beaker')->columns(2)->schema([
                    TextInput::make('incubation_days')->label('Incubation Period (Days)')
                        ->numeric()->minValue(1)->required()
                        ->helperText('Days plates incubate before results are released.'),
                    TextInput::make('sampling_sites')->label('Sampling Sites')
                        ->helperText('Comma-separated body sites sampled per run.'),
                ]),
                Section::make('Auto-Scheduling')->icon('heroicon-o-calendar-days')->columns(2)->schema([
                    Toggle::make('auto_schedule')->label('Auto-schedule Qualification Runs')
                        ->helperText('Automatically book people who need runs into the next available run day.'),
                    TextInput::make('runs_per_day_capacity')->label('Max People Per Run Day')
                        ->numeric()->minValue(1)->required()
                        ->helperText('Capacity cap for a qualification run day.'),
                    TextInput::make('auto_schedule_weeks_out')->label('Schedule Lead Time (Weeks)')
                        ->numeric()->minValue(0)->required()
                        ->helperText('How far out the first auto-booked date should be.'),
                    TextInput::make('lapsed_runs_required')->label('Runs For Lapsed Requalification')
                        ->numeric()->minValue(1)
                        ->helperText('How many runs a lapsed (overdue) person must redo. Default 3 (treat as initial).'),
                    Toggle::make('allow_self_reschedule')->label('Allow Operator Self-reschedule')
                        ->helperText('Let operators move their own run from My Qualification.'),
                ]),
                Section::make('Quality / Part 11')->icon('heroicon-o-shield-check')->columns(2)->schema([
                    Toggle::make('require_qa_signoff')->label('Require QA Sign-off To Complete')
                        ->helperText('A run is only Completed after QA approval.'),
                    Toggle::make('esig_required')->label('Require Electronic Signature On QA Sign-off'),
                ]),
                Section::make('Notifications')->icon('heroicon-o-bell')->schema([
                    TextInput::make('notify_days_before')->label('Due-date Reminder Days')
                        ->helperText('Comma-separated days before due to remind (e.g. 60,30,7).'),
                ]),
                Section::make('Email Delivery')->icon('heroicon-o-at-symbol')->columns(2)
                    ->description('Outbound mail relay. Emails queue until this is reachable, then send automatically.')
                    ->schema([
                        Toggle::make('email_enabled')->label('Email Sending Enabled')
                            ->helperText('Off = notifications stay in-app only and emails keep queuing.'),
                        TextInput::make('mail_from_address')->label('From Address')->email()
                            ->placeholder('gowning@matcastellas.com'),
                        TextInput::make('mail_from_name')->label('From Name')
                            ->placeholder('MATC Gowning Qualification'),
                        TextInput::make('mail_host')->label('SMTP Host')->placeholder('localhost or relay host'),
                        TextInput::make('mail_port')->label('SMTP Port')->numeric()->placeholder('25 / 587'),
                        TextInput::make('mail_username')->label('SMTP Username')->placeholder('(if required)'),
                    ]),
                Section::make('Organization')->icon('heroicon-o-building-office-2')->columns(2)->schema([
                    TextInput::make('org_name')->label('Organization Name'),
                    TextInput::make('site_name')->label('Site Name'),
                ]),
                Section::make('Teams')->icon('heroicon-o-user-group')->columns(2)
                    ->description('Assign the manager for each team. Add members by setting a person\'s Team on their user record (Users & Approvals).')
                    ->schema([
                        Select::make('qcm_manager_id')->label('QC Micro Team Manager')
                            ->options(fn () => \App\Models\User::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->placeholder('Unassigned'),
                        Select::make('qa_manager_id')->label('QA Team Manager')
                            ->options(fn () => \App\Models\User::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->placeholder('Unassigned'),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }
        Notification::make()->success()->title('Settings saved')->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save Settings')->submit('save')];
    }
}

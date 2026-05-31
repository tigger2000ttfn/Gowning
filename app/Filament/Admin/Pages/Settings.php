<?php

namespace App\Filament\Admin\Pages;

use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
            'requal_window_days'    => (int) Setting::get('requal_window_days', 30),
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
            'allow_self_request_run' => (bool) Setting::get('allow_self_request_run', true),
            'sampling_sites'        => Setting::get('sampling_sites', 'Fingertips, Chest, Forearms'),
            'require_qa_signoff'    => (bool) Setting::get('require_qa_signoff', true),
            'esig_required'         => (bool) Setting::get('esig_required', true),
            'password_expiry_enabled' => (bool) Setting::get('password_expiry_enabled', false),
            'password_expiry_days'  => Setting::get('password_expiry_days', '90'),
            'lockout_enabled'       => (bool) Setting::get('lockout_enabled', false),
            'lockout_threshold'     => Setting::get('lockout_threshold', '5'),
            'lockout_minutes'       => Setting::get('lockout_minutes', '15'),
            'attendance_form_document_no' => Setting::get('attendance_form_document_no', ''),
            'attendance_form_revision_no' => Setting::get('attendance_form_revision_no', ''),
            'attendance_form_title'       => Setting::get('attendance_form_title', ''),
            'notify_days_before'    => Setting::get('notify_days_before', '60,30,7'),
            'email_enabled'         => (bool) Setting::get('email_enabled', false),
            'board_group_by'        => Setting::get('board_group_by', 'none'),
            'board_show_failed'     => (bool) Setting::get('board_show_failed', true),
            'mail_from_address'     => Setting::get('mail_from_address', ''),
            'mail_from_name'        => Setting::get('mail_from_name', 'MATC Gowning Qualification'),
            'mail_host'             => Setting::get('mail_host', ''),
            'mail_port'             => Setting::get('mail_port', ''),
            'mail_username'         => Setting::get('mail_username', ''),
            'mail_password'         => '', // never prefill the password field
            'mail_encryption'       => Setting::get('mail_encryption', 'tls'),
            'org_name'              => Setting::get('org_name', 'MATC, Astellas'),
            'site_name'             => Setting::get('site_name', 'Manufacturing Technology Center'),
            'qcm_manager_id'        => Setting::get('qcm_manager_id'),
            'qa_manager_id'         => Setting::get('qa_manager_id'),
            // Class Board lane labels (stored in workflow_statuses, domain=class).
            'lane_class_signed_up'    => \App\Models\WorkflowStatus::labelFor('class', 'signed_up', 'Scheduled'),
            'lane_class_attended'     => \App\Models\WorkflowStatus::labelFor('class', 'attended', 'Attended'),
            'lane_class_qcm_reviewed' => \App\Models\WorkflowStatus::labelFor('class', 'qcm_reviewed', 'QCM Reviewed'),
            'lane_class_pending_qa'   => \App\Models\WorkflowStatus::labelFor('class', 'pending_qa', 'Pending QA'),
            'lane_class_completed'    => \App\Models\WorkflowStatus::labelFor('class', 'completed', 'QA Approved'),
            'lane_class_no_show'      => \App\Models\WorkflowStatus::labelFor('class', 'no_show', 'No-Show'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make('Rules')->icon('heroicon-o-adjustments-horizontal')->schema([
                Section::make('Qualification Rules')->icon('heroicon-o-adjustments-horizontal')->columns(2)->schema([
                    TextInput::make('initial_runs_required')->label('Runs Required For Initial Qualification')
                        ->numeric()->minValue(1)->required(),
                    TextInput::make('annual_runs_required')->label('Runs Required For Annual Requalification')
                        ->numeric()->minValue(1)->required(),
                    TextInput::make('cycle_months')->label('Qualification Valid For (Months)')
                        ->numeric()->minValue(1)->required(),
                    TextInput::make('grace_days')->label('Lapse Window (Days Relative To Due Date)')
                        ->numeric()->required()
                        ->helperText('When a qualified person lapses into requalification, relative to their due date. 0 = lapses the day after the due date. Positive = grace period (e.g. 7 = one week after). Negative = lapses early (e.g. -1 = the day before the due date). Adjust to match policy.'),
                    TextInput::make('requal_window_days')->label('Requalification Kick-Off (Days Before Due)')
                        ->numeric()->minValue(0)->required()
                        ->helperText('How many days before the due date a qualified person is automatically started on their requalification. They stay Qualified (cleanroom access intact) until the due date; this just opens their requal run early so they can complete it in time. Default 30.'),
                ]),
                Section::make('Class & Access')->icon('heroicon-o-lock-closed')->columns(2)->schema([
                    Toggle::make('class_required')->label('Gowning Class Required Before Initial Runs'),
                    Toggle::make('class_repeats_annually')->label('Gowning Class Repeats Annually')
                        ->helperText('Off = taken once at initial qualification.'),
                    Toggle::make('self_register_open')->label('Public Self-registration Open'),
                    Toggle::make('auto_approve')->label('Auto-approve New Registrations')
                        ->helperText('Off is recommended for Part 11 (admin approves each account).'),
                ]),
]),
                Tab::make('Workflow')->icon('heroicon-o-beaker')->schema([
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
                    Toggle::make('allow_self_reschedule')->label('Allow Operator Self-Reschedule')
                        ->helperText('Let Operators Move Their Own Run From My Qualification.'),
                    Toggle::make('allow_self_request_run')->label('Allow Operator Self-Request Run')
                        ->helperText('Let Operators Book Their Own Run From My Qualification (After The Class Is On File).'),
                ]),
]),
                Tab::make('Quality')->icon('heroicon-o-shield-check')->schema([
                Section::make('Quality / Part 11')->icon('heroicon-o-shield-check')->columns(2)->schema([
                    Toggle::make('require_qa_signoff')->label('Require QA Sign-off To Complete')
                        ->helperText('A run is only Completed after QA approval.'),
                    Toggle::make('esig_required')->label('Require Electronic Signature On QA Sign-off'),
                ]),
                Section::make('Password & Account Security')->icon('heroicon-o-lock-closed')->columns(2)->schema([
                    Toggle::make('password_expiry_enabled')->label('Enable Password Expiry')
                        ->live()
                        ->helperText('Off By Default. When On, Users Must Reset Their Password After The Set Number Of Days.'),
                    TextInput::make('password_expiry_days')->label('Password Expiry (Days)')->numeric()->minValue(1)
                        ->visible(fn ($get) => (bool) $get('password_expiry_enabled'))
                        ->helperText('How Many Days A Password Is Valid Before A Reset Is Required.'),
                    Toggle::make('lockout_enabled')->label('Enable Account Lockout')
                        ->live()
                        ->helperText('Off By Default. When On, Too Many Failed Logins Temporarily Locks The Account.'),
                    TextInput::make('lockout_threshold')->label('Failed Attempts Before Lockout')->numeric()->minValue(1)
                        ->visible(fn ($get) => (bool) $get('lockout_enabled')),
                    TextInput::make('lockout_minutes')->label('Lockout Duration (Minutes)')->numeric()->minValue(1)
                        ->visible(fn ($get) => (bool) $get('lockout_enabled')),
                ]),
                Section::make('Attendance Form (FORM-AST-36513)')->icon('heroicon-o-document-text')->columns(2)
                    ->description('Prefilled onto the Class Training Form. Update these as the controlled document version changes.')
                    ->schema([
                        TextInput::make('attendance_form_document_no')->label('Document #')
                            ->helperText('Controlled document number printed in the Training Item row.'),
                        TextInput::make('attendance_form_revision_no')->label('Revision #'),
                        TextInput::make('attendance_form_title')->label('Title / Description')
                            ->columnSpanFull()
                            ->helperText('Leave blank to use the class name.'),
                    ]),
]),
                Tab::make('Notifications')->icon('heroicon-o-bell')->schema([
                Section::make('Notifications')->icon('heroicon-o-bell')->schema([
                    TextInput::make('notify_days_before')->label('Due-date Reminder Days')
                        ->helperText('Comma-separated days before due to remind (e.g. 60,30,7).'),
                ]),
                Section::make('Board & Display')->icon('heroicon-o-view-columns')->columns(2)
                    ->description('Defaults for the Status Board kanban.')
                    ->schema([
                        \Filament\Forms\Components\Select::make('board_group_by')->label('Default Swimlane Grouping')
                            ->options(['none' => 'None (single board)', 'department' => 'By Department', 'cycle_type' => 'By Cycle Type (Initial / Annual)'])
                            ->default('none')
                            ->helperText('How the Status Board groups cards into horizontal swimlanes by default.'),
                        Toggle::make('board_show_failed')->label('Show Failed Lane')->default(true)
                            ->helperText('Include the off-pipeline Failed column on the board.'),
                    ]),
                Section::make('Class Board Lane Labels')->icon('heroicon-o-rectangle-stack')->columns(2)
                    ->description('The Class Board kanban lane names. These also drive the status pill on each class card.')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('lane_class_signed_up')->label('Lane: Scheduled'),
                        \Filament\Forms\Components\TextInput::make('lane_class_attended')->label('Lane: Attended'),
                        \Filament\Forms\Components\TextInput::make('lane_class_qcm_reviewed')->label('Lane: QCM Reviewed'),
                        \Filament\Forms\Components\TextInput::make('lane_class_pending_qa')->label('Lane: Pending QA'),
                        \Filament\Forms\Components\TextInput::make('lane_class_completed')->label('Lane: QA Approved (final)')
                            ->helperText('The final lane after QA approval. Defaults to "QA Approved".'),
                        \Filament\Forms\Components\TextInput::make('lane_class_no_show')->label('Lane: No-Show'),
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
                        TextInput::make('mail_password')->label('SMTP Password')->password()->revealable()
                            ->placeholder('(leave blank to keep current)')->dehydrated(fn ($state) => filled($state))
                            ->helperText('Stored for the relay. Leave blank to keep the existing password.'),
                        \Filament\Forms\Components\Select::make('mail_encryption')->label('Encryption')
                            ->options(['none' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'])->default('tls'),
                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('sendTest')
                                ->label('Send Test Email')->icon('heroicon-m-paper-airplane')
                                ->action(fn () => $this->sendTestEmail()),
                        ])->columnSpanFull(),
                    ]),
]),
                Tab::make('Organization')->icon('heroicon-o-building-office-2')->schema([
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
                ]),
                ]), // close last Tab schema, then Tabs::tabs()
            ]); // close components
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->uploadTemplateAction('attendance', 'Upload Training Form', 'FORM-AST-36513'),
            $this->uploadTemplateAction('approval', 'Upload QA Approval Form', 'FORM-AST-36749'),
        ];
    }

    protected function uploadTemplateAction(string $key, string $label, string $docNo): Action
    {
        return Action::make('upload_' . $key)
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading($label . ' (' . $docNo . ')')
            ->modalDescription('Replace The Form Template With A Newly Issued Version. The Upload Is Flattened So It Can Be Filled. Note: If The New Version Changes The Layout, The Overlay Positions May Need Adjustment.')
            ->schema([
                FileUpload::make('file')->label('PDF File')->acceptedFileTypes(['application/pdf'])
                    ->required()->storeFiles(false),
            ])
            ->action(function (array $data) use ($key) {
                $upload = $data['file'] ?? null;
                if (is_array($upload)) $upload = reset($upload) ?: null;
                if (! $upload || ! method_exists($upload, 'getRealPath')) {
                    Notification::make()->danger()->title('No File')->send(); return;
                }
                $tmp = $upload->getRealPath();
                $res = app(\App\Services\PdfTemplateStore::class)->store($key, $tmp);
                if (! $res['ok']) {
                    Notification::make()->danger()->title('Upload Failed')->body($res['message'])->send();
                    return;
                }
                if ($res['flattened']) {
                    Notification::make()->success()->title('Template Updated')->body($res['message'])->send();
                } else {
                    Notification::make()->warning()->title('Template Saved (Not Flattened)')->body($res['message'])->persistent()->send();
                }
            });
    }

    public function save(): void
    {
        // Class Board lane labels persist to the workflow_statuses table, not the settings store.
        $laneMap = [
            'lane_class_signed_up' => 'signed_up', 'lane_class_attended' => 'attended',
            'lane_class_qcm_reviewed' => 'qcm_reviewed', 'lane_class_pending_qa' => 'pending_qa',
            'lane_class_completed' => 'completed', 'lane_class_no_show' => 'no_show',
        ];
        foreach ($this->form->getState() as $key => $value) {
            if (isset($laneMap[$key])) {
                $label = trim((string) $value);
                if ($label !== '') {
                    \App\Models\WorkflowStatus::updateOrCreate(
                        ['domain' => 'class', 'key' => $laneMap[$key]],
                        ['label' => $label]
                    );
                }
                continue; // not a Setting
            }
            Setting::put($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }
        \App\Models\WorkflowStatus::flushCache();
        Notification::make()->success()->title('Settings saved')->send();
    }

    public function sendTestEmail(): void
    {
        // persist current form values first so the test uses what's on screen
        $this->save();
        $to = \Illuminate\Support\Facades\Auth::user()?->email;
        if (! $to) {
            Notification::make()->danger()->title('Your account has no email address')->send();
            return;
        }
        try {
            \App\Support\MailConfig::apply(); // bind runtime mail config from settings
            $html = view('emails.layout', [
                'subject' => 'GQS test email',
                'bodyHtml' => '<p style="margin:0 0 14px;">This is a test email from the MATC Gowning Qualification System. If you received it, your mail relay settings are working.</p>',
            ])->render();
            \Illuminate\Support\Facades\Mail::html($html, function ($m) use ($to) {
                $m->to($to)->subject('GQS test email');
            });
            Notification::make()->success()->title('Test email sent')->body('Sent to ' . $to)->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Test email failed')->body($e->getMessage())->send();
        }
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save Settings')->submit('save')];
    }
}

<?php

namespace App\Filament\Admin\Pages;

use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            'self_register_open'    => (bool) Setting::get('self_register_open', true),
            'auto_approve'          => (bool) Setting::get('auto_approve', false),
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
                    Toggle::make('self_register_open')->label('Public Self-registration Open'),
                    Toggle::make('auto_approve')->label('Auto-approve New Registrations')
                        ->helperText('Off is recommended for Part 11 (admin approves each account).'),
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

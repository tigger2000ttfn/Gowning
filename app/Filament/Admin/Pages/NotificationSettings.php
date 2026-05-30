<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NotificationEvent;
use App\Models\NotificationPreference;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class NotificationSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationLabel = 'Notification Settings';
    protected static ?string $title = 'Notification Settings';
    public function getHeading(): string { return ''; }
    public static function shouldRegisterNavigation(): bool { return false; } // in user menu / Manage

    protected string $view = 'filament.pages.notification-settings';

    /** @var array<string, array{in_app:bool,email:bool}> */
    public array $prefs = [];

    public function mount(): void
    {
        $existing = NotificationPreference::where('user_id', Auth::id())->get()->keyBy('event');
        foreach (NotificationEvent::cases() as $e) {
            $row = $existing->get($e->value);
            $this->prefs[$e->value] = [
                'in_app' => $row ? (bool) $row->in_app : $e->defaultInApp(),
                'email'  => $row ? (bool) $row->email : $e->defaultEmail(),
            ];
        }
    }

    public function events(): array
    {
        return collect(NotificationEvent::cases())
            ->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()])->all();
    }

    public function save(): void
    {
        foreach ($this->prefs as $event => $channels) {
            NotificationPreference::updateOrCreate(
                ['user_id' => Auth::id(), 'event' => $event],
                ['in_app' => (bool) ($channels['in_app'] ?? false), 'email' => (bool) ($channels['email'] ?? false)]
            );
        }
        \Filament\Notifications\Notification::make()->success()->title('Notification settings saved')->send();
    }
}

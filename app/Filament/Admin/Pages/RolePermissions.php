<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\RoleCapability;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RolePermissions extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Roles & Permissions';
    protected static ?string $slug = 'roles-permissions';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $title = 'Roles & Permissions';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.role-permissions';

    /** matrix[roleValue][capValue] = bool */
    public array $matrix = [];

    public static function canAccessNavigation(): bool
    {
        $u = Auth::user();
        return (bool) ($u && $u->hasCapability(Capability::ManageRoles));
    }
    public static function shouldRegisterNavigation(): bool { return false; } // in Manage menu
    public static function canViewAny(): bool
    {
        $u = Auth::user();
        return (bool) ($u && $u->hasCapability(Capability::ManageRoles));
    }

    public function mount(): void
    {
        $existing = RoleCapability::all()->groupBy('role')
            ->map(fn ($g) => $g->pluck('capability')->all())->all();

        foreach (Role::cases() as $role) {
            foreach (Capability::cases() as $cap) {
                $this->matrix[$role->value][$cap->value] =
                    in_array($cap->value, $existing[$role->value] ?? [], true);
            }
        }
    }

    public function roles(): array { return Role::cases(); }
    public function capabilities(): array { return Capability::cases(); }

    public function save(): void
    {
        foreach (Role::cases() as $role) {
            // Super User is always all-on and cannot be edited
            foreach (Capability::cases() as $cap) {
                $want = $role->isSuperUser() ? true : (bool) ($this->matrix[$role->value][$cap->value] ?? false);
                $has = RoleCapability::where('role', $role->value)->where('capability', $cap->value)->exists();
                if ($want && ! $has) {
                    RoleCapability::create(['role' => $role->value, 'capability' => $cap->value]);
                } elseif (! $want && $has) {
                    RoleCapability::where('role', $role->value)->where('capability', $cap->value)->delete();
                }
            }
        }
        RoleCapability::flush();
        Notification::make()->success()->title('Permissions Saved')->send();
    }
}

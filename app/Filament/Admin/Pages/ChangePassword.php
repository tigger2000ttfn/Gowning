<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Forced password-change page. A user whose must_change_password flag is set is
 * redirected here by the EnsurePasswordChanged middleware and cannot reach the rest
 * of the app until they set a new password.
 */
class ChangePassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';
    protected static ?string $title = 'Change Your Password';
    protected string $view = 'filament.pages.change-password';

    public string $current = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function save(): void
    {
        $user = Auth::user();
        if (! $user) return;

        $min = (int) \App\Models\Setting::get('password_min_length', 8);
        $this->validate([
            'current' => ['required'],
            'password' => ['required', 'string', 'min:' . max(8, $min), 'confirmed', 'different:current'],
        ], [
            'password.confirmed' => 'The new password and its confirmation do not match.',
            'password.different' => 'The new password must be different from your current password.',
            'password.min' => 'The new password must be at least ' . max(8, $min) . ' characters.',
        ]);

        if (! Hash::check($this->current, $user->password)) {
            Notification::make()->danger()->title('Incorrect Current Password')->send();
            return;
        }

        $user->password = Hash::make($this->password);   // saving hook stamps password_changed_at + clears lockout
        $user->must_change_password = false;
        $user->save();

        $this->current = $this->password = $this->password_confirmation = '';

        Notification::make()->success()->title('Password Updated')->body('Your password has been changed.')->send();
        $this->redirect(filament()->getCurrentPanel()->getUrl());
    }
}

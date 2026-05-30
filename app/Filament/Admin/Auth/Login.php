<?php

namespace App\Filament\Admin\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Setting;

/**
 * Custom login page so we control the exact labels (Title Case) without
 * depending on lang-file override paths, which were not being picked up.
 *
 * Also wires the Part 11 access controls (both default OFF, toggled in Settings):
 *  - Account lockout after N failed attempts for M minutes.
 *  - Password expiry: on login, flag must_change_password when the password is older
 *    than the configured age.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $email = is_array($this->data ?? null) ? ($this->data['email'] ?? null) : null;
        $user = $email ? User::where('email', $email)->first() : null;

        // Lockout pre-check (only when enabled).
        if ($user && (bool) Setting::get('lockout_enabled', false)
            && $user->locked_until && $user->locked_until->isFuture()) {
            $mins = (int) ceil(max(0, $user->locked_until->getTimestamp() - now()->getTimestamp()) / 60);
            throw ValidationException::withMessages([
                'data.email' => 'This account is temporarily locked due to failed sign-in attempts. Try again in ' . max(1, $mins) . ' minute(s).',
            ]);
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $e) {
            // Failed credentials: count toward lockout (only when enabled).
            if ($user && (bool) Setting::get('lockout_enabled', false)) {
                $user->failed_login_attempts = (int) $user->failed_login_attempts + 1;
                if ($user->failed_login_attempts >= (int) Setting::get('lockout_threshold', 5)) {
                    $user->locked_until = now()->addMinutes((int) Setting::get('lockout_minutes', 15));
                    $user->failed_login_attempts = 0;
                }
                $user->saveQuietly();
            }
            throw $e;
        }

        // Success: reset counters; flag expiry if enabled.
        $authed = \Illuminate\Support\Facades\Auth::user();
        if ($authed) {
            $dirty = false;
            if ($authed->failed_login_attempts || $authed->locked_until) {
                $authed->failed_login_attempts = 0;
                $authed->locked_until = null;
                $dirty = true;
            }
            if ((bool) Setting::get('password_expiry_enabled', false)) {
                $days = (int) Setting::get('password_expiry_days', 90);
                $changed = $authed->password_changed_at;
                if (! $changed || $changed->lt(now()->subDays($days))) {
                    $authed->must_change_password = true;
                    $dirty = true;
                }
            }
            if ($dirty) $authed->saveQuietly();
        }

        return $response;
    }

    public function getHeading(): string
    {
        return 'Sign In';
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Sign In');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email Address')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password')
            ->hint(filament()->hasPasswordReset() ? new \Illuminate\Support\HtmlString('<a class="fi-link" href="' . filament()->getRequestPasswordResetUrl() . '">' . 'Forgot Your Password?' . '</a>') : null)
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Remember Me');
    }
}

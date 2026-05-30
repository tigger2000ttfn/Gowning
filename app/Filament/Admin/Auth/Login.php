<?php

namespace App\Filament\Admin\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Actions\Action;

/**
 * Custom login page so we control the exact labels (Title Case) without
 * depending on lang-file override paths, which were not being picked up.
 */
class Login extends BaseLogin
{
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

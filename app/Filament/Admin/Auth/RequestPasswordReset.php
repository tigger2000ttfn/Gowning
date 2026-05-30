<?php

namespace App\Filament\Admin\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function getHeading(): string
    {
        return 'Reset Your Password';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email Address')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()->label('Send Reset Link');
    }
}

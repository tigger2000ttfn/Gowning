<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;

/**
 * Applies the relay settings (saved in the Settings page) onto Laravel's live
 * mail config at runtime, so admins can configure email without touching .env.
 * Only overrides when a host is actually set; otherwise the .env/default stands.
 */
class MailConfig
{
    public static function apply(): void
    {
        // Settings table may not exist yet during early migrations; guard.
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $host = Setting::get('mail_host');
        if (! $host) {
            return; // nothing configured; leave framework defaults
        }

        $port = (int) (Setting::get('mail_port') ?: 587);
        $encryption = Setting::get('mail_encryption', 'tls');
        $username = Setting::get('mail_username') ?: null;
        $password = Setting::get('mail_password') ?: null;
        $fromAddress = Setting::get('mail_from_address') ?: Config::get('mail.from.address');
        $fromName = Setting::get('mail_from_name') ?: Config::get('mail.from.name');

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);
        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);
    }
}

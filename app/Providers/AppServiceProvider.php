<?php

namespace App\Providers;

use App\Models\ClassCompletion;
use App\Models\Personnel;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Reservation;
use App\Models\RunSlot;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\QualificationEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Models whose changes are written to the audit trail. */
    private const AUDITED = [
        User::class,
        Personnel::class,
        Qualification::class,
        QualificationRun::class,
        RunSlot::class,
        Reservation::class,
        ClassCompletion::class,
    ];

    public function register(): void
    {
        $this->app->singleton(QualificationEngine::class);
    }

    public function boot(): void
    {
        // Flash toasts at the BOTTOM-RIGHT (Filament default is top-right). Alignment is already
        // Right; set vertical alignment to End so toasts stack up from the bottom.
        \Filament\Notifications\Livewire\Notifications::verticalAlignment(\Filament\Support\Enums\VerticalAlignment::End);
        \Filament\Notifications\Livewire\Notifications::alignment(\Filament\Support\Enums\Alignment::Right);

        foreach (self::AUDITED as $model) {
            $model::observe(AuditObserver::class);
        }

        // GMP date display: 11-MAY-2026 (uppercase month, hyphenated) and 24-hour time.
        // Registered on BOTH Carbon and CarbonImmutable so $carbon->gmp() works on either
        // (model date casts are mutable Carbon; some pages build CarbonImmutable explicitly).
        $registerGmp = function (string $name, \Closure $fn): void {
            \Carbon\Carbon::macro($name, $fn);
            \Carbon\CarbonImmutable::macro($name, $fn);
        };
        $registerGmp('gmp', function () { return strtoupper($this->format('d-M-Y')); });                       // 11-MAY-2026
        $registerGmp('gmpDt', function () { return strtoupper($this->format('d-M-Y')) . ' ' . $this->format('H:i'); }); // 11-MAY-2026 14:30
        $registerGmp('gmpL', function () { return strtoupper($this->format('l, d-M-Y')); });                   // MONDAY, 11-MAY-2026
        $registerGmp('gmpD', function () { return strtoupper($this->format('D, d-M-Y')); });                   // MON, 11-MAY-2026
        $registerGmp('gmpLDM', function () { return strtoupper($this->format('l, d-M')); });                   // MONDAY, 11-MAY
        $registerGmp('gmpDDM', function () { return strtoupper($this->format('D, d-M')); });                   // MON, 11-MAY
        $registerGmp('gmpDM', function () { return strtoupper($this->format('d-M')); });                       // 11-MAY
        $registerGmp('gmpMY', function () { return strtoupper($this->format('M Y')); });                       // MAY 2026

        // Bind relay settings (Settings page) onto the live mail config at runtime.
        \App\Support\MailConfig::apply();
    }
}

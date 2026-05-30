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
        foreach (self::AUDITED as $model) {
            $model::observe(AuditObserver::class);
        }

        // GMP date display: 11-MAY-2026 (uppercase month, hyphenated) and 24-hour time.
        // Used app-wide via $carbon->gmp() / $carbon->gmpDt().
        \Carbon\Carbon::macro('gmp', function () {
            /** @var \Carbon\Carbon $this */
            return strtoupper($this->format('d-M-Y'));
        });
        \Carbon\Carbon::macro('gmpDt', function () {
            /** @var \Carbon\Carbon $this */
            return strtoupper($this->format('d-M-Y')) . ' ' . $this->format('H:i');
        });
        // Weekday + GMP date and calendar-label variants, all uppercase.
        \Carbon\Carbon::macro('gmpL', fn () => strtoupper($this->format('l, d-M-Y')));   // MONDAY, 11-MAY-2026
        \Carbon\Carbon::macro('gmpD', fn () => strtoupper($this->format('D, d-M-Y')));   // MON, 11-MAY-2026
        \Carbon\Carbon::macro('gmpLDM', fn () => strtoupper($this->format('l, d-M')));    // MONDAY, 11-MAY
        \Carbon\Carbon::macro('gmpDDM', fn () => strtoupper($this->format('D, d-M')));    // MON, 11-MAY
        \Carbon\Carbon::macro('gmpDM', fn () => strtoupper($this->format('d-M')));        // 11-MAY
        \Carbon\Carbon::macro('gmpMY', fn () => strtoupper($this->gmpMY()));        // MAY 2026

        // Bind relay settings (Settings page) onto the live mail config at runtime.
        \App\Support\MailConfig::apply();
    }
}

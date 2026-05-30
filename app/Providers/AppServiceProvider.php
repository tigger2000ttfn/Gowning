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

        // Bind relay settings (Settings page) onto the live mail config at runtime.
        \App\Support\MailConfig::apply();
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Time-based incubation automation: promote Incubating -> Awaiting Results daily.
Artisan::command('gqs:advance-incubation', function () {
    $moved = app(\App\Services\IncubationAdvancer::class)->run();
    $this->info("Incubation advancer: {$moved} qualification(s) moved to Awaiting Results.");
})->purpose('Advance qualifications past incubation when the period has elapsed');

Schedule::command('gqs:advance-incubation')->dailyAt('06:00');

// Yearly lifecycle: lapse anyone past their qualification due date into a 3-run requal.
Artisan::command('gqs:advance-lifecycle', function () {
    $n = app(\App\Services\LifecycleAdvancer::class)->run();
    $this->info("Lifecycle advancer: {$n} qualification(s) lapsed into requalification.");
})->purpose('Lapse qualifications past their due date into a 3-run requalification');

Schedule::command('gqs:advance-lifecycle')->dailyAt('06:05');

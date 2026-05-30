<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// Public-facing site (no login required)
Route::get('/', [PublicController::class, 'home'])->name('public.home');

Route::get('/classes', [PublicController::class, 'classes'])->name('public.classes');
Route::get('/runs', [PublicController::class, 'runs'])->name('public.runs');
Route::get('/calendar', [PublicController::class, 'calendar'])->name('public.calendar');
Route::get('/calendar/events', [PublicController::class, 'calendarEvents'])->name('public.calendar.events');

Route::get('/classes/{session}/signup', [PublicController::class, 'showSignup'])->name('public.signup');
Route::post('/classes/{session}/signup', [PublicController::class, 'storeSignup'])->name('public.signup.store');

Route::get('/runs/{slot}/signup', [PublicController::class, 'showRunSignup'])->name('public.run.signup');
Route::post('/runs/{slot}/signup', [PublicController::class, 'storeRunSignup'])->name('public.run.signup.store');

// Add-to-calendar (.ics) downloads for Outlook / Teams / Google / Apple
Route::get('/runs/{slot}/calendar.ics', [PublicController::class, 'runIcs'])->name('public.run.ics');
Route::get('/classes/{session}/calendar.ics', [PublicController::class, 'classIcs'])->name('public.class.ics');


Route::view('/icon-options', 'public.icon-preview')->name('public.icons');

Route::get('/register', [PublicController::class, 'showRegister'])->name('public.register');
Route::post('/register', [PublicController::class, 'storeRegister'])->name('public.register.store');

// Authenticated printable documents (Astellas-themed, open in new tab, print/save PDF)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/print/run-day', [\App\Http\Controllers\PrintController::class, 'runDay'])->name('print.run-day');
    Route::get('/print/report', [\App\Http\Controllers\PrintController::class, 'report'])->name('print.report');
    Route::get('/print/class-attendance/{session}/{file?}', [\App\Http\Controllers\PrintController::class, 'classAttendanceForm'])->name('print.class-attendance');
    Route::get('/print/approval/{qualification}/{file?}', [\App\Http\Controllers\PrintController::class, 'approvalForm'])->name('print.approval');
});

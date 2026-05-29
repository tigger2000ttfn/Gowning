<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// Public-facing site (no login required)
Route::get('/', [PublicController::class, 'home'])->name('public.home');

Route::get('/classes/{session}/signup', [PublicController::class, 'showSignup'])->name('public.signup');
Route::post('/classes/{session}/signup', [PublicController::class, 'storeSignup'])->name('public.signup.store');

Route::get('/register', [PublicController::class, 'showRegister'])->name('public.register');
Route::post('/register', [PublicController::class, 'storeRegister'])->name('public.register.store');

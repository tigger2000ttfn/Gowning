<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// Public-facing site (no login required)
Route::get('/', [PublicController::class, 'home'])->name('public.home');

Route::get('/classes', [PublicController::class, 'classes'])->name('public.classes');
Route::get('/runs', [PublicController::class, 'runs'])->name('public.runs');

Route::get('/classes/{session}/signup', [PublicController::class, 'showSignup'])->name('public.signup');
Route::post('/classes/{session}/signup', [PublicController::class, 'storeSignup'])->name('public.signup.store');

Route::get('/runs/{slot}/signup', [PublicController::class, 'showRunSignup'])->name('public.run.signup');
Route::post('/runs/{slot}/signup', [PublicController::class, 'storeRunSignup'])->name('public.run.signup.store');


Route::get('/register', [PublicController::class, 'showRegister'])->name('public.register');
Route::post('/register', [PublicController::class, 'storeRegister'])->name('public.register.store');

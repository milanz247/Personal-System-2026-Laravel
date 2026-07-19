<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register'])->name('register.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('profile/documents/{type}', [ProfileController::class, 'downloadDocument'])->name('profile.document');
    Route::put('accounts/{account}/balance', [AccountController::class, 'updateBalance'])->name('accounts.update-balance');
    Route::resource('accounts', AccountController::class)->except(['create', 'show', 'edit']);
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
});

require __DIR__.'/settings.php';

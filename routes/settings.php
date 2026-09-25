<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    // Tenant-gated: these are the current account's settings, so there has to
    // be a current account. The profile routes above deliberately are not.
    Route::middleware('tenant')->group(function () {
        Route::get('settings', [AccountController::class, 'edit'])->name('settings');
        Route::patch('settings', [AccountController::class, 'update'])->name('account.update');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::put('profile/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('profile.edit'),
        'manage' => route('profile.edit'),
    ]);
})->name('well-known.passkeys');

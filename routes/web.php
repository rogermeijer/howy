<?php

use App\Http\Controllers\CurrentAccountController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

// Deliberately public: the language switcher has to work on the marketing and
// auth screens, before there is an account to store the choice against.
Route::put('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::inertia('dashboard', 'inbox')->name('dashboard');
    Route::inertia('inbox', 'inbox')->name('inbox');

    Route::get('emails/{email}', fn (string $email) => Inertia::render('emails/show', [
        'emailId' => $email,
    ]))->name('emails.show');

    Route::inertia('knowledge', 'knowledge')->name('knowledge');
});

// Not tenant-gated: someone healing out of a bad account state still needs to
// be able to switch, and switching must work before email verification.
Route::middleware(['auth'])->group(function () {
    Route::put('current-account', [CurrentAccountController::class, 'update'])
        ->name('current-account.update');
});

require __DIR__.'/settings.php';

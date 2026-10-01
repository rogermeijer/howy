<?php

use App\Http\Controllers\Mailboxes\GmailOAuthController;
use App\Http\Controllers\Mailboxes\MailboxController;
use App\Http\Controllers\Mailboxes\MailboxImportController;
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

        // Mailboxes belong to the account, so only its administrators manage them.
        Route::middleware('account.admin')->prefix('settings/mailboxes')->name('mailboxes.')->group(function () {
            Route::get('gmail/redirect', [GmailOAuthController::class, 'redirect'])->name('gmail.redirect');
            Route::get('gmail/callback', [GmailOAuthController::class, 'callback'])->name('gmail.callback');

            Route::post('{mailbox}/sync', [MailboxController::class, 'sync'])->name('sync');
            Route::delete('{mailbox}', [MailboxController::class, 'destroy'])->name('destroy');

            Route::get('{mailbox}/import/search', [MailboxImportController::class, 'search'])->name('import.search');
            Route::post('{mailbox}/imports', [MailboxImportController::class, 'store'])->name('import.store');
        });
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

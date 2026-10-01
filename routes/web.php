<?php

use App\Http\Controllers\CurrentAccountController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Webhooks\GmailWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// Valet serves `.webmanifest` as application/octet-stream; Chrome rejects that MIME type.
Route::get('site.webmanifest', function () {
    return response(
        file_get_contents(resource_path('site.webmanifest')),
        headers: ['Content-Type' => 'application/manifest+json; charset=UTF-8'],
    );
})->name('site.webmanifest');

Route::inertia('/', 'welcome')->name('home');

// Deliberately public: the language switcher has to work on the marketing and
// auth screens, before there is an account to store the choice against.
Route::put('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('dashboard', [InboxController::class, 'index'])->name('dashboard');
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox');

    Route::get('emails/{email}', [EmailController::class, 'show'])->name('emails.show');

    Route::inertia('knowledge', 'knowledge')->name('knowledge');
});

// Not tenant-gated: someone healing out of a bad account state still needs to
// be able to switch, and switching must work before email verification.
Route::middleware(['auth'])->group(function () {
    Route::put('current-account', [CurrentAccountController::class, 'update'])
        ->name('current-account.update');
});

// Gmail pushes here through Cloud Pub/Sub. No session and no CSRF token: the
// request is authenticated by the Google-signed OIDC token instead.
Route::post('webhooks/gmail', GmailWebhookController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.gmail');

require __DIR__.'/settings.php';

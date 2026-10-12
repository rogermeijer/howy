<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AccountMemberController;
use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\Admin\BetaAccessController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// The platform back office, for super-admins (users.is_admin) only. Not
// tenant-gated: it works across accounts, and only through the sanctioned
// paths in CLAUDE.md — accounts and users are platform data, and the AI log
// reads through App\Services\Admin\AiUsageLog.
Route::middleware(['auth', 'verified', 'super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/accounts')->name('index');

    Route::resource('accounts', AccountController::class)->except(['edit']);
    Route::post('accounts/{account}/members', [AccountMemberController::class, 'store'])->name('accounts.members.store');
    Route::patch('accounts/{account}/members/{user}', [AccountMemberController::class, 'update'])->name('accounts.members.update');
    Route::delete('accounts/{account}/members/{user}', [AccountMemberController::class, 'destroy'])->name('accounts.members.destroy');

    Route::resource('users', UserController::class)->except(['edit']);

    Route::get('beta', [BetaAccessController::class, 'index'])->name('beta.index');
    Route::post('beta', [BetaAccessController::class, 'store'])->name('beta.store');
    Route::post('beta/{invite}/send', [BetaAccessController::class, 'send'])->name('beta.send');
    Route::delete('beta/{invite}', [BetaAccessController::class, 'destroy'])->name('beta.destroy');

    Route::get('ai', [AiUsageController::class, 'index'])->name('ai.index');
    Route::get('ai/{call}', [AiUsageController::class, 'show'])->whereNumber('call')->name('ai.show');
});

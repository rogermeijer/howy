<?php

use App\Http\Controllers\CurrentAccountController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailStatementController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\Knowledge\DocumentController;
use App\Http\Controllers\Knowledge\DocumentInspectorController;
use App\Http\Controllers\Knowledge\DocumentVersionController;
use App\Http\Controllers\Knowledge\KnowledgeController;
use App\Http\Controllers\Knowledge\KnowledgeSearchController;
use App\Http\Controllers\Knowledge\KnowledgeTopicController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Webhooks\GmailWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Valet serves `.webmanifest` as application/octet-stream; Chrome rejects that MIME type.
Route::get('site.webmanifest', function () {
    return response(
        File::get(resource_path('site.webmanifest')),
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

    // What a mail would add to the knowledge base: its admins decide, as they curate it.
    Route::middleware('account.admin')->group(function () {
        Route::post('emails/{email}/statements/{statement}/approve', [EmailStatementController::class, 'approve'])
            ->whereNumber('statement')
            ->name('emails.statements.approve');
        Route::post('emails/{email}/statements/{statement}/reject', [EmailStatementController::class, 'reject'])
            ->whereNumber('statement')
            ->name('emails.statements.reject');
    });

    Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge');

    Route::prefix('knowledge')->name('knowledge.')->group(function () {
        Route::get('search', [KnowledgeSearchController::class, 'index'])->name('search');
        Route::get('folders', [KnowledgeTopicController::class, 'index'])->name('topics.index');
        Route::get('folders/{topic}', [KnowledgeTopicController::class, 'show'])->name('topics.show');
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{document}', [DocumentInspectorController::class, 'show'])->name('documents.show');
        Route::get('documents/{document}/versions/{version}/file', [DocumentVersionController::class, 'file'])
            ->scopeBindings()
            ->name('documents.versions.file');

        // Everyone in the account reads the knowledge base; its admins curate it.
        Route::middleware('account.admin')->group(function () {
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::patch('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
            Route::post('documents/{document}/reprocess', [DocumentController::class, 'reprocess'])->name('documents.reprocess');
            Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])->name('documents.versions.store');

            Route::post('folders', [KnowledgeTopicController::class, 'store'])->name('topics.store');
            Route::post('folders/approve', [KnowledgeTopicController::class, 'approveAll'])->name('topics.approve-all');
            Route::patch('folders/{topic}', [KnowledgeTopicController::class, 'update'])->name('topics.update');
            Route::post('folders/{topic}/approve', [KnowledgeTopicController::class, 'approve'])->name('topics.approve');
            Route::post('folders/{topic}/move', [KnowledgeTopicController::class, 'move'])->name('topics.move');
            Route::post('folders/{topic}/merge', [KnowledgeTopicController::class, 'merge'])->name('topics.merge');
            Route::delete('folders/{topic}', [KnowledgeTopicController::class, 'destroy'])->name('topics.destroy');
            Route::post('folders/{topic}/links', [KnowledgeTopicController::class, 'link'])->name('topics.links.store');
            Route::delete('folders/{topic}/links/{link}', [KnowledgeTopicController::class, 'unlink'])->scopeBindings()->name('topics.links.destroy');
        });
    });
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
